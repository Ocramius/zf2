<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\ServiceManager\TestAsset;

use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class used to try to simulate a cyclic dependency in ServiceManager.
 */
class CircularDependencyAbstractFactory implements AbstractFactoryInterface
{
    public $expectedInstance = 'a retrieved value';

    /**
     * {@inheritDoc}
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
     * @param $name
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
     * @param $name
     * @return array|mixed|object|string
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name)
    {
        if ($serviceLocator->has($name)) {
            return $serviceLocator->get($name);
        }

        return $this->expectedInstance;
    }
}
