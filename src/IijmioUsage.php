<?php declare(strict_types=1);

namespace App;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use App\Utils\Logger;

final class IijmioUsage
{
    public function __construct(
        private object $iijmioConfig,
        private int $sendEachNDays = 10,
        private ?Logger $logger = null,
        private array $history = []
    ) {
    }

    public function getStats(): array
    {
        $this->logger?->info("Starting to crawl IIJmio usage data...");
        [$remainingDataVolume, $monthlyUsages, $dailyUsages] = $this->__crawl();
        $this->logger?->info("Successfully crawled data.");
        [$isSend, $message] = $this->__judgeResult($remainingDataVolume, $monthlyUsages, $dailyUsages);
        return [$isSend, $message, $monthlyUsages];
    }

    private function __crawl(): array
    {
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->logger?->info("Attempting crawl (Attempt " . ($i + 1) . "/5)...");
                $client = new Client([
                    'base_uri' => 'https://www.iijmio.jp/',
                    'timeout'  => 30.0,
                ]);
                $cookieJar = new CookieJar();

                $this->logger?->info("Fetching member page...");
                $response = $client->get(
                    "/member/",
                    [
                        "headers" => $this->__getHttpHeaders(null),
                        "cookies" => $cookieJar,
                    ]
                );
                $this->__checkResponse($response);

                $this->logger?->info("Logging in...");
                $response = $client->post(
                    "/api/member/login",
                    [
                        "headers" => $this->__getHttpHeaders("application/json"),
                        "cookies" => $cookieJar,
                        "json" => [
                            "mioId" => $this->iijmioConfig->mio_id,
                            "password"  => $this->iijmioConfig->password,
                        ],
                    ]
                );
                $this->__checkResponse($response);

                $this->logger?->info("Fetching top page data (coupon data)...");
                $response = $client->post(
                    "/api/member/top",
                    [
                        "headers" => $this->__getHttpHeaders("application/json"),
                        "cookies" => $cookieJar,
                        "json" => [
                            "billingFlag" => true,
                            "serviceCode"  => "",
                        ],
                    ]
                );
                $this->__checkResponse($response);
                $body = json_decode((string)$response->getBody(), true);
                if (empty($body["serviceInfoList"][0]["couponData"])) {
                    throw new \Exception("Could not get couponData: " . var_export($body, true));
                }
                $remainingDataVolume = [];
                foreach (json_decode((string)$response->getBody(), true)["serviceInfoList"][0]["couponData"] as $couponData) {
                    $remainingDataVolume[$couponData["month"]] = $couponData["couponValue"];
                }

                $this->logger?->info("Fetching monthly usage page...");
                $response = $client->get(
                    "/service/setup/hdc/viewmonthlydata/",
                    [
                        "headers" => $this->__getHttpHeaders(null),
                        "cookies" => $cookieJar,
                    ]
                );
                $this->__checkResponse($response);
                $monthlyUsage = $this->__parseMonthlyUsagePage((string)$response->getBody());

                $this->logger?->info("Fetching daily usage page...");
                $response = $client->get(
                    "/service/setup/hdc/viewdailydata/",
                    [
                        "headers" => $this->__getHttpHeaders(null),
                        "cookies" => $cookieJar,
                    ]
                );
                $this->__checkResponse($response);
                $dailyUsage = $this->__parseDailyUsagePage((string)$response->getBody());

                return [$remainingDataVolume, $monthlyUsage, $dailyUsage];
            } catch (\Exception $e) {
                $this->logger?->warning("Crawl attempt " . ($i + 1) . " failed: " . $e->getMessage());
                if ($i >= 4) {
                    throw $e;
                }
                sleep(10);
            }
        }

        throw new \Exception("Retry limit exceeded.");
    }

    private function __getHttpHeaders(?string $contentType): array
    {
        $result =  [
            // これを与えないと、HTMLが結構変わったり、検索時の書籍名がより短い（モバイル向け？）ものになる
            "User-Agent" => "Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Mobile Safari/537.36",
        ];

        if (!empty($contentType)) {
            $result["Content-Type"] = $contentType;
        }

        return $result;
    }

    private function __checkResponse($response): void
    {
        if (!in_array($response->getStatusCode(), [200])) {
            throw new \Exception("Request error. [" . $response->getStatusCode() . "] " . $response->getReasonPhrase());
        }
    }

    private function __parseMonthlyUsagePage(string $content): array
    {
        // 不要部分カット
        $content = preg_replace('/<h1>データ利用量照会（月別）<\/h1>/m', "", $content);
        // var_dump($content);

        $result = [];
        // ユーザーごとに分割
        $contentUsers = explode('<div class="viewdata">', $content);
        foreach ($contentUsers as $idx => $contentUser) {
            if ($idx === 0) {
                continue;
            }

            // <input id="hdoCode" name="hdoCode" value="hdo12345678" type="hidden" value=""/>
            preg_match('/<input id="hdoCode" name="hdoCode" value="(hdo[0-9]+?)" type="hidden" value=""\/>/', $contentUser, $matches);
            if (!$matches || count($matches) < 2) {
                throw new \Exception("Could not get hdoCode usage: " . $contentUser);
            }
            $hdoCode = $matches[1];

            // <td class="viewdata-detail-cell2">
            // 5.3GB </td>
            preg_match('/<td class="viewdata-detail-cell2">[\s]*?([0-9\.]+)GB[\s]*<\/td>/m', $contentUser, $matches);
            if (!$matches || count($matches) < 2) {
                throw new \Exception("Could not get monthly usage: " . $contentUser);
            }
            $usage = (float)$matches[1];

            $result[$hdoCode] = $usage;
        }

        return $result;
    }

    private function __parseDailyUsagePage(string $content): array
    {
        // 不要部分カット
        $content = preg_replace('/<h1>データ利用量照会<\/h1>/m', "", $content);
        // var_dump($content);

        $result = [];
        // ユーザーごとに分割
        $contentUsers = explode('<div class="viewdata">', $content);
        foreach ($contentUsers as $idx => $contentUser) {
            if ($idx === 0) {
                continue;
            }

            // <input id="hdoCode" name="hdoCode" value="hdo12345678" type="hidden" value=""/>
            preg_match('/<input id="hdoCode" name="hdoCode" value="(hdo[0-9]+?)" type="hidden" value=""\/>/', $contentUser, $matches);
            if (!$matches || count($matches) < 2) {
                throw new \Exception("Could not get hdoCode usage: " . $contentUser);
            }
            $hdoCode = $matches[1];

            // <td class="viewdata-detail-cell2">
            // 5.3GB </td>
            preg_match('/<td class="viewdata-detail-cell2">[\s]*?([0-9\.]+)MB[\s]*<\/td>/m', $contentUser, $matches);
            if (!$matches || count($matches) < 2) {
                throw new \Exception("Could not get daily usage: " . $contentUser);
            }
            $usage = ((float)$matches[1] / 1000);  // MB -> GB

            $result[$hdoCode] = $usage;
        }

        return $result;
    }

    private function __judgeResult(array $remainingDataVolume, array $monthlyUsages, array $dailyUsages): array
    {
        $totalRemainingDataVolume = array_sum($remainingDataVolume);
        [$estimateUsage, $estimateDetails] = $this->__estimateThisMonthUsage($monthlyUsages);

        $planDataVolume = 0.0;
        if (isset($this->iijmioConfig->users)) {
            foreach ($this->iijmioConfig->users as $user => $userInfo) {
                if (is_object($userInfo) && isset($userInfo->plan_data_volume)) {
                    $planDataVolume += (float)$userInfo->plan_data_volume;
                } elseif (is_array($userInfo) && isset($userInfo['plan_data_volume'])) {
                    $planDataVolume += (float)$userInfo['plan_data_volume'];
                }
            }
        }

        $isSend = false;
        if ($planDataVolume > 0 && $estimateUsage > $planDataVolume * 0.9) {
            $isSend = true;
            $subject = "[WARN] Mobile usage is not good";
        } else {
            $subject = "[INFO] Mobile usage report";
        }
        $now = new Carbon(timezone: Consts::TIMEZONE);
        if ($now->day % $this->sendEachNDays === 0) {
            $isSend = true;
        }

        $thisMonthUsageList = [];
        foreach ($monthlyUsages as $user => $monthlyUsage) {
            $monthlyUsage = sprintf("%.1f", $monthlyUsage);
            $dailyUsage = sprintf("%.1f", $dailyUsages[$user]);
            $userName = $user;
            if (isset($this->iijmioConfig->users->$user)) {
                $userInfo = $this->iijmioConfig->users->$user;
                if (is_object($userInfo) && isset($userInfo->name)) {
                    $userName = $userInfo->name;
                } elseif (is_array($userInfo) && isset($userInfo['name'])) {
                    $userName = $userInfo['name'];
                } elseif (is_string($userInfo)) {
                    $userName = $userInfo;
                }
            }
            $thisMonthUsageList[] = "  {$userName}: {$monthlyUsage}GB  (+{$dailyUsage})";
        }
        $thisMonthUsageList = implode("\n", $thisMonthUsageList);
        $thisMonthTotalUsage = sprintf("%.1f", array_sum($monthlyUsages));
        $dailyTotalUsage = sprintf("%.1f", array_sum($dailyUsages));
        $thisMonthTotalUsageRate = $planDataVolume > 0 ? (int)round($thisMonthTotalUsage / $planDataVolume * 100, 0) : 0;
        $estimateUsageRate = $planDataVolume > 0 ? (int)round($estimateUsage / $planDataVolume * 100, 0) : 0;
        $planDataVolumeStr = sprintf("%.1f", $planDataVolume);
        $totalRemainingDataVolume = sprintf("%.1f", $totalRemainingDataVolume);

        $remainingDays = $now->daysInMonth() - $now->day;

        $detailList = [];
        foreach ($estimateDetails as $user => $detail) {
            $userName = $user;
            if (isset($this->iijmioConfig->users->$user)) {
                $userInfo = $this->iijmioConfig->users->$user;
                if (is_object($userInfo) && isset($userInfo->name)) {
                    $userName = $userInfo->name;
                } elseif (is_array($userInfo) && isset($userInfo['name'])) {
                    $userName = $userInfo['name'];
                } elseif (is_string($userInfo)) {
                    $userName = $userInfo;
                }
            }

            $estimatedUserUsageStr = sprintf("%.1f", $detail['estimatedUserUsage']);
            $rProjectedStr = sprintf("%.2f", $detail['avgConsumptionPerDay']);

            if ($detail['wCurrent'] < 1.0) {
                // Beginning of the month (blended with baseline)
                $rCurrentBlended = $detail['hasRecent']
                    ? (0.5 * $detail['rRecent'] + 0.5 * $detail['rCumulative'])
                    : $detail['rCumulative'];
                $rCurrentBlendedStr = sprintf("%.2f", $rCurrentBlended);
                $rBaselineStr = sprintf("%.2f", $detail['rBaseline']);
                $wCurrentPct = (int)round($detail['wCurrent'] * 100);
                $wBaselinePct = 100 - $wCurrentPct;

                $detailList[] = "  {$userName}: 日平均 {$rProjectedStr}GB [内訳: 今月実績 {$rCurrentBlendedStr}GB×{$wCurrentPct}% + 前月/計画 {$rBaselineStr}GB×{$wBaselinePct}%] -> 月末予測: {$estimatedUserUsageStr}GB";
            } else {
                // Middle/end of the month (100% current month)
                if ($detail['hasRecent']) {
                    $dayDiff = $detail['dayDiff'];
                    $rRecentStr = sprintf("%.2f", $detail['rRecent']);
                    $rCumulativeStr = sprintf("%.2f", $detail['rCumulative']);
                    $detailList[] = "  {$userName}: 日平均 {$rProjectedStr}GB [内訳: 直近{$dayDiff}日 {$rRecentStr}GB×50% + 月初来 {$rCumulativeStr}GB×50%] -> 月末予測: {$estimatedUserUsageStr}GB";
                } else {
                    $rCumulativeStr = sprintf("%.2f", $detail['rCumulative']);
                    $detailList[] = "  {$userName}: 日平均 {$rProjectedStr}GB [内訳: 月初来 {$rCumulativeStr}GB(100%)] -> 月末予測: {$estimatedUserUsageStr}GB";
                }
            }
        }
        $detailStr = implode("\n", $detailList);

        $message = <<<EOT
{$subject}

Usage:
{$thisMonthUsageList}
  TOTAL: {$thisMonthTotalUsage}GB  (+{$dailyTotalUsage}, {$thisMonthTotalUsageRate}%)

EoM: {$estimateUsage}GB  ({$estimateUsageRate}%)
Plan: {$planDataVolumeStr}GB
Left: {$totalRemainingDataVolume}GB

[予測根拠] (残り{$remainingDays}日)
{$detailStr}
EOT;

        return [$isSend, $message];
    }

    private function __estimateThisMonthUsage(array $monthlyUsage): array
    {
        $now = new Carbon(timezone: Consts::TIMEZONE);
        $todayStr = $now->format('Y-m-d');
        $currentYearMonth = $now->format('Y-m');
        $prevYearMonth = $now->copy()->subMonth()->format('Y-m');
        $daysInMonth = $now->daysInMonth();
        $currentDay = $now->day;

        $monthlyHistory = [];
        $prevHistory = [];
        foreach ($this->history as $dateStr => $usages) {
            if (str_starts_with($dateStr, $currentYearMonth) && $dateStr < $todayStr) {
                $monthlyHistory[$dateStr] = $usages;
            } elseif (str_starts_with($dateStr, $prevYearMonth)) {
                $prevHistory[$dateStr] = $usages;
            }
        }
        krsort($monthlyHistory);
        krsort($prevHistory);

        $totalEstimated = 0.0;
        $details = [];
        foreach ($monthlyUsage as $user => $currentUsage) {
            // Baseline Rate calculation (R_baseline)
            $rPrev = null;
            if (!empty($prevHistory)) {
                $prevDateStr = (string)array_key_first($prevHistory);
                $prevUsages = $prevHistory[$prevDateStr];
                $prevUserUsage = null;
                if (is_object($prevUsages) && isset($prevUsages->$user)) {
                    $prevUserUsage = (float)$prevUsages->$user;
                } elseif (is_array($prevUsages) && isset($prevUsages[$user])) {
                    $prevUserUsage = (float)$prevUsages[$user];
                }

                if ($prevUserUsage !== null) {
                    $prevRecordDay = (new Carbon($prevDateStr, timezone: Consts::TIMEZONE))->day;
                    $rPrev = $prevUserUsage / $prevRecordDay;
                }
            }

            $userPlanVolume = 0.0;
            if (isset($this->iijmioConfig->users->$user)) {
                $userInfo = $this->iijmioConfig->users->$user;
                if (is_object($userInfo) && isset($userInfo->plan_data_volume)) {
                    $userPlanVolume = (float)$userInfo->plan_data_volume;
                } elseif (is_array($userInfo) && isset($userInfo['plan_data_volume'])) {
                    $userPlanVolume = (float)$userInfo['plan_data_volume'];
                }
            }

            if ($rPrev !== null) {
                $rBaseline = $rPrev;
                $baselineSource = 'previous_month';
            } else {
                $rBaseline = ($userPlanVolume > 0.0) ? ($userPlanVolume / $daysInMonth) : 0.0;
                $baselineSource = 'plan';
            }

            // Current Month Cumulative Rate (R_cumulative)
            $rCumulative = $currentDay > 0 ? ($currentUsage / $currentDay) : 0.0;

            // Current Month Recent 7-Day Rate (R_recent)
            $bestDateStr = null;
            $bestUserPastUsage = null;
            $bestDayDiff = null;
            $minDistance = null;

            foreach ($monthlyHistory as $dateStr => $usages) {
                $userPastUsage = null;
                if (is_object($usages) && isset($usages->$user)) {
                    $userPastUsage = (float)$usages->$user;
                } elseif (is_array($usages) && isset($usages[$user])) {
                    $userPastUsage = (float)$usages[$user];
                }

                if ($userPastUsage !== null) {
                    $pastCarbon = new Carbon($dateStr, timezone: Consts::TIMEZONE);
                    $dayDiff = $currentDay - $pastCarbon->day;
                    if ($dayDiff >= 1) {
                        $distance = abs($dayDiff - 7);
                        if ($minDistance === null || $distance < $minDistance || ($distance === $minDistance && $dayDiff > $bestDayDiff)) {
                            $minDistance = $distance;
                            $bestDayDiff = $dayDiff;
                            $bestDateStr = $dateStr;
                            $bestUserPastUsage = $userPastUsage;
                        }
                    }
                }
            }

            $hasRecent = false;
            $rRecent = null;
            if ($bestDateStr !== null && $bestUserPastUsage !== null && $bestDayDiff !== null) {
                $consumption = $currentUsage - $bestUserPastUsage;
                $rRecent = max(0.0, $consumption / $bestDayDiff);
                $hasRecent = true;
            }

            // Blend Recent and Cumulative for Current Month Rate (R_current_blended)
            if ($hasRecent && $rRecent !== null) {
                $rCurrentBlended = 0.5 * $rRecent + 0.5 * $rCumulative;
            } else {
                $rCurrentBlended = $rCumulative;
            }

            // Current Month Weight (W_current)
            $wCurrent = min(1.0, ($currentDay - 1) / 7.0);

            // Projected Rate (R_projected)
            $rProjected = $wCurrent * $rCurrentBlended + (1.0 - $wCurrent) * $rBaseline;

            // Estimated Usage
            $remainingDays = $daysInMonth - $currentDay;
            $estimatedUserUsage = $currentUsage + ($rProjected * $remainingDays);

            $this->logger?->info("User {$user}: cumulative rate = {$rCumulative}GB/day, recent rate = " . ($rRecent !== null ? "{$rRecent}" : "N/A") . "GB/day, baseline rate = {$rBaseline}GB/day ({$baselineSource}), current weight = {$wCurrent}, projected rate = {$rProjected}GB/day. Estimated = {$estimatedUserUsage}GB");

            $detail = [
                'type' => 'blended',
                'currentDay' => $currentDay,
                'currentUsage' => $currentUsage,
                'avgConsumptionPerDay' => round($rProjected, 4),
                'remainingDays' => $remainingDays,
                'estimatedUserUsage' => $estimatedUserUsage,
                'rCumulative' => $rCumulative,
                'rRecent' => $rRecent,
                'rBaseline' => $rBaseline,
                'wCurrent' => $wCurrent,
                'hasRecent' => $hasRecent,
                'dayDiff' => $bestDayDiff,
                'pastDate' => $bestDateStr ? (new Carbon($bestDateStr, timezone: Consts::TIMEZONE))->format('m/d') : null,
                'baselineSource' => $baselineSource,
            ];

            $totalEstimated += $estimatedUserUsage;
            $details[$user] = $detail;
        }

        return [round($totalEstimated, 1), $details];
    }

}
