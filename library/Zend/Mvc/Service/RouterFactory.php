<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\Mvc\Router\Http\TreeRouteStack as Router;

class RouterFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $config = $serviceManager->get('Configuration');
        $routes = $config->routes ?: array();
        $router = Router::factory($routes);
        return $router;
    }
}
