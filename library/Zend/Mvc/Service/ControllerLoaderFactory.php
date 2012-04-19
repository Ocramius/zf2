<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\ServiceManager\ServiceManagerAwareInterface,
    Zend\ServiceManager\Di\DiServiceInitializer,
    Zend\Loader\Pluggable,
    Zend\View\View;

class ControllerLoaderFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $controllerLoader = $serviceManager->createScopedServiceManager();
        $configuration = $serviceManager->get('Configuration');
        foreach ($configuration->controllers as $name => $controller) {
            $controllerLoader->setInvokable($name, $controller);
        }
        $controllerLoader->addInitializer(new DiServiceInitializer($serviceManager->get('Di'), $serviceManager));
        $controllerLoader->addInitializer(function ($instance) use ($serviceManager) {
            if ($instance instanceof Pluggable) {
                $instance->setBroker($serviceManager->get('ControllerPluginBroker'));
            }
        });
        return $controllerLoader;
    }
}
