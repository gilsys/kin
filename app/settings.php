<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => [
            'debug' => true,
            'displayErrorDetails' => false, // Should be set to false in production
            'logger' => [
                'name' => 'slim-app',
                'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                'level' => Logger::DEBUG,
            ],
            'route_cache' => __DIR__ . '/../var/routes.cache',
            'i18n' => [
                'default_lang' => [
                    'frontend' => 'en',
                    'backoffice' => 'en'
                ],                
                'translations_path' => __DIR__ . '/../src/i18n/',
                'translations_path_app' => __DIR__ . '/../../app/src/lang/'
            ],
            // Renderer settings
            'renderer' => [
                'template_path' => __DIR__ . '/../src/templates/',
            ],
            // Monolog settings            
            'pdo' => [                
                'connection_string' => 'mysql:host=g6server;dbname=kin;charset=utf8;port=3401',
                'user' => 'root',
                'password' => 'kYn2o2AB'
            ],            
            // Custom project settings
            'defines' => [
                'RESOURCES_VERSION' => time(),
                'PASSWORD_SALT' => 'A!o(}_/y',
                'AES_KEY' => '-&K(%jg?4La,[-)N',                
                'IMAGEMAGICK_CMD' => 'convert'
            ]
        ],
    ]);
};
