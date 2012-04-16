<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\View\HelperLoader as ViewHelperLoader;

class ViewHelperLoaderFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $config = $serviceManager->get('Configuration');
        if (isset($config->view) && isset($config->view->helper_map)) {
            $map = $config->view->helper_map;
        } else {
            $map = array();
        }
        return new ViewHelperLoader($map);
    }
}
