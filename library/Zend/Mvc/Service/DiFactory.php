<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\Di\Di,
    Zend\Di\Configuration as DiConfiguration;

class DiFactory implements FactoryInterface
{
    protected $sharedEventManager = null;

    public function createService(ServiceManager $serviceManager)
    {
        $config = $serviceManager->get('Configuration');

        $di = new Di();
        if (isset($config->di)) {
            $di->configure(new DiConfiguration($config->di));
        }

        return $di;
    }
}