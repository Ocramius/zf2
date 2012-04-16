<?php

namespace Zend\ServiceManager;

class Configuration implements ConfigurationInterface
{
    protected $config = null;

    public function __construct($config = null)
    {
        $this->config = $config;
    }

    public function getFactories()
    {
        return array();
    }

    public function getAbstractFactories()
    {
        return array();
    }

    public function getServices()
    {
        return array();
    }

    public function getAliases()
    {
        return array();
    }

    public function getShared()
    {
        return array();
    }

    public function configureServiceManager(ServiceManager $instanceManager)
    {
        foreach ($this->getFactories() as $name => $factory) {
            $instanceManager->setFactory($name, $factory);
        }

        foreach ($this->getAliases() as $alias => $nameOrAlias) {
            $instanceManager->setAlias($alias, $nameOrAlias);
        }

        foreach ($this->getShared() as $name => $isShared) {
            $instanceManager->setShared($name, $isShared);
        }
    }

}