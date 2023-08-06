<?php

declare(strict_types = 1);

namespace FileStorage;

use Cake\Core\BasePlugin;
use Cake\Routing\RouteBuilder;

/**
 * FileStorage Plugin for CakePHP
 */
class FileStoragePlugin extends BasePlugin
{
    /**
     * Do bootstrapping or not
     *
     * @var bool
     */
    protected $bootstrapEnabled = true;

    /**
     * @var bool
     */
    protected $middlewareEnabled = false;

    /**
     * Register container services
     *
     * @var bool
     */
    protected $servicesEnabled = false;

    /**
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     *
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->prefix('Admin', function (RouteBuilder $routes) {
            $routes->plugin('FileStorage', ['path' => '/file-storage'], function (RouteBuilder $routes) {
                $routes->connect('/', ['controller' => 'FileStorage', 'action' => 'index']);

                $routes->fallbacks();
            });
        });
    }
}
