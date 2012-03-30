<?php

namespace Zend\InstanceManager;

class InstanceManager
{
    const SCOPE_PARENT = 'parent';
    const SCOPE_CHILD = 'child';

    /**
     * @var bool
     */
    protected $allowOverride = false;

    /**
     * @var Closure|InstanceFactoryInterface[]
     */
    protected $factories = array();

    /**
     * @var Closure|InstanceFactoryInterface[]
     */
    protected $abstractFactories = array();

    /**
     * @var array
     */
    protected $shared = array();

    /**
     * Registered services and cached values
     *
     * @var array
     */
    protected $instances = array();

    /**
     * @var array
     */
    protected $aliases = array();

    /**
     * @var InstanceManager[]
     */
    protected $peeringInstanceManagers = array();

    /**
     * @var bool Track whether not ot throw exceptions during create()
     */
    protected $createThrowException = true;

    /**
     * @param bool $allowOverride
     */
    public function __construct($allowOverride = false)
    {
        if ($allowOverride) {
            $this->setAllowOverride($allowOverride);
        }
    }

    /**
     * @param $allowOverride
     */
    public function setAllowOverride($allowOverride)
    {
        $this->allowOverride = (bool) $allowOverride;
    }

    /**
     * @param $name
     * @param $factory
     * @throws Exception\DuplicateServiceNameException
     */
    public function setFactory($name, $factory, $shared = true)
    {
        if ($this->allowOverride === false && $this->has($name)) {
            throw new Exception\DuplicateServiceNameException(
                'A service by this name or alias already exists and cannot be overridden, please use an alternate name.'
            );
        }
        $this->factories[$name] = $factory;
        $this->shared[$name] = $shared;
    }

    /**
     * @param $factory
     * @param bool $topOfStack
     */
    public function addAbstractFactory($factory, $topOfStack = true)
    {
        if ($topOfStack) {
            array_unshift($this->abstractFactories, $factory);
        } else {
            array_push($this->abstractFactories, $factory);
        }

    }

    /**
     * Register a service with the locator
     *
     * @param  string $name
     * @param  mixed $service
     * @return InstanceManager
     */
    public function set($name, $service, $shared = true)
    {
        if ($this->allowOverride === false && $this->has($name)) {
            throw new Exception\DuplicateServiceNameException(
                'A service by this name or alias already exists and cannot be overridden, please use an alternate name.'
            );
        }

        /**
         * @todo If a service is being overwritten, destroy all previous aliases
         */

        $this->instances[$name] = $service;
        return $this;
    }

    /**
     * Retrieve a registered service
     *
     * Tests first if a value is registered for the service, and, if so,
     * returns it.
     *
     * If the value returned is a non-object callback or closure, the return
     * value is retrieved, stored, and returned. Parameters passed to the method
     * are passed to the callback, but only on the first retrieval.
     *
     * If the service requested matches a method in the method map, the return
     * value of that method is returned. Parameters are passed to the matching
     * method.
     *
     * @param  string $name
     * @param  array $params
     * @return mixed
     */
    public function get($name)
    {
        $requestedName = $name;

        if ($this->hasAlias($name)) {
            do {
                $name = $this->aliases[$name];
            } while ($this->hasAlias($name));
        }

        $instance = null;

        if (isset($this->instances[$name])) {
            $instance = $this->instances[$name];
        }

        if (!$instance) {
            $instance = $this->create(array($name, $requestedName));
        }

        return $instance;
    }

    /**
     * @param $name
     * @return false|object
     * @throws Exception\InvalidServiceException
     * @throws Exception\InstanceNotCreatedException
     * @throws Exception\InvalidFactoryException
     */
    public function create($name)
    {
        $instance = false;
        $requestedName = null;

        if (is_array($name)) {
            list($name, $requestedName) = $name;
        }

        if (isset($this->factories[$name])) {
            $factory = $this->factories[$name];
            if ($factory instanceof InstanceFactoryInterface) {
                $instance = $this->createInstance(array($factory, 'createInstance'), $name);
            } elseif (is_callable($factory)) {
                $instance = $this->createInstance($factory, $name);
            } else {
                throw new Exception\InvalidFactoryException(sprintf(
                    'While attempting to create %s%s an invalid factory was registered for this instance type.',
                    $name,
                    ($requestedName ? '(alias: ' . $requestedName . ')' : '')
                ));
            }
        }

        if (!$instance && !empty($this->abstractFactories)) {
            foreach ($this->abstractFactories as $abstractFactory) {
                if ($abstractFactory instanceof InstanceFactoryInterface) {
                    $instance = $this->createInstance(array($abstractFactory, 'createInstance'), $name);
                } elseif (is_callable($abstractFactory)) {
                    $instance = $this->createInstance($abstractFactory, $name);
                }
                if (is_object($instance)) {
                    break;
                }
            }
            if (!$instance) {
                throw new Exception\InstanceNotCreatedException(sprintf(
                    'While attempting to create %s%s an abstract factory could not produce a valid instance.',
                    $name,
                    ($requestedName ? '(alias: ' . $requestedName . ')' : '')
                ));
            }
        }

        if (!$instance && $this->peeringInstanceManagers) {
            foreach ($this->peeringInstanceManagers as $peeringInstanceManager) {
                $peeringInstanceManager->createThrowException = false;
                $instance = $peeringInstanceManager->get($requestedName ?: $name);
            }
        }

        if ($this->createThrowException == true && !$instance || !is_object($instance)) {
            throw new Exception\InvalidServiceException(sprintf(
                'No valid instance was found for %s%s',
                $name,
                ($requestedName ? '(alias: ' . $requestedName . ')' : '')
            ));
        }

        // service locator?
        if ($instance instanceof InstanceManagerAwareInterface) {
            /* @var $instance InstanceManagerAwareInterface */
            $instance->setInstanceManager($this);
        }

        return $instance;
    }

    public function has($nameOrAlias)
    {
        return (isset($this->instances[$nameOrAlias]) || isset($this->aliases[$nameOrAlias]));
    }

    public function setAlias($alias, $nameOrAlias)
    {
        if (!is_string($alias) || $alias == '') {
            throw new Exception\InvalidServiceNameException('Invalid service name alias');
        }

        if ($this->hasAlias($alias)) {
            throw new Exception\InvalidServiceNameException('An alias by this name already exists');
        }

        if (!$this->has($nameOrAlias)) {
            throw new Exception\InvalidServiceException('A target service or target alias could not be located');
        }

        $this->aliases[$alias] = $nameOrAlias;
        return $this;
    }

    public function hasAlias($alias)
    {
        return (isset($this->aliases[$alias]));
    }

    public function createScopedInstanceManager($peering = self::SCOPE_PARENT)
    {
        $instanceManager = new InstanceManager();
        // @todo make these flags so both are possible
        if ($peering == self::SCOPE_PARENT) {
            $instanceManager->registerPeerInstanceManager($this);
        }
        if ($peering == self::SCOPE_CHILD) {
            $this->registerPeerInstanceManager($instanceManager);
        }
        return $instanceManager;
    }

    public function registerPeerInstanceManager(InstanceManager $instanceManager)
    {
        $this->peeringInstanceManagers[] = $instanceManager;
    }

    /**
     * @param callable $callable
     * @param string $name
     * @return object
     * @throws \Exception
     */
    protected function createInstance($callable, $name)
    {
        static $circularDependencyResolver = array();

        if (isset($circularDependencyResolver[spl_object_hash($this) . '-' . $name])) {
            $circularDependencyResolver = array();
            throw new Exception\CircularDependencyException('Circular dependency for LazyServiceLoader was found for instance ' . $name);
        }

        $circularDependencyResolver[spl_object_hash($this) . '-' . $name] = true;
        $instance = call_user_func($callable, $this);
        unset($circularDependencyResolver[spl_object_hash($this) . '-' . $name]);

        return $instance;
    }

}