<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\View\HelperBroker as ViewHelperBroker,
    Zend\View\Renderer\PhpRenderer as ViewPhpRenderer;

class ViewPhpRendererFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $resolver     = $serviceManager->get('ViewAggregateResolver');
        $helperLoader = $serviceManager->get('ViewHelperLoader');

        $config = $serviceManager->get('Configuration');

        $broker       = new ViewHelperBroker();
        $broker->setClassLoader($helperLoader);

        $url          = $broker->load('url');
        $url->setRouter($serviceManager->get('Router'));
        $basePath     = $broker->load('basePath');

        // set base path
        if (isset($config->view) && isset($config->view->base_path)) {
            $basePath->setBasePath($config->view->base_path);
        } else {
            $basePath->setBasePath('/');
        }

        $renderer     = new ViewPhpRenderer();
        $renderer->setBroker($broker);
        $renderer->setResolver($resolver);
        return $renderer;
    }
}
