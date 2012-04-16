<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\View\View;

class ViewFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $view = new View;
        $view->setEventManager($serviceManager->get('EventManager'));
        $view->events()->attachAggregate($serviceManager->get('ViewPhpRendererStrategy'));
        return $view;
    }
}
