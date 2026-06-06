<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use App\Utils\Logger;
use App\Utils\Line;
use App\IijmioUsage;
use App\AppConfig;
use App\Firestore;

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger("main_event");

    $appEnv = getenv('APP_ENV');
    $logger->log("Running as " . ($appEnv ?: "unknown") . " mode");

    $collectionName = AppConfig::getCollectionName();

    $firestore = Firestore::getClient();
    $doc = $firestore->collection($collectionName)->document('config')->snapshot();

    if (!$doc->exists()) {
        throw new \RuntimeException("Config not found in Firestore: {$collectionName}/config");
    }

    $config = (object)json_decode(json_encode($doc->data()));

    $iijmio = new IijmioUsage(
        $config->iijmio,
        $config->alert->send_usage_each_n_days,
        $logger
    );
    [$isSendAlert, $message] = $iijmio->getStats();
    if ($isSendAlert) {
        $lineTokensAndTargets = json_decode(getenv('LINE_TOKENS_N_TARGETS'), false);
        $botName = $config->alert->bot ?? null;
        if (!is_string($botName) || $botName === '') {
            throw new \RuntimeException('Unable to get config->alert->bot value.');
        }
        if (!isset($lineTokensAndTargets->tokens->{$botName})) {
            throw new \RuntimeException("Access token not found for bot key: {$botName}");
        }
        $accessToken = $lineTokensAndTargets->tokens->{$botName};
        $logger->log("access Token: " . $accessToken);
        $line = new Line($accessToken);

        $targetName = $config->alert->target ?? null;
        if (!is_string($targetName) || $targetName === '') {
            throw new \RuntimeException('Unable to get config->alert->target value.');
        }
        if (!isset($lineTokensAndTargets->target_ids->{$targetName})) {
            throw new \RuntimeException("Target ID not found for target key: {$targetName}");
        }
        $target = $lineTokensAndTargets->target_ids->{$targetName} ?? null;
        $logger->log("Target: " . $target);

        $line->sendPush(target: $target, message: $message);
    }

    $logger->log($message);
    $logger->log("Succeeded." . PHP_EOL);
}
