<?php

namespace Zend\Module\Consumer;

interface ServiceProviderInterface
{
    /**
     * @abstract
     * @return \Zend\ServiceManager\Configuration
     */
    public function getServiceConfiguration();
}