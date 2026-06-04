<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use MyApp\Utils\Logger;
use MyApp\Utils\Line;
use MyApp\IijmioUsage;
use MyApp\AppConfig;
use MyApp\Firestore;

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger("main_event");

    $appEnv = getenv('APP_ENV');
    $isProduction = ($appEnv === AppConfig::ENV_PRODUCTION);
    $logger->log("Running as " . ($isProduction ? "production" : "test") . " mode");

    $collectionName = $isProduction ? AppConfig::COLLECTION_NAME : AppConfig::COLLECTION_NAME_TEST;

    try {
        $firestore = Firestore::getClient();
        $doc = $firestore->collection($collectionName)->document('config')->snapshot();

        if (!$doc->exists()) {
            throw new \RuntimeException("Config not found in Firestore: {$collectionName}/config");
        }

        $config = (object)json_decode(json_encode($doc->data()));
    } catch (\Exception $e) {
        throw new \RuntimeException("Failed to load config from Firestore: " . $e->getMessage());
    }

    $iijmio = new IijmioUsage(
        $config->iijmio,
        $config->alert->send_usage_each_n_days
    );
    [$isSendAlert, $message] = $iijmio->getStats();
    if ($isSendAlert) {
        $accessToken = (string)getenv('LINE_CHANNEL_ACCESS_TOKEN');
        if (empty($accessToken)) {
            throw new \RuntimeException("LINE_CHANNEL_ACCESS_TOKEN is not set.");
        }
        $line = new Line($accessToken);
        $line->sendPush(target: $config->alert->target, message: $message);
    }

    $logger->log($message);
    $logger->log("Succeeded." . PHP_EOL);
}
