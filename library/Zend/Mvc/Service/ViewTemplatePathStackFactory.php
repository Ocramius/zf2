<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\View\Resolver\TemplatePathStack as ViewTemplatePathStack;

class ViewTemplatePathStackFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $config = $serviceManager->get('Configuration');
        if (isset($config->view) && isset($config->view->template_path_stack)) {
            $stack = $config->view->template_path_stack;
        } else {
            $stack = array();
        }
        return new ViewTemplatePathStack($stack);
    }
}
