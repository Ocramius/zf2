<?php

namespace Zend\Mvc;

use Zend\EventManager\EventCollection;
use Zend\EventManager\ListenerAggregate;

class RouteListener implements ListenerAggregate
{
    protected $listeners = array();

    public function attach(EventCollection $events)
    {
        $this->listeners[] = $events->attach('route', array($this, 'onRoute'));
    }

    public function detach(EventCollection $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onRoute($e)
    {
        $target  = $e->getTarget();
        $request = $e->getRequest();
        $router  = $e->getRouter();

        $instanceManager = $target->getServiceManager();
        $mm              = $instanceManager->get('ModuleManager');
        $moduleParams    = $mm->getEvent()->getParams();
        $config          = $moduleParams['configListener']->getMergedConfig();

        foreach ($config['routes'] as $name => $route) {
            $router->addRoute($name, $route);
        }

        $routeMatch = $router->match($request);

        if (!$routeMatch instanceof Router\RouteMatch) {
            $e->setError($target::ERROR_ROUTER_NO_MATCH);

            $results = $target->events()->trigger('dispatch.error', $e);
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
}
