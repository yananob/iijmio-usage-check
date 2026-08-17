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
}
