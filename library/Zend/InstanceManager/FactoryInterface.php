<?php

namespace Zend\InstanceManager;

interface InstanceFactoryInterface
{
    public function createInstance(InstanceManager $instanceManager);
}