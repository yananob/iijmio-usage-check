<?php declare(strict_types=1);

namespace App\Services;

trait ConfigServiceTrait
{
    /**
     * リクエストパラメータから設定構造をパースして正規化する
     *
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
                'web_url' => (string)($alert['web_url'] ?? ''),
            ],
        ];
    }

    /**
     * Raw履歴データから日別・月別の集計データを算出する
     *
     * @param array<string, mixed> $rawHistory
     * @param array<string, string> $userNames
     * @return array<string, mixed>
     */
    protected function processHistory(array $rawHistory, array $userNames): array
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
                    $uKey = (string)$userKey;
                    if ($uKey === 'coupon' || $uKey === 'totalRemainingDataVolume') {
                        continue;
                    }
                    $dateUsages[$uKey] = (float)$val;
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
