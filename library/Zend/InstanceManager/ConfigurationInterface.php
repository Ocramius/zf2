<?php

namespace Zend\InstanceManager;

interface ConfigurationInterface
{
    public function configureInstanceManager(InstanceManager $instanceManager);
}