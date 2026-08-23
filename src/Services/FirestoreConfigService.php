<?php declare(strict_types=1);

namespace App\Services;

use App\Firestore;
use App\IijmioUsage;
use App\Utils\Logger;

final class FirestoreConfigService implements ConfigServiceInterface
{
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
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function parseConfigFromParams(array $params): array
    {
        $users = [];
        if (isset($params['iijmio']['users']) && is_array($params['iijmio']['users'])) {
            foreach ($params['iijmio']['users'] as $user) {
                if (is_array($user) && !empty($user['code']) && !empty($user['name'])) {
                    $users[(string)$user['code']] = [
                        'name' => (string)$user['name'],
                        'plan_data_volume' => (float)($user['plan_data_volume'] ?? 0),
                    ];
                }
            }
        }

        $iijmio = is_array($params['iijmio'] ?? null) ? $params['iijmio'] : [];
        $alert = is_array($params['alert'] ?? null) ? $params['alert'] : [];

        return [
            'iijmio' => [
                'mio_id' => (string)($iijmio['mio_id'] ?? ''),
                'password' => (string)($iijmio['password'] ?? ''),
                'users' => $users,
            ],
            'alert' => [
                'bot' => (string)($alert['bot'] ?? ''),
                'target' => (string)($alert['target'] ?? ''),
                'send_usage_each_n_days' => (int)($alert['send_usage_each_n_days'] ?? 0),
            ],
        ];
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
            $history
        );

        try {
            [, $previewMessage] = $iijmio->getStats();
            return $previewMessage;
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsageSummary(): array
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
            $history
        );

        try {
            return $iijmio->getDetailedStats();
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

    /**
     * @param array<string, mixed> $rawHistory
     * @param array<string, string> $userNames
     * @return array<string, mixed>
     */
    private function processHistory(array $rawHistory, array $userNames): array
    {
        ksort($rawHistory);

        $dailyList = [];
        $monthlyLatest = [];
        $prevUsages = [];
        $prevMonth = null;

        foreach ($rawHistory as $dateStr => $usages) {
            $dateUsages = [];
            if (is_array($usages) || is_object($usages)) {
                foreach ((array)$usages as $userKey => $val) {
                    $dateUsages[(string)$userKey] = (float)$val;
                }
            }

            $currentMonth = substr($dateStr, 0, 7);

            $dailyCalculated = [];
            $totalDaily = 0.0;
            foreach ($dateUsages as $userKey => $cumVal) {
                if ($prevMonth === $currentMonth && isset($prevUsages[$userKey])) {
                    $diff = max(0.0, $cumVal - $prevUsages[$userKey]);
                } else {
                    $diff = $cumVal;
                }
                $dailyCalculated[$userKey] = round($diff, 3);
                $totalDaily += $diff;
            }

            $dailyList[] = [
                'date' => $dateStr,
                'usages' => $dailyCalculated,
                'total' => round($totalDaily, 3),
                'cumulativeUsages' => $dateUsages,
                'cumulativeTotal' => round(array_sum($dateUsages), 3),
            ];

            $monthlyLatest[$currentMonth] = $dateUsages;
            $prevUsages = $dateUsages;
            $prevMonth = $currentMonth;
        }

        $monthlyList = [];
        foreach ($monthlyLatest as $monthStr => $usages) {
            $totalMonthly = array_sum($usages);
            $roundedUsages = [];
            foreach ($usages as $userKey => $val) {
                $roundedUsages[$userKey] = round((float)$val, 2);
            }
            $monthlyList[] = [
                'month' => $monthStr,
                'usages' => $roundedUsages,
                'total' => round($totalMonthly, 2),
            ];
        }

        return [
            'users' => $userNames,
            'daily' => $dailyList,
            'monthly' => $monthlyList,
        ];
    }
}
