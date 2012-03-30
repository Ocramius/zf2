<?php

namespace Zend\InstanceManager;

interface InstanceAbstractFactoryInterface
{
    public function createInstance(InstanceManager $instanceManager, $name);
}