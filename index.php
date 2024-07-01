<?php
require 'vendor/autoload.php';

use App\CreateDB;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

$dataBase = new CreateDB("storage/database.sqlite");
$dataBase->make();
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', [App\Controller\CryptoController::class, 'index']);
    $r->addRoute('GET', '/search/{symbol}', [App\Controller\CryptoController::class, 'show']);
    $r->addRoute('GET', '/transactions', [App\Controller\CryptoController::class, 'transactions']);
    $r->addRoute('POST', '/purchase/{symbol}', [App\Controller\CryptoController::class, 'purchase']);
    $r->addRoute('POST', '/sell/{symbol}', [App\Controller\CryptoController::class, 'sell']);
    $r->addRoute('GET', '/wallet', [App\Controller\CryptoController::class, 'wallet']);
});

$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        try {
            echo $twig->render('404.twig');
        } catch (LoaderError|SyntaxError|RuntimeError $e) {
        }
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        try {
            echo $twig->render('405.twig');
        } catch (LoaderError|SyntaxError|RuntimeError $e) {
        }
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        [$controller, $method] = $handler;
        $response = (new $controller)->$method($vars);
        if (is_array($response)) {
            try {
                echo $twig->render($method . '.twig', ['data' => $response]);
            } catch (LoaderError|RuntimeError|SyntaxError $e) {
            }
        } else {
            echo $response;
        }
        break;
}