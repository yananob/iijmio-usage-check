<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use App\Utils\Logger;
use App\Utils\Line;
use App\IijmioUsage;
use App\AppConfig;
use App\Firestore;
use Psr\Http\Message\ServerRequestInterface;
use eftec\bladeone\BladeOne;

FunctionsFramework::http('main_http', 'main_http');
function main_http(ServerRequestInterface $request): string
{
    $firestore = Firestore::getClient();
    $collectionName = AppConfig::getCollectionName();
    $docRef = $firestore->collection($collectionName)->document('config');
    $message = null;

    if ($request->getMethod() === 'POST') {
        $params = $request->getParsedBody();
        $configJson = $params['config'] ?? '';
        $configData = json_decode($configJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return "Invalid JSON: " . json_last_error_msg();
        }

        $docRef->set($configData);
        $message = "Config updated successfully.";
    }

    $doc = $docRef->snapshot();
    $configData = $doc->exists() ? $doc->data() : [];

    $configJson = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $views = __DIR__ . '/views';
    $cache = __DIR__ . '/cache';
    $blade = new BladeOne($views, $cache, BladeOne::MODE_AUTO);

    return $blade->run("config", [
        "message" => $message,
        "collectionName" => $collectionName,
        "configJson" => $configJson,
        "appEnv" => getenv('APP_ENV') ?: 'unknown',
    ]);
}

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
        $line = new Line($accessToken);

        $targetName = $config->alert->target ?? null;
        if (!is_string($targetName) || $targetName === '') {
            throw new \RuntimeException('Unable to get config->alert->target value.');
        }
        if (!isset($lineTokensAndTargets->target_ids->{$targetName})) {
            throw new \RuntimeException("Target ID not found for target key: {$targetName}");
        }
        $target = $lineTokensAndTargets->target_ids->{$targetName} ?? null;

        $line->sendPush(target: $target, message: $message);
    }

    $logger->log($message);
    $logger->log("Succeeded." . PHP_EOL);
}
