<?php declare(strict_types=1);

namespace FileStorage;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Routing\RouteBuilder;
use FileStorage\Command\CleanupCommand;
use FileStorage\Command\ImageVariantGenerateCommand;

/**
 * FileStorage Plugin for CakePHP
 */
class FileStoragePlugin extends BasePlugin
{
    /**
     * @var bool
     */
    protected bool $bootstrapEnabled = true;

    /**
     * @var bool
     */
    protected bool $middlewareEnabled = false;

    /**
     * Register container services
     *
     * @var bool
     */
    protected bool $servicesEnabled = false;

    /**
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     *
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->prefix('Admin', function (RouteBuilder $routes): void {
            $routes->plugin('FileStorage', ['path' => '/file-storage'], function (RouteBuilder $routes): void {
                $routes->connect('/', ['controller' => 'FileStorage', 'action' => 'index']);

                $routes->fallbacks();
            });
        });
    }

    /**
     * Add commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update.
     *
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('file_storage cleanup', CleanupCommand::class);
        $commands->add('file_storage generate_image_variant', ImageVariantGenerateCommand::class);

        return $commands;
    }
}
