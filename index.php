<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use yananob\MyTools\Logger;
use yananob\MyTools\Utils;
use yananob\MyTools\Line;
use yananob\MyGcpTools\CFUtils;
use MyApp\IijmioUsage;

FunctionsFramework::cloudEvent('main', 'main');
function main(CloudEventInterface $event): void
{
    $logger = new Logger("main");

    $isLocal = CFUtils::isLocalEvent($event);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $config = Utils::getConfig(path: __DIR__ . "/configs/config.json", asArray: false);
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
