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
        $controllerLoader = $serviceManager->createScopedServiceManager();
        $configuration = $serviceManager->get('Configuration');
        foreach ($configuration->controllers as $name => $controller) {
            $controllerLoader->setSource($name, $controller);
        }
        $controllerLoader->addInitializer(function ($instance) use ($serviceManager) {
            if ($instance instanceof EventManagerAware) {
                $instance->setEventManager($serviceManager->get('EventManager'));
            }
        });
        $controllerLoader->addInitializer(function ($instance) use ($serviceManager) {
            if ($instance instanceof Pluggable) {
                $instance->setBroker($serviceManager->get('ControllerPluginBroker'));
            }
        });
        return $controllerLoader;
    }
}
