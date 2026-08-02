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
    $previewMessage = null;

    if ($request->getMethod() === 'POST') {
        $params = $request->getParsedBody();

        $users = [];
        if (isset($params['iijmio']['users']) && is_array($params['iijmio']['users'])) {
            foreach ($params['iijmio']['users'] as $user) {
                if (!empty($user['code']) && !empty($user['name'])) {
                    $users[$user['code']] = [
                        'name' => $user['name'],
                        'plan_data_volume' => (float)($user['plan_data_volume'] ?? 0),
                    ];
                }
            }
        }

        $configData = [
            'iijmio' => [
                'mio_id' => $params['iijmio']['mio_id'] ?? '',
                'password' => $params['iijmio']['password'] ?? '',
                'users' => $users,
            ],
            'alert' => [
                'bot' => $params['alert']['bot'] ?? '',
                'target' => $params['alert']['target'] ?? '',
                'send_usage_each_n_days' => (int)($params['alert']['send_usage_each_n_days'] ?? 0),
            ],
        ];

        $action = $params['action'] ?? 'save';
        if ($action === 'save') {
            $docRef->set($configData);
            $message = "Config updated successfully.";
        } elseif ($action === 'preview') {
            $configObj = (object)json_decode(json_encode($configData));
            $logger = new Logger("preview");
            $iijmio = new IijmioUsage(
                $configObj->iijmio,
                $configObj->alert->send_usage_each_n_days,
                $logger
            );
            try {
                [, $previewMessage] = $iijmio->getStats();
            } catch (\Exception $e) {
                $previewMessage = "Error: " . $e->getMessage();
            }
        }
    } else {
        $doc = $docRef->snapshot();
        $configData = $doc->exists() ? $doc->data() : [];
    }

    $views = __DIR__ . '/views';
    $cache = '/tmp/cache';
    if (!is_dir($cache)) {
        mkdir($cache, 0777, true);
    }
    $blade = new BladeOne($views, $cache, BladeOne::MODE_AUTO);

    return $blade->run("config", [
        "message" => $message,
        "previewMessage" => $previewMessage ?? null,
        "collectionName" => $collectionName,
        "config" => $configData,
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
