<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Controllers\ConfigController;
use App\Handlers\EventHandler;

FunctionsFramework::http('main_http', 'main_http');
function main_http(ServerRequestInterface $request): string
{
    $controller = new ConfigController();
    return $controller->handle($request);
}

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $handler = new EventHandler();
    $handler->handle($event);
}
