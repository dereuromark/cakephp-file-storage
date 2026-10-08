<?php declare(strict_types=1);

namespace TestApp;

use Cake\Core\Configure;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;

/**
 * Routed upload test application.
 *
 * @author Mark Scherer
 * @license MIT
 */
class UploadsApplication extends Application
{
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin('FileStorage', ['path' => '/file-storage'], function (RouteBuilder $routes): void {
            $routes->connect('/uploads', ['controller' => 'Uploads', 'action' => 'collection']);
            $routes->connect('/uploads/{id}', ['controller' => 'Uploads', 'action' => 'resource'], ['pass' => ['id']]);
        });
    }

    public function middleware(MiddlewareQueue $middleware): MiddlewareQueue
    {
        $middleware = parent::middleware($middleware);
        if (Configure::read('Test.uploadCsrf')) {
            $middleware->add(new CsrfProtectionMiddleware());
        }

        return $middleware;
    }
}
