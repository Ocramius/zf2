<?php

namespace Zend\Mvc;

use Zend\Config\Config;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAware;
use Zend\EventManager\SharedEventManager;
use Zend\Http\PhpEnvironment\Request as PhpHttpRequest;
use Zend\Http\PhpEnvironment\Response as PhpHttpResponse;
use Zend\ServiceManager\ConfigurationInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\InstanceManagerAware;
use Zend\Loader\Pluggable;
use Zend\Module\Listener\ListenerOptions;
use Zend\Module\Listener\DefaultListenerAggregate;
use Zend\Module\Manager as ModuleManager;
use Zend\Stdlib\Dispatchable;
use Zend\View\HelperBroker as ViewHelperBroker;
use Zend\View\HelperLoader as ViewHelperLoader;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver\AggregateResolver;
use Zend\View\Resolver\TemplateMapResolver;
use Zend\View\Resolver\TemplatePathStack;
use Zend\View\Strategy\PhpRendererStrategy;
use Zend\View\View as ViewManager;

class ApplicationManager implements ConfigurationInterface
{
    protected $appConfig;

    public function __construct($appConfig)
    {
        $this->appConfig = $appConfig;
    }

    public function configureInstanceManager(ServiceManager $im)
    {
        $im->setFactory('SharedEventManager', function($im) {
            return new SharedEventManager();
        });

        $im->setFactory('EventManager', function($im) {
            $sharedEvents = $im->get('SharedEventManager');
            $eventManager = new EventManager();
            $eventManager->setSharedCollections($sharedEvents);
            return $eventManager;
        });
        $im->setShared('EventManager', false);
        $im->setAlias('EM', 'EventManager');

        $configuration = $this->appConfig;

        $im->setFactory('ModuleManager', function (ServiceManager $im) use ($configuration) {
            $listenerOptions  = new ListenerOptions($configuration['module_listener_options']);
            $defaultListeners = new DefaultListenerAggregate($listenerOptions);
            $defaultListeners->getConfigListener()->addConfigGlobPath("config/autoload/{,*.}{global,local}.config.php");

            $moduleManager = new ModuleManager($configuration['modules'], $im->get('EventManager'));
            $moduleManager->events()->attachAggregate($defaultListeners);
            return $moduleManager;
        });
        $im->setAlias('MM', 'ModuleManager');

        // Config
        $im->setFactory('config', function($im) {
            $mm           = $im->get('ModuleManager');
            $mm->loadModules();
            $moduleParams = $mm->getEvent()->getParams();
            $config       = $moduleParams['configListener']->getMergedConfig();
            return $config;
        });

        // Request/Response
        $im->set('Request', new PhpHttpRequest());
        $im->set('Response', new PhpHttpResponse());

        // Router
        $im->setFactory('Router', function($im, $name) {
            $config = $im->get('config');
            $routes = $config->routes ?: array();
            $router = Router\Http\TreeRouteStack::factory($routes);
            return $router;
        });
        $im->setFactory('RouteListener', function($im) {
            return new RouteListener();
        });
        $im->setFactory('DispatchListener', function ($im) {
            return new DispatchListener();
        });


        // controller factory
        $im->setFactory('ControllerPluginLoader', function ($im, $name) {
            $config = $im->get('config');
            $map    = (isset($config->controller) && isset($config->controller->map)) ? $config->controller->map: array();
            $loader = new Controller\PluginLoader($map);
            return $loader;
        });
        $im->setFactory('ControllerPluginBroker', function ($im, $name) {
            $broker = new Controller\PluginBroker();
            $broker->setClassLoader($im->get('ControllerPluginLoader'));
            return $broker;
        });
        $im->addAbstractFactory(function ($im, $name) {
            if ($im->has($name)) {
                $controller = $im->get($name);
                return $controller;
            }

            $config       = $im->get('config');
            $controllers  = array_change_key_case($config['controllers']->toArray());
            if (!isset($controllers[$name])) {
                return false;
            }
            $controller = new $controllers[$name];
            if ($controller instanceof InstanceManagerAware) {
                $controller->setInstanceManager($im);
            }
            if ($controller instanceof EventManagerAware) {
                $controller->setEventManager($im->get('EventManager'));
            }
            if ($controller instanceof Pluggable) {
                $controller->setBroker($im->get('ControllerPluginBroker'));
            }

            return $controller;
        });

        // View layer
        $im->setFactory('ViewHelperLoader', function($im, $name) {
            $config = $im->get('config');
            if (isset($config->view) && isset($config->view->helper_map)) {
                $map = $config->view->helper_map;
            } else {
                $map = array();
            }
            return new ViewHelperLoader($map);
        });
        $im->setFactory('TemplateMapResolver', function($im, $name) {
            $config = $im->get('config');
            if (isset($config->view) && isset($config->view->template_map)) {
                $map = $config->view->template_map;
            } else {
                $map = array();
            }
            return new TemplateMapResolver($map);
        });
        $im->setFactory('TemplatePathStack', function($im, $name) {
            $config = $im->get('config');
            if (isset($config->view) && isset($config->view->template_path_stack)) {
                $stack = $config->view->template_path_stack;
            } else {
                $stack = array();
            }
            return new TemplatePathStack($stack);
        });
        $im->setFactory('AggregateResolver', function($im, $name) {
            $map   = $im->get('TemplateMapResolver');
            $stack = $im->get('TemplatePathStack');
            $aggregate = new AggregateResolver();
            $aggregate->attach($map);
            $aggregate->attach($stack);
            return $aggregate;
        });
        $im->setFactory('PhpRenderer', function($im, $name) {
            $resolver     = $im->get('AggregateResolver');
            $helperLoader = $im->get('ViewHelperLoader');

            $config = $im->get('config');
            if (isset($config->view)) {
                $config = $config->view;
            } else {
                $config = new Config(array());
            }

            $broker       = new ViewHelperBroker();
            $broker->setClassLoader($helperLoader);

            $url          = $broker->load('url');
            $url->setRouter($im->get('Router'));
            $basePath     = $broker->load('basePath');
            $basePath->setBasePath(($config->base_path ?: '/'));

            $renderer     = new PhpRenderer();
            $renderer->setBroker($broker);
            $renderer->setResolver($resolver);
            return $renderer;
        });
        $im->setFactory('PhpRendererStrategy', function($im, $name) {
            $renderer   = $im->get('PhpRenderer');
            return new PhpRendererStrategy($renderer);
        });
        $im->setFactory('View', function($im, $name) {
            $view = new ViewManager;
            $view->setEventManager($im->get('EventManager'));
            $view->events()->attachAggregate($im->get('PhpRendererStrategy'));
            return $view;
        });

        $im->setFactory('DefaultRenderingStrategy', function($im, $name) {
            $config   = $im->get('config');
            $strategy = new View\DefaultRenderingStrategy($im->get('View'));
            $layout   = (isset($config->view) && isset($config->view->layout)) ? $config->view->layout : 'layout/layout';
            $strategy->setLayoutTemplate($layout);
            return $strategy;
        });

        $im->setFactory('RouteNotFoundStrategy', function($im, $name) {
            $config                = $im->get('config');
            $displayNotFoundReason = ($config->view->display_not_found_reason) ?: false;
            $notFoundTemplate      = ($config->view->not_found_template) ?: '404';
            $strategy              = new View\RouteNotFoundStrategy();
            $strategy->setDisplayNotFoundReason($displayNotFoundReason);
            $strategy->setNotFoundTemplate($notFoundTemplate);
            return $strategy;
        });

        $im->setFactory('ExceptionStrategy', function($im, $name) {
            $config                = $im->get('config');
            $displayExceptions = ($config->view->display_exceptions) ?: false;
            $exceptionTemplate      = ($config->view->exception_template) ?: 'error';
            $strategy              = new View\ExceptionStrategy();
            $strategy->setDisplayExceptions($displayExceptions);
            $strategy->setExceptionTemplate($exceptionTemplate);
            return $strategy;
        });
    }
}
