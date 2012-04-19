<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\Di\Di,
    Zend\Di\Configuration as DiConfiguration,
    Zend\ServiceManager\Di\DiAbstractServiceFactory;

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

        // register as abstract factory as well:
        $serviceManager->addAbstractSource(
            new DiAbstractServiceFactory($di, DiAbstractServiceFactory::USE_SM_BEFORE_DI)
        );

        return $di;
    }
}