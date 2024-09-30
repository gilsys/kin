<?php

namespace App;

use App\Constant\UserProfile;
use App\Controller\App\AdminController;
use App\Controller\App\ClientController;
use App\Controller\App\DashboardController;
use App\Controller\App\FileController;
use App\Controller\App\i18nController;
use App\Controller\App\LoginController;
use App\Controller\App\PageController;
use App\Controller\App\PinController;
use App\Controller\App\ProfileController;
use App\Controller\App\ProcessController;
use App\Controller\App\ProcessTypeController;
use App\Controller\App\TaskController;
use App\Controller\App\StaticListController;
use App\Controller\App\UserController;
use App\Middleware\ProfileMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    // Redirect to Swagger documentation
    $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
        return $response->withStatus(302)->withHeader('Location', '/app/public/login');
    });

    $app->get('/app[/]', function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
        return $response->withStatus(302)->withHeader('Location', '/app/public/login');
    });

    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/index', [AdminController::class, 'index']);
    })->add(new ProfileMiddleware())->add('csrf');

    // Dashboard
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/dashboard', [DashboardController::class, 'dashboard']);
    })->add(new ProfileMiddleware())->add('csrf');

    // Login
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/public/login', [LoginController::class, 'form']);
        $app->post('/app/public/login', [LoginController::class, 'login']);
        $app->post('/app/public/forgot_login', [LoginController::class, 'forgotLogin']);
        $app->post('/app/public/forgot_pin', [LoginController::class, 'forgotPin']);
        $app->post('/app/public/forgot_password', [LoginController::class, 'forgotPassword']);
        $app->get('/app/public/enter_password/{token}', [LoginController::class, 'enterPassword']);
        $app->post('/app/public/enter_password', [LoginController::class, 'enterPasswordPost']);
        $app->get('/app/logout', [LoginController::class, 'logout']);
    })->add('csrf');

    // i18n
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/i18n/{lang}/js', [i18nController::class, 'i18n']);
    })->add('csrf');

    // Page
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/page/{page}', [PageController::class, 'page']);
    });

    // File
    $app->group('/app/file', function (RouteCollectorProxy $app) {
        $app->get('/user/avatar/{id}', [FileController::class, 'avatar']);
        $app->get('/{token}', [FileController::class, 'token']);
    })->add(new ProfileMiddleware());

    // Pin
    $app->group('', function (RouteCollectorProxy $app) {
        $app->post('/app/pin_request', [PinController::class, 'send']);
        $app->post('/app/pin_validate', [PinController::class, 'check']);
    })->add(new ProfileMiddleware())->add('csrf');

    // Profile
    $app->group('/app/profile', function (RouteCollectorProxy $app) {
        $app->get('', [ProfileController::class, 'form']);
        $app->post('', [ProfileController::class, 'load']);
        $app->post('/save/{mode}', [ProfileController::class, 'save']);
    })->add(new ProfileMiddleware())->add('csrf');

    // Static List
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/static_lists/{list}', [StaticListController::class, 'list']);
        $app->post('/app/static_list/datatable/{list}', [StaticListController::class, 'datatable']);
        $app->get('/app/static_list/form/{list}[/{id:[0-9]+}]', [StaticListController::class, 'form']);
        $app->post('/app/static_list/delete/{list}', [StaticListController::class, 'delete']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('', function (RouteCollectorProxy $app) {
        $app->post('/app/static_list/{list}/{id}', [StaticListController::class, 'load']);
    })->add(new ProfileMiddleware())->add('csrf');

    $app->group('/app/static_list', function (RouteCollectorProxy $app) {
        $app->post('/save/{list}/{mode}', [StaticListController::class, 'save']);
        $app->post('/order/{list}/{id}/{direction:[0-1]}', [StaticListController::class, 'order']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    // User
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/users', [UserController::class, 'list']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('/app/user', function (RouteCollectorProxy $app) {
        $app->post('/datatable', [UserController::class, 'datatable']);
        $app->get('/form[/{id:[0-9]+}]', [UserController::class, 'form']);
        $app->get('/logs/{id:[0-9]+}', [UserController::class, 'logs']);
        $app->post('/logs_auth/datatable/{id:[0-9]+}', [UserController::class, 'logsAuthDatatable']);
        $app->post('/logs/datatable/{id:[0-9]+}', [UserController::class, 'logsDatatable']);
        $app->post('/delete', [UserController::class, 'delete']);
        $app->post('/save/{mode}', [UserController::class, 'save']);
        $app->post('/status/{id:[0-9]+}/{userStatusId:[A-Z]}', [UserController::class, 'status']);
        $app->get('/email_password/{id:[0-9]+}', [UserController::class, 'emailPassword']);
        $app->post('/update-auth', [UserController::class, 'updateAuth']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('', function (RouteCollectorProxy $app) {
        $app->post('/app/user/{id:[0-9]+}', [UserController::class, 'load']);
        $app->post('/app/user/selector', [UserController::class, 'selector']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('/app/user', function (RouteCollectorProxy $app) {
        $app->get('/avatar/{id:[0-9]+}', [UserController::class, 'avatar']);
        $app->post('/check_nickname[/{id:[0-9]+}]', [UserController::class, 'checkNickname']);
        $app->post('/check_password_current/{id:[0-9]+}', [UserController::class, 'checkCurrentPassword']);
    })->add(new ProfileMiddleware())->add('csrf');

    // Client
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/clients', [ClientController::class, 'list']);
        $app->post('/app/client/datatable', [ClientController::class, 'datatable']);
        $app->get('/app/client/form[/{id:[0-9]+}]', [ClientController::class, 'form']);
        $app->post('/app/client/save/{mode}', [ClientController::class, 'save']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');

    $app->group('', function (RouteCollectorProxy $app) {
        $app->post('/app/client/delete', [ClientController::class, 'delete']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('', function (RouteCollectorProxy $app) {
        $app->post('/app/client/{id:[0-9]+}', [ClientController::class, 'load']);
        $app->post('/app/client/selector', [ClientController::class, 'selector']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');

    // Client processes
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/client/processes/{id:[0-9]+}', [ClientController::class, 'processes']);
        $app->post('/app/client/processes/datatable/{id:[0-9]+}', [ClientController::class, 'processesDatatable']);
        $app->get('/app/clientProcesses/form/{clientId:[0-9]+}', [ProcessController::class, 'form']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');

    // Process
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/processes', [ProcessController::class, 'list']);
        $app->post('/app/process/datatable', [ProcessController::class, 'datatable']);
        $app->post('/app/process/{id:[0-9]+}', [ProcessController::class, 'load']);
        $app->get('/app/process/form[/{id:[0-9]+}[/{clientId:[0-9]+}]]', [ProcessController::class, 'form']);
        $app->post('/app/process/save/{mode}', [ProcessController::class, 'save']);
        $app->post('/app/process/logs/datatable/{id:[0-9]+}', [ProcessController::class, 'logsDatatable']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');

    $app->group('', function (RouteCollectorProxy $app) {
        $app->post('/app/process/delete', [ProcessController::class, 'delete']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    // Process file
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/process/file/{id:[0-9]+}', [ProcessController::class, 'processFile']);
        $app->post('/app/process/upload_file', [ProcessController::class, 'uploadFile']);
        $app->get('/app/process/download_file/{id:[0-9]+}', [ProcessController::class, 'downloadFile']);
        $app->post('/app/process/file/delete', [ProcessController::class, 'deleteFile']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]));

    // Process type
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/process_types', [ProcessTypeController::class, 'list']);
        $app->post('/app/process_type/datatable', [ProcessTypeController::class, 'datatable']);
        $app->post('/app/process_type/{id:[0-9]+}', [ProcessTypeController::class, 'load']);
        $app->post('/app/process_type/delete', [ProcessTypeController::class, 'delete']);
        $app->get('/app/process_type/form[/{id:[0-9]+}]', [ProcessTypeController::class, 'form']);
        $app->post('/app/process_type/save/{mode}', [ProcessTypeController::class, 'save']);
        $app->post('/app/process_type/order/{id}/{direction:[0-1]}', [ProcessTypeController::class, 'order']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');

    // Tasks
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/tasks', [TaskController::class, 'list']);
        $app->post('/app/task/datatable[/{processId:[0-9]+}]', [TaskController::class, 'datatable']);
        $app->post('/app/task/{id:[0-9]+}', [TaskController::class, 'load']);
        $app->post('/app/task/delete', [TaskController::class, 'delete']);
        $app->get('/app/task/form/process/{processId:[0-9]+}', [TaskController::class, 'form']);
        $app->get('/app/task/form[/{id:[0-9]+}[/{processId:[0-9]+}]]', [TaskController::class, 'form']);
        $app->post('/app/task/save/{mode}', [TaskController::class, 'save']);
        $app->post('/app/task/logs/datatable/{id:[0-9]+}', [TaskController::class, 'logsDatatable']);
        $app->post('/app/task/tag_whitelist/{processId:[0-9]+}', [TaskController::class, 'getTagsForTaskWhitelist']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');
};
