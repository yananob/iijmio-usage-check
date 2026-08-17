<?php declare(strict_types=1);

namespace App\Handlers;

use App\AppConfig;
use App\Consts;
use App\Firestore;
use App\IijmioUsage;
use App\Utils\Line;
use App\Utils\Logger;
use Carbon\Carbon;
use CloudEvents\V1\CloudEventInterface;

final class EventHandler
{
    public function handle(CloudEventInterface $event): void
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

        $config = (object)json_decode((string)json_encode($doc->data()));

        $historyDoc = $firestore->collection($collectionName)->document('history')->snapshot();
        $history = $historyDoc->exists() ? (array)$historyDoc->data() : [];

        $iijmio = new IijmioUsage(
            $config->iijmio,
            $config->alert->send_usage_each_n_days,
            $logger,
            $history
        );
        [$isSendAlert, $message, $monthlyUsages] = $iijmio->getStats();

        if (!empty($monthlyUsages)) {
            $today = (new Carbon(timezone: Consts::TIMEZONE))->format('Y-m-d');
            $firestore->collection($collectionName)->document('history')->set([
                $today => $monthlyUsages
            ], ['merge' => true]);
            $logger->log("Saved daily usage history for {$today}.");
        }

        if ($isSendAlert) {
            $lineTokensAndTargets = json_decode((string)getenv('LINE_TOKENS_N_TARGETS'), false);
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
}
