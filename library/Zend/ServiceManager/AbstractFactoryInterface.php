<?php

namespace Zend\ServiceManager;

interface AbstractFactoryInterface
{
    public function createService(ServiceManager $instanceManager, $name);
}