<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\Mvc\View\DefaultRenderingStrategy as ViewDefaultRenderingStrategy;

class ViewDefaultRenderingStrategyFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $config   = $serviceManager->get('Configuration');
        $strategy = new ViewDefaultRenderingStrategy($serviceManager->get('View'));
        $layout   = (isset($config->view) && isset($config->view->layout)) ? $config->view->layout : 'layout/layout';
        $strategy->setLayoutTemplate($layout);
        return $strategy;
    }
}
