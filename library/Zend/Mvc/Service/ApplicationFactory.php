<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\Mvc\Application;

class ApplicationFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        return new Application($serviceManager->get('Configuration'), $serviceManager);
    }
}