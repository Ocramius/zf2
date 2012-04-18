<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\Module\Listener\ListenerOptions,
    Zend\Module\Listener\DefaultListenerAggregate,
    Zend\Module\Manager as ModuleManager;

class ModuleManagerFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $configuration = $serviceManager->get('ApplicationConfiguration');
        $listenerOptions  = new ListenerOptions($configuration['module_listener_options']);
        $defaultListeners = new DefaultListenerAggregate($listenerOptions);
        $defaultListeners->getConfigListener()->addConfigGlobPath("config/autoload/{,*.}{global,local}.config.php");

        $moduleManager = new ModuleManager($configuration['modules'], $serviceManager->get('EventManager'));
        $moduleManager->events()->attachAggregate($defaultListeners);
        return $moduleManager;
    }
}