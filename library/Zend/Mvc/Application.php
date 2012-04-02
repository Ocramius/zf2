<?php

namespace Zend\Mvc;

use ArrayObject;
use Zend\EventManager\EventCollection;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAware;
use Zend\Http\Header\Cookie;
use Zend\Http\PhpEnvironment\Request as PhpHttpRequest;
use Zend\Http\PhpEnvironment\Response as PhpHttpResponse;
use Zend\InstanceManager\InstanceManager;
use Zend\InstanceManager\ConfigurationInterface as InstanceConfigurationInterface;
use Zend\Uri\Http as HttpUri;
use Zend\Stdlib\Dispatchable;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\Parameters;
use Zend\Stdlib\RequestDescription as Request;
use Zend\Stdlib\ResponseDescription as Response;

/**
 * Main application class for invoking applications
 *
 * Expects the user will provide a Service Locator or Dependency Injector, as
 * well as a configured router. Once done, calling run() will invoke the
 * application, first routing, then dispatching the discovered controller. A
 * response will then be returned, which may then be sent to the caller.
 */
class Application implements 
    ApplicationInterface, 
    EventManagerAware
{
    const ERROR_CONTROLLER_CANNOT_DISPATCH = 'error-controller-cannot-dispatch';
    const ERROR_CONTROLLER_NOT_FOUND       = 'error-controller-not-found';
    const ERROR_CONTROLLER_INVALID         = 'error-controller-invalid';
    const ERROR_EXCEPTION                  = 'error-exception';
    const ERROR_ROUTER_NO_MATCH            = 'error-router-no-match';

    protected $event;

    protected $request;
    protected $response;
    protected $router;

    /**
     * @var EventManager
     */
    protected $events;

    /**
     * @var array
     */
    protected $configuration = null;

    /**
     * @var InstanceManager
     */
    protected $instanceManager = null;

    /**
     * @var \Zend\Module\Manager
     */
    protected $moduleManager;


    public function __construct($configuration, InstanceManager $instanceManager = null)
    {
        $this->configuration = $configuration;

        if ((null === $instanceManager) && isset($configuration['instance_manager'])) {
            if ($configuration['instance_manager'] instanceof InstanceManager) {
                $instanceManager = $configuration['instance_manager'];
            }
            if (is_string($configuration['instance_manager']) && class_exists($configuration['instance_manager'])) {
                $instanceManager = new $configuration['instance_manager']();
            }
        }
        if (null === $instanceManager) {
            $instanceManager = new InstanceManager();
        }

        if (!isset($configuration['use_application_manager']) || $configuration['use_application_manager']) {
            $appManager = new ApplicationManager($configuration);
            $appManager->configureInstanceManager($instanceManager);
        }

        $this->setEventManager($instanceManager->get('EventManager'));
        $this->moduleManager = $instanceManager->get('ModuleManager');

        $this->events->attach($instanceManager->get('RouteListener'));
        $this->events->attach($instanceManager->get('DispatchListener'));
        $this->events->attach($instanceManager->get('DefaultRenderingStrategy'));
        $this->request  = $instanceManager->get('Request');
        $this->response = $instanceManager->get('Response');

        $this->instanceManager = $instanceManager;
    }

    public function getConfiguration()
    {
        $this->instanceManager->get('config');
    }

    public function bootstrap()
    {
        $instanceManager = $this->instanceManager;
        $events          = $this->events();
        $sharedEvents    = $events->getSharedCollections();

        // Setup error strategies
        $noRouteStrategy   = $instanceManager->get('RouteNotFoundStrategy');
        $exceptionStrategy = $instanceManager->get('ExceptionStrategy');
        $events->attach($noRouteStrategy);
        $events->attach($exceptionStrategy);

        // Setup default view events
        $createViewModelListener = new View\CreateViewModelListener();
        $injectTemplateListener  = new View\InjectTemplateListener();
        $injectViewModelListener = new View\InjectViewModelListener();

        $sharedEvents->attach('Zend\Stdlib\Dispatchable', 'dispatch', array($createViewModelListener, 'createViewModelFromArray'), -80);
        $sharedEvents->attach('Zend\Stdlib\Dispatchable', 'dispatch', array($createViewModelListener, 'createViewModelFromNull'), -80);
        $sharedEvents->attach('Zend\Stdlib\Dispatchable', 'dispatch', array($injectTemplateListener,  'injectTemplate'), -90);
        $sharedEvents->attach('Zend\Stdlib\Dispatchable', 'dispatch', array($injectViewModelListener, 'injectViewModel'), -100);
        $events->attach('dispatch.error', array($injectViewModelListener, 'injectViewModel'), -100);

        // Setup MVC Event
        $this->event = $event  = new MvcEvent();
        $event->setTarget($this);
        $event->setApplication($this)
              ->setRequest($this->getRequest())
              ->setResponse($this->getResponse())
              ->setRouter($instanceManager->get('Router'));

        // Setup "layout" view model for event
        $renderingStrategy = $instanceManager->get('DefaultRenderingStrategy');
        $viewModel         = $event->getViewModel();
        $viewModel->setTemplate($renderingStrategy->getLayoutTemplate());

        $renderer    = $this->instanceManager->get('PhpRenderer');
        $modelHelper = $renderer->plugin('view_model');
        $modelHelper->setRoot($viewModel);

        // Trigger bootstrap events
        $this->events()->trigger('bootstrap', $event);
    }

    public function getInstanceManager()
    {
        return $this->instanceManager;
    }

    /**
     * Get the request object
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the response object
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get the router object
     *
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * Get the MVC event instance
     * 
     * @return MvcEvent
     */
    public function getMvcEvent()
    {
        return $this->event;
    }

    /**
     * Set the event manager instance
     * 
     * @param  EventCollection $eventManager 
     * @return Application
     */
    public function setEventManager(EventCollection $eventManager)
    {
        $eventManager->setIdentifiers(array(
            __CLASS__,
            get_called_class(),
            'application',
        ));
        $this->events = $eventManager;
        return $this;
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventCollection
     */
    public function events()
    {
        return $this->events;
    }

    /**
     * Run the application
     *
     * @triggers route(MvcEvent)
     *           Routes the request, and sets the RouteMatch object in the event.
     * @triggers dispatch(MvcEvent)
     *           Dispatches a request, using the discovered RouteMatch and
     *           provided request.
     * @triggers dispatch.error(MvcEvent)
     *           On errors (controller not found, action not supported, etc.),
     *           populates the event with information about the error type,
     *           discovered controller, and controller class (if known).
     *           Typically, a handler should return a populated Response object
     *           that can be returned immediately.
     * @return SendableResponse
     */
    public function run()
    {
        $events = $this->events();
        $event  = $this->getMvcEvent();

        // Define callback used to determine whether or not to short-circuit
        $shortCircuit = function ($r) use ($event) {
            if ($r instanceof Response) {
                return true;
            }
            if ($event->getError()) {
                return true;
            }
            return false;
        };
        
        // Trigger route event
        $result = $events->trigger('route', $event, $shortCircuit);
        if ($result->stopped()) {
            $response = $result->last();
            if ($response instanceof Response) {
                $event->setTarget($this);
                $events->trigger('finish', $event);
                return $response;
            }
            if ($event->getError()) {
                return $this->completeRequest($event);
            }
            return $event->getResponse();
        }
        if ($event->getError()) {
            return $this->completeRequest($event);
        }

        // Trigger dispatch event
        $result = $events->trigger('dispatch', $event, $shortCircuit);

        // Complete response
        $response = $result->last();
        if ($response instanceof Response) {
            $event->setTarget($this);
            $events->trigger('finish', $event);
            return $response;
        }

        $response = $this->getResponse();
        $event->setResponse($response);

        return $this->completeRequest($event);
    }

    /**
     * Complete the request
     *
     * Triggers "render" and "finish" events, and returns response from
     * event object.
     * 
     * @param  MvcEvent $event 
     * @return Response
     */
    protected function completeRequest(MvcEvent $event)
    {
        $events = $this->events();
        $event->setTarget($this);
        $events->trigger('render', $event);
        $events->trigger('finish', $event);
        return $event->getResponse();
    }
}
