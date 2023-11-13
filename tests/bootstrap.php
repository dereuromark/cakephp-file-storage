<?php

declare(strict_types = 1);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Filesystem\Folder;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('PLUGIN_ROOT', dirname(__DIR__));
define('ROOT', PLUGIN_ROOT . DS . 'tests' . DS . 'test_app');
define('TMP', PLUGIN_ROOT . DS . 'tmp' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('APP', ROOT . DS . 'src' . DS);
define('APP_DIR', 'src');
define('CAKE_CORE_INCLUDE_PATH', PLUGIN_ROOT . '/vendor/cakephp/cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . APP_DIR . DS);

require PLUGIN_ROOT . '/vendor/autoload.php';
require CORE_PATH . 'config/bootstrap.php';

$config = [
    'path' => dirname(__FILE__, 2) . DS,
];
Plugin::getCollection()->add(new \FileStorage\Plugin($config));

Configure::write('App', [
    'namespace' => 'TestApp',
    'encoding' => 'UTF-8',
    'imageBaseUrl' => 'img/',
    'paths' => [
        'templates' => [
            PLUGIN_ROOT . DS . 'tests' . DS . 'test_app' . DS . 'templates' . DS,
        ],
    ],
]);

class_alias(TestApp\Application::class, 'App\Application');
class_alias(TestApp\Controller\AppController::class, 'App\Controller\AppController');
class_alias(TestApp\View\AppView::class, 'App\View\AppView');

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

$Tmp = new Folder(TMP);
$Tmp->create(TMP . 'cache/models', 0770);
$Tmp->create(TMP . 'cache/persistent', 0770);
$Tmp->create(TMP . 'cache/views', 0770);

$cache = [
    'default' => [
        'engine' => 'File',
        'path' => CACHE,
    ],
    '_cake_core_' => [
        'className' => 'File',
        'prefix' => 'crud_myapp_cake_core_',
        'path' => CACHE . 'persistent/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
    '_cake_model_' => [
        'className' => 'File',
        'prefix' => 'crud_my_app_cake_model_',
        'path' => CACHE . 'models/',
        'serialize' => 'File',
        'duration' => '+10 seconds',
    ],
];

Cache::setConfig($cache);

// Allow local overwrite
// E.g. in your console: export DB_URL="mysql://root:secret@127.0.0.1/cake_test"
if (getenv('DB_URL')) {
    ConnectionManager::setConfig('test', [
        'url' => getenv('DB_URL'),
        'quoteIdentifiers' => false,
        'cacheMetadata' => true,
    ]);

    return;
}

if (!getenv('DB_URL')) {
    putenv('DB_CLASS=Cake\Database\Driver\Sqlite');
    putenv('DB_URL=sqlite:///:memory:');
}

// Uses Travis config then (MySQL, Postgres, ...)
ConnectionManager::setConfig('test', [
    'className' => 'Cake\Database\Connection',
    'driver' => getenv('DB_CLASS') ?: null,
    'dsn' => getenv('DB_URL') ?: null,
    'timezone' => 'UTC',
    'quoteIdentifiers' => false,
    'cacheMetadata' => true,
]);
