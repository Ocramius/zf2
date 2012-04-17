<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\ConfigurationInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\ServiceManager\ServiceManagerAwareInterface;

class DefaultServiceConfiguration implements ConfigurationInterface
{
    protected $services = array(
        'Request'           => 'Zend\Http\PhpEnvironment\Request',
        'Response'          => 'Zend\Http\PhpEnvironment\Response',
        'RouteListener'     => 'Zend\Mvc\RouteListener',
        'DispatchListener'  => 'Zend\Mvc\DispatchListener'
    );

    protected $factories = array(
        'EventManager'           => 'Zend\Mvc\Service\EventManagerFactory',
        'ModuleManager'          => 'Zend\Mvc\Service\ModuleManagerFactory',
        'Configuration'          => 'Zend\Mvc\Service\ConfigurationFactory',
        'Router'                 => 'Zend\Mvc\Service\RouterFactory',
        'ControllerPluginLoader' => 'Zend\Mvc\Service\ControllerPluginLoaderFactory',
        'ControllerPluginBroker' => 'Zend\Mvc\Service\ControllerPluginBrokerFactory',
        'Application'            => 'Zend\Mvc\Service\ApplicationFactory',

        // view related stuffs
        'View'                         => 'Zend\Mvc\Service\ViewFactory',
        'ViewAggregateResolver'        => 'Zend\Mvc\Service\ViewAggregateResolverFactory',
        'ViewDefaultRenderingStrategy' => 'Zend\Mvc\Service\ViewDefaultRenderingStrategyFactory',
        'ViewExceptionStrategy'        => 'Zend\Mvc\Service\ViewExceptionStrategyFactory',
        'ViewHelperLoader'             => 'Zend\Mvc\Service\ViewHelperLoaderFactory',
        'ViewPhpRenderer'              => 'Zend\Mvc\Service\ViewPhpRendererFactory',
        'ViewPhpRendererStrategy'      => 'Zend\Mvc\Service\ViewPhpRendererStrategyFactory',
        'ViewRouteNotFoundStrategy'    => 'Zend\Mvc\Service\ViewRouteNotFoundStrategyFactory',
        'ViewTemplateMapResolver'      => 'Zend\Mvc\Service\ViewTemplateMapResolverFactory',
        'ViewTemplatePathStack'        => 'Zend\Mvc\Service\ViewTemplatePathStackFactory',
    );

    protected $abstractFactories = array(
        'Zend\Mvc\Service\ControllerLoaderFactory',
    );

    protected $aliases = array(
        'EM'     => 'EventManager',
        'MM'     => 'ModuleManager',
        'Config' => 'Configuration',
    );

    protected $shared = array(
        'EventManager' => false
    );

    public function configureServiceManager(ServiceManager $serviceManager)
    {
        foreach ($this->services as $name => $service) {
            $serviceManager->set($name, new $service);
        }

        foreach ($this->factories as $name => $factoryClass) {
            $serviceManager->setSource($name, new $factoryClass);
        }

        foreach ($this->abstractFactories as $factoryClass) {
            $serviceManager->addAbstractSource(new $factoryClass);
        }

        foreach ($this->aliases as $name => $service) {
            $serviceManager->setAlias($name, $service);
        }

        foreach ($this->shared as $name => $value) {
            $serviceManager->setShared($name, $value);
        }

        $serviceManager->addInitializer(function ($instance) use ($serviceManager) {
            if ($instance instanceof ServiceManagerAwareInterface) {
                $instance->setServiceManager($instance);
            }
        });
    }

}