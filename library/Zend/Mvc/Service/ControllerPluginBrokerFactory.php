<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\Mvc\Controller\PluginBroker as ControllerPluginBroker;


class ControllerPluginBrokerFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $broker = new ControllerPluginBroker();
        $broker->setClassLoader($serviceManager->get('ControllerPluginLoader'));
        return $broker;
    }
}
