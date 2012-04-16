<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager;

class ConfigurationFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $mm           = $serviceManager->get('ModuleManager');
        $mm->loadModules();
        $moduleParams = $mm->getEvent()->getParams();
        $config       = $moduleParams['configListener']->getMergedConfig();
        return $config;
    }
}