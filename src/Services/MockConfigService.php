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

    /**
     * @return array<string, mixed>
     */
    public function getUsageSummary(): array
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
  Alice: 2.1GB (+0.2) → 4.2GB
  Bob: 4.5GB (+0.4) → 8.6GB
  TOTAL: 6.6GB (+0.6) → 12.8GB
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

        ksort($rawHistory);

        $dailyList = [];
        $monthlyLatest = [];
        $prevUsages = [];
        $prevMonth = null;

        foreach ($rawHistory as $dateStr => $usages) {
            $currentMonth = substr($dateStr, 0, 7);
            $dailyCalculated = [];
            $totalDaily = 0.0;

            foreach ($usages as $userKey => $cumVal) {
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
            ];

            $monthlyLatest[$currentMonth] = $usages;
            $prevUsages = $usages;
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
