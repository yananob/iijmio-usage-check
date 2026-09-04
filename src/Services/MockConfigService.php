<?php declare(strict_types=1);

namespace App\Services;

final class MockConfigService implements ConfigServiceInterface
{
    use ConfigServiceTrait;

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
            'send_usage_each_n_days' => 3,
            'web_url' => 'https://example.com'
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

    /**
     * @param bool $refresh
     * @return array<string, mixed>
     */
    public function getUsageSummary(bool $refresh = false): array
    {
        $now = new \Carbon\Carbon('2025-02-15 12:00:00', timezone: \App\Consts::TIMEZONE);
        $remainingDays = $now->daysInMonth() - $now->day;

        $message = <<<EOT
[INFO] Mobile usage report

Usage:
  Alice: 2.1GB  (+0.2)
  Bob: 4.5GB  (+0.4)
  TOTAL: 6.6GB  (+0.6, 44%)

EoM: 12.8GB  (85%)
Plan: 15.0GB
Left: 8.4GB
残り消費予定: 6.2GB
過不足予定: 2.2GB

[予測根拠] (残り{$remainingDays}日)
  Alice: 0.1/日 1.0/週 → 4.2GB
  Bob: 0.3/日 2.1/週 → 8.6GB
  TOTAL: 0.4/日 3.1/週 → 12.8GB

Web: https://example.com
EOT;

        return [
            'isSend' => false,
            'message' => $message,
            'monthlyUsages' => [
                'hdo11111111' => 2.1,
                'hdo22222222' => 4.5,
            ],
            'dailyUsages' => [
                'hdo11111111' => 0.2,
                'hdo22222222' => 0.4,
            ],
            'planDataVolume' => 15.0,
            'thisMonthTotalUsage' => 6.6,
            'dailyTotalUsage' => 0.6,
            'totalRemainingDataVolume' => 8.4,
            'estimateUsage' => 12.8,
            'remainingConsumption' => 6.2,
            'shortageOrSurplus' => 2.2,
            'users' => [
                [
                    'code' => 'hdo11111111',
                    'name' => 'Alice',
                    'currentUsage' => 2.1,
                    'dailyUsage' => 0.2,
                    'estimatedUserUsage' => 4.2,
                    'planDataVolume' => 5.0,
                ],
                [
                    'code' => 'hdo22222222',
                    'name' => 'Bob',
                    'currentUsage' => 4.5,
                    'dailyUsage' => 0.4,
                    'estimatedUserUsage' => 8.6,
                    'planDataVolume' => 10.0,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoryData(): array
    {
        $rawHistory = [
            '2024-12-31' => ['hdo11111111' => 4.2, 'hdo22222222' => 8.5],
            '2025-01-31' => ['hdo11111111' => 4.8, 'hdo22222222' => 9.1],
            '2025-02-10' => ['hdo11111111' => 1.2, 'hdo22222222' => 2.8],
            '2025-02-11' => ['hdo11111111' => 1.4, 'hdo22222222' => 3.2],
            '2025-02-12' => ['hdo11111111' => 1.6, 'hdo22222222' => 3.5],
            '2025-02-13' => ['hdo11111111' => 1.8, 'hdo22222222' => 3.9],
            '2025-02-14' => ['hdo11111111' => 1.9, 'hdo22222222' => 4.1],
            '2025-02-15' => ['hdo11111111' => 2.1, 'hdo22222222' => 4.5],
        ];

        $userNames = [
            'hdo11111111' => 'Alice',
            'hdo22222222' => 'Bob',
        ];

        return $this->processHistory($rawHistory, $userNames);
    }
}
