<?php

namespace Zend\Module\Listener;

use Zend\Loader\AutoloaderFactory,
    Zend\Module\Consumer\ServiceProviderInterface,
    Zend\Module\ModuleEvent;

class ServiceListener extends AbstractListener
{
    public function __invoke(ModuleEvent $e)
    {
        $module = $e->getModule();
        if (!$module instanceof ServiceProviderInterface) {
            return;
        }
        /** @var $serviceManager \Zend\ServiceManager\Configuration */
        $serviceConfiguration = $module->getServiceConfiguration();
        /** @var $serviceManager \Zend\ServiceManager\ServiceManager */
        $serviceManager = $e->getParam('ServiceManager');
        $serviceConfiguration->configureServiceManager($serviceManager);
    }
}
