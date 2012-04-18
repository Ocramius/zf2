<?php

namespace Zend\ServiceLocator\Di;


use Zend\ServiceLocator\LazyServiceInterface,
Zend\ServiceLocator\ServiceLocatorInterface,
Zend\ServiceLocator\Exception,
Zend\Di\Di,
Zend\Di\Exception\ClassNotFoundException as DiClassNotFoundException;

class DiService extends Di implements LazyServiceInterface
{
    const USE_SL_BEFORE_DI = 'before';
    const USE_SL_AFTER_DI  = 'after';
    const USE_SL_NONE      = 'none';

    protected $di = null;
    protected $diInstanceName = null;
    protected $diInstanceParameters = array();
    protected $useServiceLocator = self::USE_SL_AFTER_DI;

    /**
     * @var \Zend\ServiceLocator\ServiceLocatorInterface
     */
    protected $serviceLocator = null;


    public function __construct(Di $di, $diInstanceName, array $diInstanceParameters = array(), $useServiceLocator = self::USE_SL_NONE)
    {
        $this->di = $di;
        $this->diInstanceName = $diInstanceName;
        $this->diInstanceParameters = $diInstanceParameters;
        if (in_array($useServiceLocator, array(self::USE_SL_BEFORE_DI, self::USE_SL_AFTER_DI, self::USE_SL_NONE))) {
            $this->useServiceLocator = $useServiceLocator;
        }

        // since we are using this in a proxy-fashion, localize state
        $this->definitions = $this->di->definitions;
        $this->instanceManager = $this->di->instanceManager;
    }


    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this->get($this->diInstanceName, $this->diInstanceParameters, true);
    }

    /**
     * Override, as we want it to use the functionality defined in the proxy
     *
     * @param  string $name
     * @param  array $params
     * @return GeneratorInstance
     */
    public function get($name, array $params = array(), $calledFromDiService = false)
    {
        // allow this di service to get dependencies from the service locator BEFORE trying di
        if (!$calledFromDiService && $this->useServiceLocator == self::USE_SL_BEFORE_DI && $this->serviceLocator->has($name)) {
            return $this->serviceLocator->get($name);
        }

        // copied from DI::get(), get shared instance when I can
        if ($params) {
            if (($fastHash = $this->instanceManager->hasSharedInstanceWithParameters($name, $params, true))) {
                return $this->instanceManager->getSharedInstanceWithParameters(null, array(), $fastHash);
            }
        } else {
            if ($this->instanceManager->hasSharedInstance($name, $params)) {
                return $this->instanceManager->getSharedInstance($name, $params);
            }
        }
        // end copy/paste

        try {

            // get dependencies from di itself
            $instance = $this->newInstance($name, $params);
            return $instance;

        } catch (DiClassNotFoundException $e) {

            // allow this di service to get dependencies from the service locator AFTER trying di
            if (!$calledFromDiService && $this->useServiceLocator == self::USE_SL_AFTER_DI && $this->serviceLocator->has($name)) {
                return $this->serviceLocator->get($name);
            } else {
                throw new Exception\InvalidServiceException('Service was not found in this DI instance', null, $e);
            }
        }
    }

}