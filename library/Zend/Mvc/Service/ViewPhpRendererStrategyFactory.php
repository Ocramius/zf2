<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\View\Strategy\PhpRendererStrategy;

class ViewPhpRendererStrategyFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $renderer = $serviceManager->get('ViewPhpRenderer');
        return new PhpRendererStrategy($renderer);
    }
}
