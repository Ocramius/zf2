<?php

namespace Zend\InstanceManager;

interface InstanceManagerAwareInterface
{
    public function setInstanceManager(InstanceManager $instanceManager);
}