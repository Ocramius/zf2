<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\EventManager\SharedEventCollection,
    Zend\EventManager\SharedEventManager,
    Zend\EventManager\EventManager;

class EventManagerFactory implements FactoryInterface
{
    protected $sharedEventManager = null;

    public function __construct(SharedEventCollection $sharedEventCollection = null)
    {
        $this->sharedEventManager = ($sharedEventCollection) ?: new SharedEventManager;
    }

    public function createService(ServiceManager $serviceManager)
    {
        $em = new EventManager();
        $em->setSharedCollections($this->sharedEventManager);
        return $em;
    }
}