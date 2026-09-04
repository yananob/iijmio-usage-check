<?php declare(strict_types=1);

namespace App\Services;

use App\Firestore;
use App\IijmioUsage;
use App\Utils\Logger;

final class FirestoreConfigService implements ConfigServiceInterface
{
    use ConfigServiceTrait;

    private string $collectionName;

    public function __construct(string $collectionName)
    {
        $this->collectionName = $collectionName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $firestore = Firestore::getClient();
        $doc = $firestore->collection($this->collectionName)->document('config')->snapshot();
        return $doc->exists() ? (array)$doc->data() : [];
    }

    /**
     * @param array<string, mixed> $configData
     * @return string
     */
    public function saveConfig(array $configData): string
    {
        $firestore = Firestore::getClient();
        $docRef = $firestore->collection($this->collectionName)->document('config');
        $docRef->set($configData);
        return "Config updated successfully.";
    }

    /**
     * @param array<string, mixed> $configData
     * @return string
     */
    public function generatePreview(array $configData): string
    {
        $firestore = Firestore::getClient();
        $configObj = (object)json_decode((string)json_encode($configData));
        $logger = new Logger("preview");
        $historyDoc = $firestore->collection($this->collectionName)->document('history')->snapshot();
        $history = $historyDoc->exists() ? (array)$historyDoc->data() : [];

        $iijmio = new IijmioUsage(
            $configObj->iijmio,
            $configObj->alert->send_usage_each_n_days,
            $logger,
            $history,
            $configObj->alert->web_url ?? null
        );

        try {
            [, $previewMessage] = $iijmio->getStats();
            return $previewMessage;
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * @param bool $refresh
     * @return array<string, mixed>
     */
    public function getUsageSummary(bool $refresh = false): array
    {
        $configData = $this->getConfig();
        if (empty($configData)) {
            return [];
        }

        $firestore = Firestore::getClient();
        $configObj = (object)json_decode((string)json_encode($configData));
        $logger = new Logger("usage_summary");
        $historyDoc = $firestore->collection($this->collectionName)->document('history')->snapshot();
        $history = $historyDoc->exists() ? (array)$historyDoc->data() : [];

        $iijmio = new IijmioUsage(
            $configObj->iijmio,
            (int)($configObj->alert->send_usage_each_n_days ?? 10),
            $logger,
            $history,
            $configObj->alert->web_url ?? null
        );

        try {
            if ($refresh) {
                $summary = $iijmio->getDetailedStats();
                if (!empty($summary['monthlyUsages'])) {
                    $today = (new \Carbon\Carbon(timezone: \App\Consts::TIMEZONE))->format('Y-m-d');
                    $historyData = $summary['monthlyUsages'];
                    if (isset($summary['totalRemainingDataVolume'])) {
                        $historyData['coupon'] = $summary['totalRemainingDataVolume'];
                    }
                    $firestore->collection($this->collectionName)->document('history')->set([
                        $today => $historyData
                    ], ['merge' => true]);
                }
                return $summary;
            }
            return $iijmio->getDetailedStatsFromHistory();
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoryData(): array
    {
        $configData = $this->getConfig();
        $userNames = [];
        if (isset($configData['iijmio']['users']) && is_array($configData['iijmio']['users'])) {
            foreach ($configData['iijmio']['users'] as $code => $user) {
                $name = is_array($user) ? ($user['name'] ?? $code) : (is_object($user) ? ($user->name ?? $code) : $user);
                $userNames[(string)$code] = (string)$name;
            }
        }

        $firestore = Firestore::getClient();
        $historyDoc = $firestore->collection($this->collectionName)->document('history')->snapshot();
        $rawHistory = $historyDoc->exists() ? (array)$historyDoc->data() : [];

        return $this->processHistory($rawHistory, $userNames);
    }
}
