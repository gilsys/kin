<?php

declare(strict_types=1);

use App\Util\ProjectErrorRenderer;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

// Set up settings
$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);
// Set up dependencies
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();
$settings = $container->get('settings');

foreach ($settings['defines'] as $key => $value) {
    define($key, $value);
}

if (!defined('ENCRYPT_IMG_PASSWORD')) {
    define('ENCRYPT_IMG_PASSWORD', '-');
}

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();
$callableResolver = $app->getCallableResolver();

// Register middleware
$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);
// Set the cache file for the routes. Note that you have to delete this file whenever you change the routes.
$cacheRoutesFile = $settings['route_cache'];
$settingsFile = __DIR__ . '/../app/settings.php';

if (!$settings['debug'] && (!file_exists($cacheRoutesFile) || (filemtime($cacheRoutesFile) > filemtime($settingsFile)))) {
    $app->getRouteCollector()->setCacheFile($cacheRoutesFile);
} else if (file_exists($cacheRoutesFile)) {
    unlink($cacheRoutesFile);
}

$displayErrorDetails = $container->get('settings')['displayErrorDetails'];

if (!$displayErrorDetails) {
    error_reporting(0);
}

// Add Routing Middleware
$app->addRoutingMiddleware();

// Special function to quick translate using i18n
$i18n = $container->get('i18n');

function __($param, $args = null) {
    global $i18n;
    return $i18n->translate($param, $args);
}

function __ss($text, $args = null) {
    $translation = __($text, $args);
    __s($translation, true, true);    
}

function __s($text, $echo = true, $addslashes = false) {
    if (empty($text)) {
        return '';
    }
    $out = htmlspecialchars($text);

    if ($addslashes) {
        $out = addslashes($out);
    }

    if ($echo) {
        echo $out;
    }
    return $out;
}

function __sp($text) {
    echo str_replace(array("\n", "\r\n"), ". ", __s(trim($text), false));
}

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, !$settings['debug']);
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->registerErrorRenderer('text/html', ProjectErrorRenderer::class);
$app->run();
