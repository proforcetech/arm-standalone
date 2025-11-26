<?php
declare(strict_types=1);

use ARM\Routing\Kernel;
use Dotenv\Dotenv;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

$rootPath = dirname(__DIR__);

require $rootPath . '/vendor/autoload.php';

if (file_exists($rootPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($rootPath);
    $dotenv->safeLoad();
}

require $rootPath . '/includes/bootstrap.php';

Kernel::bootModules();

$dispatcher = simpleDispatcher(function (RouteCollector $router) {
    $router->addRoute(['GET'], '/health', static fn () => ['status' => 'ok']);

    $router->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/{path:.*}', [Kernel::class, 'dispatch']);
});

$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}

$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo 'Not Found';
        break;

    case Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        echo 'Method Not Allowed';
        break;

    case Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        $response = is_callable($handler) ? $handler($vars) : null;
        Kernel::renderResponse($response);
        break;
}
