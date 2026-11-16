<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\BalanceController;
use App\Controller\CartController;
use App\Controller\CheckoutController;
use App\Controller\HealthController;
use App\Controller\OrderController;
use App\Controller\PaymentController;
use App\Controller\ProductController;
use App\Controller\UploadController;
use App\Controller\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use App\Middleware\JsonErrorHandler;
use App\Middleware\RequestIdMiddleware;
use App\Support\Config;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

Config::load([]);

if (!Config::isLocal() && Config::bool('LAB_MODE', false)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'CONFIG_ERROR', 'message' => 'Konfigurasi tidak valid untuk lingkungan ini.'],
    ]);
    exit;
}

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->add(new RequestIdMiddleware());
$app->add(new JsonBodyParserMiddleware());

$errorMiddleware = $app->addErrorMiddleware(Config::debug(), true, true);
$errorMiddleware->setDefaultErrorHandler(new JsonErrorHandler());

$auth = new AuthController();
$users = new UserController();
$products = new ProductController();
$cart = new CartController();
$checkout = new CheckoutController();
$orders = new OrderController();
$payments = new PaymentController();
$balance = new BalanceController();
$uploads = new UploadController();
$health = new HealthController();
$authMw = new AuthMiddleware();

$app->group('/api/v1', function ($group) use ($auth, $users, $products, $cart, $checkout, $orders, $payments, $balance, $uploads, $health, $authMw): void {
    $group->get('/health', [$health, 'health']);

    $group->get('/payments/config', [$payments, 'config']);
    $group->post('/payments/midtrans/notification', [$payments, 'notification']);

    $group->post('/auth/register', [$auth, 'register']);
    $group->post('/auth/login', [$auth, 'login']);
    $group->post('/auth/forgot-password', [$auth, 'forgotPassword']);
    $group->post('/auth/verify-otp', [$auth, 'verifyOtp']);
    $group->post('/auth/reset-password', [$auth, 'resetPassword']);

    $group->get('/home', [$products, 'home']);
    $group->get('/search', [$products, 'search']);
    $group->get('/categories', [$products, 'categories']);
    $group->get('/sellers', [$users, 'sellers']);

    $group->group('/me', function ($me) use ($users): void {
        $me->get('', [$users, 'me']);
        $me->patch('', [$users, 'updateMe']);
        $me->get('/activity', [$users, 'meActivity']);
    })->add($authMw);

    $group->get('/users/{id}/card', [$users, 'card']);
    $group->get('/users/{id}/activity', [$users, 'activity']);
    $group->get('/users/{id}/products', [$users, 'products']);
    $group->get('/users/{id}', [$users, 'show']);
    $group->patch('/users/{id}', [$users, 'update'])->add($authMw);

    $group->get('/products/image-preview', [$products, 'imagePreview']);
    $group->get('/products', [$products, 'index']);
    $group->post('/products', [$products, 'store'])->add($authMw);
    $group->get('/products/{id}/comments', [$products, 'comments']);
    $group->post('/products/{id}/comments', [$products, 'addComment'])->add($authMw);
    $group->post('/products/{id}/document', [$products, 'uploadDocument'])->add($authMw);
    $group->get('/products/{id}/document', [$products, 'downloadDocument']);
    $group->get('/products/{id}', [$products, 'show']);
    $group->patch('/products/{id}', [$products, 'update'])->add($authMw);
    $group->delete('/products/{id}', [$products, 'destroy'])->add($authMw);

    $group->group('/cart', function ($cartGroup) use ($cart): void {
        $cartGroup->get('', [$cart, 'index']);
        $cartGroup->post('/items', [$cart, 'addItem']);
        $cartGroup->patch('/items/{id}', [$cart, 'updateItem']);
        $cartGroup->delete('/items/{id}', [$cart, 'deleteItem']);
    })->add($authMw);

    $group->post('/checkout', [$checkout, 'store'])->add($authMw);

    $group->group('/orders', function ($ordersGroup) use ($orders, $payments): void {
        $ordersGroup->get('', [$orders, 'index']);
        $ordersGroup->post('/{id}/cancel', [$orders, 'cancel']);
        $ordersGroup->get('/{id}/payment', [$payments, 'orderStatus']);
        $ordersGroup->get('/{id}', [$orders, 'show']);
    })->add($authMw);

    $group->group('/balance', function ($balanceGroup) use ($balance): void {
        $balanceGroup->get('', [$balance, 'show']);
        $balanceGroup->post('/topup', [$balance, 'topup']);
    })->add($authMw);

    $group->post('/uploads/editor', [$uploads, 'editor'])->add($authMw);
});

$app->run();
