<?php

declare(strict_types=1);

use App\Service\SecurityService;
use App\Service\TemplateDataService;
use App\Util\CustomEngine;
use App\Util\i18n;
use App\Util\JsonManager;
use App\Util\ParamsLoader;
use DI\ContainerBuilder;
use MongoDB\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Slim\Flash\Messages;
use Slim\HttpCache\CacheProvider;
use SlimSession\Helper;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        'logger' => function (ContainerInterface $c) {
            $settings = $c->get('settings');

            $loggerSettings = $settings['logger'];
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },
        // view renderer
        'renderer' => function (ContainerInterface $c) {
            $containerSettings = $c->get('settings');
            $settings = $containerSettings['renderer'];

            $settings['template_path'] .= 'app/';

            $globalVars = [
                'debug' => $containerSettings['debug'],
                'templatesPath' => $settings['template_path']
            ];

            // Skip cache in rendered pages (history back)
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0 ");

            // Assign CSRF vars to view
            try {
                $csrfNameKey = $c->get('csrf')->getTokenNameKey();
                $csrfValueKey = $c->get('csrf')->getTokenValueKey();
                $csrfName = $c->get('csrf')->getTokenName();
                $csrfValue = $c->get('csrf')->getTokenValue();
                $globalVars['params'] = $c->get('params')->getAll();

                $templateDataService = new TemplateDataService($c->get('pdo'), $c->get('session'), $c->get('params'));
                $globalVars['common'] = $templateDataService->fetchCommonData();
                $globalVars['csrf'] = [
                    'keys' => [
                        'name' => $csrfNameKey,
                        'value' => $csrfValueKey
                    ],
                    'name' => $csrfName,
                    'value' => $csrfValue
                ];
            } catch (Exception $e) {
                
            }

            $plates = new CustomEngine($settings['template_path']);
            $plates->addData($globalVars);
            return $plates;
            //return new PhpRenderer($settings['template_path'], $globalVars);
        },
        'pdo' => function (ContainerInterface $c) {
            $settings = $c->get('settings')['pdo'];
            $connectionDB = $settings['connection_string'];
            $pdo = new PDO($connectionDB, $settings['user'], $settings['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        },        
        'jsonManager' => function (ContainerInterface $c) {
            $jsonPath = $c->get('settings')['json_path'];
            return new JsonManager($jsonPath);
        },
        'cache' => function () {
            return new CacheProvider();
        },
        // Register globally to app
        'session' => function () {
            return new Helper;
        },
        // flash
        'flash' => function () {
            return new Messages();
        },
        'params' => function (ContainerInterface $c) {
            return new ParamsLoader($c->get('pdo'));
        },
        'i18n' => function (ContainerInterface $c) {
            $settings = $c->get('settings')['i18n'];
            $environemnt = (isset($_SERVER['REQUEST_URI']) && preg_match("/\/app/", $_SERVER['REQUEST_URI'])) ? 'backoffice' : 'frontend';
            return new i18n($settings['translations_path'], $settings['default_lang'][$environemnt], $environemnt, $settings['translations_path_app']);
        },
        'security' => function (ContainerInterface $c) {
            return new SecurityService($c->get('pdo'), $c->get('session'));
        },
    ]);
};
