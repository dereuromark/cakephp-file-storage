<?php

declare(strict_types = 1);

namespace TestApp;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;

class Application extends BaseApplication
{
 /**
  * @inheritDoc
  */
    public function middleware(MiddlewareQueue $middleware): MiddlewareQueue
    {
        $middleware->add(new RoutingMiddleware($this));

        return $middleware;
    }
}
