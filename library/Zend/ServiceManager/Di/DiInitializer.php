<?php

namespace Zend\ServiceManager\Di;


use Zend\ServiceManager\InitializerInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\ServiceManager\Exception,
    Zend\Di\Di,
    Zend\Di\Exception\ClassNotFoundException as DiClassNotFoundException;

class DiInitializer extends Di implements InitializerInterface
{

    protected $di = null;
    protected $diInstanceManagerProxy = null;
    protected $serviceManager = null;

    public function __construct(Di $di, ServiceManager $serviceManager)
    {
        $this->di = $di;
        $this->serviceManager = $serviceManager;

        // since we are using this in a proxy-fashion, localize state
        $this->definitions = $this->di->definitions;
        $this->diInstanceManagerProxy = new DiInstanceManagerProxy($di->instanceManager(), $serviceManager);
    }

    public function initialize($instance)
    {
        $instanceManager = $this->di->instanceManager;
        $this->di->instanceManager = $this->diInstanceManagerProxy;
        try {
            $this->di->injectDependencies($instance);
            $this->di->instanceManager = $instanceManager;
        } catch (Exception $e) {
            $this->di->instanceManager = $instanceManager;
            throw $e;
        }
    }
}