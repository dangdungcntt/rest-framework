<?php

namespace Rest;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

class Router
{
    protected Dispatcher $dispatcher;

    public function __construct()
    {
        $this->dispatcher = simpleDispatcher(function (RouteCollector $routes) {
            $this->register($routes);
        });
    }

    protected function register(RouteCollector $routes): void
    {
        //Default router
    }

    public function dispatch($httpMethod, $uri): array
    {
        return $this->dispatcher->dispatch($httpMethod, $uri);
    }
}
