<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\EventManager\SharedEventCollection,
    Zend\EventManager\SharedEventManager,
    Zend\EventManager\EventManager;

class EventManagerFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        static $sharedEventManager = null;
        if (!$sharedEventManager) {
            $sharedEventManager = new SharedEventManager();
        }
        $em = new EventManager();
        $em->setSharedCollections($sharedEventManager);
        return $em;
    }
}