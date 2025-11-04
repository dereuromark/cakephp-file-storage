<?php

declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

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
define('CONFIG', ROOT . DS . 'config' . DS);
define('TESTS', __DIR__ . DS);
if (!defined('WWW_ROOT')) {
    define('WWW_ROOT', ROOT . DS . 'webroot' . DS);
}

require PLUGIN_ROOT . '/vendor/autoload.php';
require CORE_PATH . 'config/bootstrap.php';
require CAKE_CORE_INCLUDE_PATH . '/src/functions.php';

$config = [
    'path' => dirname(__FILE__, 2) . DS,
];

Configure::write('debug', true);

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

//$Tmp = new Folder(TMP);
//$Tmp->create(TMP . 'cache/models', 0770);
//$Tmp->create(TMP . 'cache/persistent', 0770);
//$Tmp->create(TMP . 'cache/views', 0770);

$cache = [
    'default' => [
        'engine' => 'File',
        'path' => CACHE,
    ],
    '_cake_translations_' => [
        'className' => 'File',
        'prefix' => 'myapp_cake_translations_',
        'path' => CACHE . 'persistent/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
    '_cake_model_' => [
        'className' => 'File',
        'prefix' => 'myapp_cake_model_',
        'path' => CACHE . 'models/',
        'serialize' => 'File',
        'duration' => '+10 seconds',
    ],
];

Cache::setConfig($cache);

if (!getenv('DB_URL')) {
    putenv('DB_URL=sqlite:///:memory:');
}

ConnectionManager::setConfig('test', [
    'url' => getenv('DB_URL') ?: null,
    'timezone' => 'UTC',
    'quoteIdentifiers' => false,
    'cacheMetadata' => true,
]);

Chronos::setTestNow(Chronos::now());

if (env('FIXTURE_SCHEMA_METADATA')) {
    $loader = new Cake\TestSuite\Fixture\SchemaLoader();
    $loader->loadInternalFile(env('FIXTURE_SCHEMA_METADATA'));
}
Configure::load('example');
