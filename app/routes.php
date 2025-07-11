<?php

namespace App;

use App\Constant\UserProfile;
use App\Controller\App\AdminController;
use App\Controller\App\DashboardController;
use App\Controller\App\FileController;
use App\Controller\App\i18nController;
use App\Controller\App\LoginController;
use App\Controller\App\MarketController;
use App\Controller\App\ProductController;
use App\Controller\App\SubProductController;
use App\Controller\App\BookletController;
use App\Controller\App\CustomProductController;
use App\Controller\App\PageController;
use App\Controller\App\ProfileController;
use App\Controller\App\RecipeController;
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
    })->add(new ProfileMiddleware([UserProfile::Administrator]));

    $app->group('/app/file', function (RouteCollectorProxy $app) {
        $app->post('/upload_file', [FileController::class, 'uploadFile']);
        $app->get('/get/{name}', [FileController::class, 'getUploadedFile']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]));

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
        $app->post('/app/static_list/{list}/{id}', [StaticListController::class, 'load']);
        $app->post('/app/static_list/save/{list}/{mode}', [StaticListController::class, 'save']);
        $app->post('/app/static_list/order/{list}/{id}/{direction:[0-1]}', [StaticListController::class, 'order']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    // User
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/users', [UserController::class, 'list']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('/app/user', function (RouteCollectorProxy $app) {
        $app->post('/datatable', [UserController::class, 'datatable']);
        $app->get('/form[/{id:[0-9]+}]', [UserController::class, 'form']);
        /* $app->get('/logs/{id:[0-9]+}', [UserController::class, 'logs']);
        $app->post('/logs_auth/datatable/{id:[0-9]+}', [UserController::class, 'logsAuthDatatable']);
        $app->post('/logs/datatable/{id:[0-9]+}', [UserController::class, 'logsDatatable']); */
        $app->post('/delete', [UserController::class, 'delete']);
        $app->post('/save/{mode}', [UserController::class, 'save']);
        $app->post('/status/{id:[0-9]+}/{userStatusId:[A-Z]}', [UserController::class, 'status']);
        $app->get('/email_password/{id:[0-9]+}', [UserController::class, 'emailPassword']);
        $app->post('/update-auth', [UserController::class, 'updateAuth']);
        $app->post('/{id:[0-9]+}', [UserController::class, 'load']);
        $app->post('/selector', [UserController::class, 'selector']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('/app/user', function (RouteCollectorProxy $app) {
        $app->get('/avatar/{id:[0-9]+}', [UserController::class, 'avatar']);
        $app->post('/check_nickname[/{id}]', [UserController::class, 'checkNickname']);
        $app->post('/check_password_current', [UserController::class, 'checkCurrentPassword']);
    })->add(new ProfileMiddleware())->add('csrf');

    // Markets
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/markets', [MarketController::class, 'list']);
        $app->post('/app/market/datatable', [MarketController::class, 'datatable']);
        $app->get('/app/market/form[/{id:[0-9]+}]', [MarketController::class, 'form']);
        $app->post('/app/market/save/{mode}', [MarketController::class, 'save']);
        $app->post('/app/market/delete', [MarketController::class, 'delete']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('', function (RouteCollectorProxy $app) {
        $app->post('/app/market/{id:[0-9]+}', [MarketController::class, 'load']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');

    // Products
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/products', [ProductController::class, 'list']);
        $app->post('/app/product/datatable', [ProductController::class, 'datatable']);
        $app->get('/app/product/form[/{id:[0-9]+}]', [ProductController::class, 'form']);
        $app->post('/app/product/save/{mode}', [ProductController::class, 'save']);
        $app->post('/app/product/delete', [ProductController::class, 'delete']);
        $app->post('/app/product/restore', [ProductController::class, 'restore']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    $app->group('', function (RouteCollectorProxy $app) {
        $app->post('/app/product/{id:[0-9]+}', [ProductController::class, 'load']);
    })->add(new ProfileMiddleware())->add('csrf');

    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/image/{field}/{id:[0-9]+}', [ProductController::class, 'image']);
    })->add(new ProfileMiddleware())->add('csrf');

    // Custom products
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/custom_products', [CustomProductController::class, 'list']);
        $app->post('/app/custom_product/datatable', [CustomProductController::class, 'datatable']);
        $app->get('/app/custom_product/select', [CustomProductController::class, 'listSelect']);
        $app->post('/app/custom_product/datatable/select', [CustomProductController::class, 'datatableSelect']);
        $app->post('/app/custom_product/{id:[0-9]+}', [CustomProductController::class, 'load']);
        $app->get('/app/custom_product/form/{parentProductId:[0-9]+}[/{id:[0-9]+}]', [CustomProductController::class, 'form']);
        $app->post('/app/custom_product/save/{mode}', [CustomProductController::class, 'save']);
        $app->post('/app/custom_product/delete', [CustomProductController::class, 'delete']);
        $app->post('/app/custom_product/restore', [CustomProductController::class, 'restore']);
    })->add(new ProfileMiddleware([UserProfile::User]))->add('csrf');

    // Subproducts
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/subproducts', [SubProductController::class, 'list']);
        $app->post('/app/subproduct/datatable', [SubProductController::class, 'datatable']);
        $app->post('/app/subproduct/{id:[0-9]+}', [SubProductController::class, 'load']);
        $app->get('/app/subproduct/form[/{id:[0-9]+}]', [SubProductController::class, 'form']);
        $app->post('/app/subproduct/save/{mode}', [SubProductController::class, 'save']);
        $app->post('/app/subproduct/delete', [SubProductController::class, 'delete']);
        $app->post('/app/subproduct/restore', [SubProductController::class, 'restore']);
    })->add(new ProfileMiddleware([UserProfile::Administrator]))->add('csrf');

    // Booklets
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/booklets[/{type:[A-Z]}]', [BookletController::class, 'list']);
        $app->post('/app/booklet/datatable/{type:[A-Z]}', [BookletController::class, 'datatable']);
        $app->post('/app/booklet/{id:[0-9]+}', [BookletController::class, 'load']);
        $app->get('/app/booklet/form/{type:[A-Z]}', [BookletController::class, 'form']);
        $app->get('/app/booklet/form/{id:[0-9]+}[/{mode:[A-Z]+}]', [BookletController::class, 'form']);
        $app->post('/app/booklet/save/{mode}', [BookletController::class, 'save']);
        $app->post('/app/booklet/delete', [BookletController::class, 'delete']);
        $app->post('/app/booklet/get_products', [BookletController::class, 'getProducts']);
        $app->get('/app/booklet/pdf/{id:[0-9]+}', [BookletController::class, 'pdfPreview']);
        $app->get('/app/booklet/pdf/file/{id:[0-9]+}', [BookletController::class, 'pdfFile']);
        $app->post('/app/booklet/pdf/delete/{id:[0-9]+}', [BookletController::class, 'pdfDelete']);
        $app->get('/app/booklet/cover/{id:[0-9]+}', [BookletController::class, 'coverUploaded']);
        $app->get('/app/booklet/cover/{id:[0-9]+}/{lang}', [BookletController::class, 'cover']);
        $app->get('/app/booklet/cover_file/{lang}', [BookletController::class, 'coverFile']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');


    // Recipes
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/app/recipes', [RecipeController::class, 'list']);
        $app->post('/app/recipe/datatable', [RecipeController::class, 'datatable']);
        $app->post('/app/recipe/{id:[0-9]+}', [RecipeController::class, 'load']);
        $app->post('/app/recipe/save/{mode}', [RecipeController::class, 'save']);
        $app->post('/app/recipe/delete', [RecipeController::class, 'delete']);
        $app->get('/app/recipe/form[/{id:[0-9]+}[/{mode:[A-Z]+}]]', [RecipeController::class, 'form']);
        $app->post('/app/recipe/get_products', [RecipeController::class, 'getProducts']);
        $app->get('/app/recipe/pdf/{id:[0-9]+}', [RecipeController::class, 'pdfPreview']);
        $app->get('/app/recipe/pdf/file/{id:[0-9]+}', [RecipeController::class, 'pdfFile']);
        $app->post('/app/recipe/pdf/delete/{id:[0-9]+}', [RecipeController::class, 'pdfDelete']);
        $app->post('/app/recipe/duplicate', [RecipeController::class, 'duplicate']);
        $app->get('/app/recipe/product_image/{field}/{productId:[0-9]+}[/{recipeId:[0-9]+}]', [RecipeController::class, 'productImage']);
    })->add(new ProfileMiddleware([UserProfile::Administrator, UserProfile::User]))->add('csrf');
};
