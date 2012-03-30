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
    public function __construct(ConfigurationInterface $configuration = null)
    {
        if ($configuration) {
            $configuration->configureInstanceManager($this);
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
        $name = $this->canonicalizeName($name);

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
        return $this;
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
        $name = $this->canonicalizeName($name);

        if ($this->allowOverride === false && $this->has($name)) {
            throw new Exception\DuplicateServiceNameException(
                'A service by this name or alias already exists and cannot be overridden, please use an alternate name.'
            );
        }

        /**
         * @todo If a service is being overwritten, destroy all previous aliases
         */

        $this->instances[$name] = $service;
        $this->shared[$name] = (bool) $shared;
        return $this;
    }

    public function setShared($name, $isShared)
    {
        $name = $this->canonicalizeName($name);

        if (!isset($this->factories[$name])) {
            throw new Exception\InstanceNotFoundException();
        }

        $this->shared[$name] = (bool) $isShared;
        return $this;
    }

    /**
     * Retrieve a registered instance
     *
     * @param  string $name
     * @param  array $params
     * @return mixed
     */
    public function get($name)
    {
        $name = $this->canonicalizeName($name);

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

        if (isset($this->shared[$name]) && $this->shared[$name] === true) {
            $this->instances[$name] = $instance;
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

        $name = $this->canonicalizeName($name);

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
        $nameOrAlias = $this->canonicalizeName($nameOrAlias);

        return (isset($this->factories[$nameOrAlias]) || isset($this->aliases[$nameOrAlias]) || isset($this->instances[$nameOrAlias]));
    }

    public function setAlias($alias, $nameOrAlias)
    {
        $alias = $this->canonicalizeName($alias);
        $nameOrAlias = $this->canonicalizeName($nameOrAlias);

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
        $alias = $this->canonicalizeName($alias);
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

    protected function canonicalizeName($name)
    {
        return strtolower(str_replace(array('-', '_', ' '), '', $name));
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
        $instance = call_user_func($callable, $this, $name);
        if ($instance === null) {
            throw new Exception\InstanceNotCreatedException('The factory was called but did not return an instance.');
        }
        unset($circularDependencyResolver[spl_object_hash($this) . '-' . $name]);

        return $instance;
    }

}