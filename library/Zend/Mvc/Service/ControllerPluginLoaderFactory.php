<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\Mvc\Controller\PluginLoader as ControllerPluginLoader;


class ControllerPluginLoaderFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $config = $serviceManager->get('Configuration');
        $map    = (isset($config->controller) && isset($config->controller->map)) ? $config->controller->map: array();
        $loader = new ControllerPluginLoader($map);
        return $loader;
    }
}
