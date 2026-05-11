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
                $routes->connect('/', ['controller' => 'FileStorageDashboard', 'action' => 'index']);
                $routes->connect('/files', ['controller' => 'FileStorage', 'action' => 'index']);
                $routes->connect('/dashboard', ['controller' => 'FileStorageDashboard', 'action' => 'index']);

                $routes->fallbacks();
            });
        });

        // Public signed-download route. Lives outside the Admin prefix because
        // it authorizes via the embedded signature, not via an authenticated
        // session. The signature is captured as a URL segment (not a query
        // string) so it doesn't get scrubbed from same-origin Referer headers
        // or hidden in reverse-proxy access logs that strip the query.
        $routes->plugin('FileStorage', ['path' => '/file-storage'], function (RouteBuilder $routes): void {
            $routes->connect(
                '/signed/{id}/{signature}',
                ['controller' => 'FileStorage', 'action' => 'signed'],
                ['pass' => ['id', 'signature'], 'signature' => '[a-f0-9]{64}'],
            );
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
