<?php

namespace Zend\Mvc;

use Zend\InstanceManager\InstanceManager,
    Zend\EventManager\EventCollection,
    Zend\Stdlib\RequestDescription as Request,
    Zend\Stdlib\ResponseDescription as Response;

interface ApplicationInterface
{


    /**
     * Get the locator object
     * 
     * @return InstanceManager
     */
    public function getInstanceManager();

    /**
     * Get the request object
     * 
     * @return Request
     */
    public function getRequest();

    /**
     * Get the response object
     * 
     * @return Response
     */
    public function getResponse();

    /**
     * Get the router object
     * 
     * @return Router
     */
    public function getRouter();

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     * 
     * @return EventCollection
     */
    public function events();

    /**
     * Run the application
     * 
     * @return \Zend\Http\Response
     */
    public function run();
}
