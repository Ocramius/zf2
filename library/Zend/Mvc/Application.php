<?php

namespace Zend\Mvc;

use ArrayObject,
    Zend\EventManager\EventCollection,
    Zend\EventManager\EventManager,
    Zend\Http\Header\Cookie,
    Zend\Http\PhpEnvironment\Request as PhpHttpRequest,
    Zend\Http\PhpEnvironment\Response as PhpHttpResponse,
    Zend\Uri\Http as HttpUri,
    Zend\Stdlib\Dispatchable,
    Zend\Stdlib\ArrayUtils,
    Zend\Stdlib\Parameters,
    Zend\Stdlib\RequestDescription as Request,
    Zend\Stdlib\ResponseDescription as Response;

use Zend\InstanceManager\InstanceManager,
    Zend\InstanceManager\ConfigurationInterface as InstanceConfigurationInterface;

/**
 * Main application class for invoking applications
 *
 * Expects the user will provide a Service Locator or Dependency Injector, as
 * well as a configured router. Once done, calling run() will invoke the
 * application, first routing, then dispatching the discovered controller. A
 * response will then be returned, which may then be sent to the caller.
 */
class Application implements ApplicationInterface, InstanceConfigurationInterface
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
    protected $eventManager;

    /**
     * @var array
     */
    protected $configuration = null;

    /**
     * @var InstanceManager
     */
    protected $instanceManager = null;



    public function __construct($configuration, InstanceManager $instanceManager = null)
    {
        $this->configuration = $configuration;
        $this->instanceManager = ($instanceManager) ?: new InstanceManager();
        $this->configureInstanceManager($this->instanceManager);
    }

    public function configureInstanceManager(InstanceManager $im)
    {
        $im->setFactory('EventManager', function (InstanceManager $im) {
            $em = new EventManager;
            $em->setSharedCollections($im->get('SharedEventManager'));
            return $em;
        });

        $im->setFactory('SharedEventManager', function () {
            return new \Zend\EventManager\SharedEventManager();
        });

        $im->setShared('EventManager', false);

        $configuration = $this->configuration;

        $im->setFactory('ModuleManager', function (InstanceManager $im) use ($configuration) {
            $listenerOptions  = new \Zend\Module\Listener\ListenerOptions($configuration['module_listener_options']);
            $defaultListeners = new \Zend\Module\Listener\DefaultListenerAggregate($listenerOptions);
            $defaultListeners->getConfigListener()->addConfigGlobPath("config/autoload/{,*.}{global,local}.config.php");

            $moduleManager = new \Zend\Module\Manager($configuration['modules'], $im->get('EventManager'));
            $moduleManager->events()->attachAggregate($defaultListeners);
            return $moduleManager;
        });

        // controller factory
        $im->addAbstractFactory(function ($im, $name) {
            $mm = $im->get('ModuleManager');
            $moduleParams = $mm->getEvent()->getParams();
            $config = $moduleParams['configListener']->getMergedConfig();
            $controllers = array_change_key_case($config['controllers']->toArray());
            if (isset($controllers[$name])) {
                return new $controllers[$name];
            }
            return false;
        });

        /* This probably needs to be moved, but lets have it here to get it working */
        $im->set('View', $view = new \Zend\View\View);
        $view->setEventManager($im->get('EventManager'));
        $view->events()->attach(new \Zend\View\Strategy\PhpRendererStrategy(
            $phpRenderer = new \Zend\View\Renderer\PhpRenderer(array('resolver' => 'Zend\View\Resolver\AggregateResolver'))
        ));
        $phpRenderer->setResolver($resolver = new \Zend\View\Resolver\AggregateResolver());
        $resolver->attach(new \Zend\View\Resolver\TemplateMapResolver(array('layout/layout' => getcwd() . '/view/layout/layout.phtml')));
        $resolver->attach(new \Zend\View\Resolver\TemplatePathStack(array('script_paths' => array('application' => getcwd() . '/view'))));



        $im->setAlias('EM', 'EventManager');
        $im->setAlias('MM', 'ModuleManager');
    }

    public function bootstrap()
    {
        $this->eventManager = $this->instanceManager->get('EventManager');
        $this->eventManager->setIdentifiers(array(
            __CLASS__,
            get_called_class(),
        ));
        $this->eventManager->attach('route', array($this, 'route'));
        $this->eventManager->attach('dispatch', array($this, 'dispatch'));

        // this has to do with view:
        $this->events()->attach($defaultRenderer = new \Zend\Mvc\View\DefaultRenderingStrategy($this->instanceManager->get('View')));
        $defaultRenderer->setLayoutTemplate('layout/layout');

        $im = $this->instanceManager;

        if ($im->has('Router')) {
            $this->router = $im->get('Router');
        } else {
            $im->set('Router', ($this->router = new Router\Http\TreeRouteStack()));
        }

        if ($im->has('Request')) {
            $this->request = $im->get('Request');
        } else {
            $im->set('Request', ($this->request = new PhpHttpRequest()));
        }

        if ($im->has('Response')) {
            $this->response = $im->get('Response');
        } else {
            $im->set('Response', ($this->response = new PhpHttpResponse()));
        }

        $this->event = $event  = new MvcEvent();
        $event->setTarget($this);
        $event->setRequest($this->getRequest())
            ->setResponse($this->getResponse())
            ->setRouter($this->getRouter());

        $this->events()->trigger('bootstrap', $this, array(
            'application' => $this,
            'config'      => $this->configuration,
        ));

        $moduleManager = $im->get('ModuleManager');
        $moduleManager->loadModules();
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
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventCollection
     */
    public function events()
    {
        return $this->eventManager;
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

    /**
     * Route the request
     *
     * @param  MvcEvent $e
     * @return Router\RouteMatch
     */
    public function route(MvcEvent $e)
    {
        $request = $e->getRequest();
        $router  = $e->getRouter();

        $mm = $this->instanceManager->get('mm');
        $moduleParams = $mm->getEvent()->getParams();
        $config = $moduleParams['configListener']->getMergedConfig();

        foreach ($config['routes'] as $name => $route) {
            $router->addRoute($name, $route);
        }

        $routeMatch = $router->match($request);

        if (!$routeMatch instanceof Router\RouteMatch) {
            $e->setError(static::ERROR_ROUTER_NO_MATCH);

            $results = $this->events()->trigger('dispatch.error', $e);
            if (count($results)) {
                $return  = $results->last();
            } else {
                $return = $e->getParams();
            }
            return $return;
        }

        $e->setRouteMatch($routeMatch);
        return $routeMatch;
    }

    /**
     * Dispatch the matched route
     *
     * @param  MvcEvent $e
     * @return mixed
     */
    public function dispatch(MvcEvent $e)
    {
        $routeMatch     = $e->getRouteMatch();
        $controllerName = $routeMatch->getParam('controller', 'not-found');
        $events         = $this->events();

        try {
            $controller = $this->instanceManager->get($controllerName);
        } catch (ClassNotFoundException $exception) {
            $error = clone $e;
            $error->setError(static::ERROR_CONTROLLER_NOT_FOUND)
                  ->setController($controllerName)
                  ->setParam('exception', $exception);

            $results = $events->trigger('dispatch.error', $error);
            if (count($results)) {
                $return  = $results->last();
            } else {
                $return = $error->getParams();
            }
            goto complete;
        }

        if (!$controller instanceof Dispatchable) {
            $error = clone $e;
            $error->setError(static::ERROR_CONTROLLER_INVALID)
                  ->setController($controllerName)
                  ->setControllerClass(get_class($controller));

            $results = $events->trigger('dispatch.error', $error);
            if (count($results)) {
                $return  = $results->last();
            } else {
                $return = $error->getParams();
            }
            goto complete;
        }

        $request  = $e->getRequest();
        $response = $this->getResponse();

        if ($controller instanceof InjectApplicationEvent) {
            $controller->setEvent($e);
        }

        try {
            $return   = $controller->dispatch($request, $response);
        } catch (\Exception $ex) {
            $error = clone $e;
            $error->setError(static::ERROR_EXCEPTION)
                  ->setController($controllerName)
                  ->setControllerClass(get_class($controller))
                  ->setParam('exception', $ex);
            $results = $events->trigger('dispatch.error', $error);
            if (count($results)) {
                $return  = $results->last();
            } else {
                $return = $error->getParams();
            }
        }

        complete:

        if (!is_object($return)) {
            if (ArrayUtils::hasStringKeys($return)) {
                $return = new ArrayObject($return, ArrayObject::ARRAY_AS_PROPS);
            }
        }
        $e->setResult($return);
        return $return;
    }

}
