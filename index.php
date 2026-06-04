<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use MyApp\Utils\Logger;
use MyApp\Utils\Utils;
use MyApp\Utils\Line;
use MyApp\Utils\CFUtils;
use MyApp\IijmioUsage;
use MyApp\AppConfig;

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger("main_event");

    $isLocal = CFUtils::isLocalEvent($event);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $config = AppConfig::get();
    $iijmio = new IijmioUsage(
        $config->iijmio,
        $config->alert->send_usage_each_n_days
    );
    [$isSendAlert, $message] = $iijmio->getStats();
    if ($isSendAlert) {
        $line = new Line(__DIR__ . '/configs/line.json');
        $line->sendPush(bot: $config->alert->bot, target: $config->alert->target, message: $message);
    }

    $logger->log($message);
    $logger->log("Succeeded." . PHP_EOL);
}
