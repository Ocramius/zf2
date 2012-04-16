<?php

namespace Zend\Mvc\Service;

use Zend\ServiceManager\FactoryInterface,
    Zend\ServiceManager\ServiceManager,
    Zend\View\Resolver\AggregateResolver as ViewAggregateResolver;

class ViewAggregateResolverFactory implements FactoryInterface
{
    public function createService(ServiceManager $serviceManager)
    {
        $map   = $serviceManager->get('ViewTemplateMapResolver');
        $stack = $serviceManager->get('ViewTemplatePathStack');
        $aggregate = new ViewAggregateResolver();
        $aggregate->attach($map);
        $aggregate->attach($stack);
        return $aggregate;
    }
}
