<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\AbstractFactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\ServiceManager\ServiceManagerAwareInterface,
    Zend\EventManager\EventManagerAware,
    Zend\Loader\Pluggable,
    Zend\View\View;

class ControllerLoaderFactory implements AbstractFactoryInterface
{
    public function createService(ServiceManager $serviceManager, $name)
    {
        /*
        $scopedSM = $serviceManager->createScopedServiceManager();
        $configuration = $serviceManager->get('Configuration');
        if (isset($configuration->controllers)) {
            foreach ($configuration->controllers as $name => $service) {
            }
        }
        */


        if ($serviceManager->has($name)) {
            $controller = $serviceManager->get($name);
            return $controller;
        }

        $config       = $serviceManager->get('Configuration');
        $controllers  = array_change_key_case($config['controllers']->toArray());
        if (!isset($controllers[$name])) {
            return false;
        }
        $controller = new $controllers[$name];
        if ($controller instanceof ServiceManagerAwareInterface) {
            $controller->setInstanceManager($serviceManager);
        }
        if ($controller instanceof EventManagerAware) {
            $controller->setEventManager($serviceManager->get('EventManager'));
        }
        if ($controller instanceof Pluggable) {
            $controller->setBroker($serviceManager->get('ControllerPluginBroker'));
        }

        return $controller;
    }
}
