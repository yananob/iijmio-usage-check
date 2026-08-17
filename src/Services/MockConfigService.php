<?php declare(strict_types=1);

namespace App\Services;

final class MockConfigService implements ConfigServiceInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $configData = [
        'iijmio' => [
            'mio_id' => 'MA1234567',
            'password' => 'supersecret',
            'users' => [
                'hdo11111111' => [
                    'name' => 'Alice',
                    'plan_data_volume' => 5.0
                ],
                'hdo22222222' => [
                    'name' => 'Bob',
                    'plan_data_volume' => 10.0
                ]
            ]
        ],
        'alert' => [
            'bot' => 'MyLineBot',
            'target' => 'MyGroup',
            'send_usage_each_n_days' => 3
        ]
    ];

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->configData;
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
        $this->configData = $configData;
        return "Config updated successfully. (Mock Save)";
    }

    /**
     * @param array<string, mixed> $configData
     * @return string
     */
    public function generatePreview(array $configData): string
    {
        return "[IIJmioデータ利用状況]\n- Alice (hdo11111111): 1.2 GB / 5.0 GB\n- Bob (hdo22222222): 4.5 GB / 10.0 GB\n\n[予測根拠]\nAlice: 直近3日間の平均から算出。\nBob: 直近3日間の平均から算出。";
    }
}
