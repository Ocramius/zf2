<?php

namespace Zend\Mvc;

use ArrayObject;
use Zend\EventManager\EventCollection;
use Zend\EventManager\ListenerAggregate;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\Exception as InstanceException;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\Dispatchable;

class DispatchListener implements ListenerAggregate
{
    protected $listeners = array();

    public function attach(EventCollection $events)
    {
        $this->listeners[] = $events->attach('dispatch', array($this, 'onDispatch'));
    }

    public function detach(EventCollection $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onDispatch(MvcEvent $e)
    {
        $routeMatch     = $e->getRouteMatch();
        $controllerName = $routeMatch->getParam('controller', 'not-found');
        $application    = $e->getApplication();
        $events         = $application->events();
        $locator        = $application->getServiceManager();

        try {
            $controller = $locator->get($controllerName);
        } catch (InstanceException $exception) {
            $error = clone $e;
            $error->setError($application::ERROR_CONTROLLER_NOT_FOUND)
                  ->setController($controllerName)
                  ->setParam('exception', $exception);

            $results = $events->trigger('dispatch.error', $error);
            if (count($results)) {
                $return = $results->last();
            } else {
                $return = $error->getParams();
            }
            goto complete;
        }

        if (!$controller instanceof Dispatchable) {
            $error = clone $e;
            $error->setError($application::ERROR_CONTROLLER_INVALID)
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
        $response = $application->getResponse();

        if ($controller instanceof InjectApplicationEvent) {
            $controller->setEvent($e);
        }

        try {
            $return   = $controller->dispatch($request, $response);
        } catch (\Exception $ex) {
            $error = clone $e;
            $error->setError($application::ERROR_EXCEPTION)
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
