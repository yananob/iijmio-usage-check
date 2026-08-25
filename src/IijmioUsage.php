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
        private array $history = [],
        private ?string $webUrl = null
    ) {
    }

    public function getStats(): array
    {
        $this->logger?->info("Starting to crawl IIJmio usage data...");
        [$remainingDataVolume, $monthlyUsages, $dailyUsages] = $this->crawl();
        $this->logger?->info("Successfully crawled data.");
        [$isSend, $message] = $this->judgeResult($remainingDataVolume, $monthlyUsages, $dailyUsages);
        $totalRemainingDataVolume = array_sum($remainingDataVolume);
        return [$isSend, $message, $monthlyUsages, round($totalRemainingDataVolume, 2)];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetailedStats(): array
    {
        $this->logger?->info("Starting to crawl IIJmio usage data...");
        [$remainingDataVolume, $monthlyUsages, $dailyUsages] = $this->crawl();
        $this->logger?->info("Successfully crawled data.");
        return $this->buildSummary($remainingDataVolume, $monthlyUsages, $dailyUsages);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetailedStatsFromHistory(): array
    {
        $now = new Carbon(timezone: Consts::TIMEZONE);
        $todayStr = $now->format('Y-m-d');
        $yesterdayStr = $now->copy()->subDay()->format('Y-m-d');

        $sortedHistory = $this->history;
        ksort($sortedHistory);

        $targetDate = null;
        if (isset($sortedHistory[$todayStr])) {
            $targetDate = $todayStr;
        } elseif (isset($sortedHistory[$yesterdayStr])) {
            $targetDate = $yesterdayStr;
        }

        $monthlyUsages = [];
        $dailyUsages = [];
        $savedTotalRemainingDataVolume = null;

        if ($targetDate !== null) {
            $targetUsages = (array)$sortedHistory[$targetDate];

            if (isset($targetUsages['coupon'])) {
                $savedTotalRemainingDataVolume = (float)$targetUsages['coupon'];
            } elseif (isset($targetUsages['totalRemainingDataVolume'])) {
                $savedTotalRemainingDataVolume = (float)$targetUsages['totalRemainingDataVolume'];
            }

            foreach ($targetUsages as $userKey => $val) {
                $uKey = (string)$userKey;
                if ($uKey === 'coupon' || $uKey === 'totalRemainingDataVolume') {
                    continue;
                }
                $monthlyUsages[$uKey] = (float)$val;
            }

            // Find preceding record date before $targetDate
            $prevDate = null;
            foreach (array_keys($sortedHistory) as $dateStr) {
                $dateStr = (string)$dateStr;
                if ($dateStr < $targetDate) {
                    $prevDate = $dateStr;
                } else {
                    break;
                }
            }

            if ($prevDate !== null) {
                $prevUsages = (array)$sortedHistory[$prevDate];
                $targetMonth = substr($targetDate, 0, 7);
                $prevMonth = substr($prevDate, 0, 7);

                foreach ($monthlyUsages as $userKey => $cumVal) {
                    if ($targetMonth === $prevMonth && isset($prevUsages[$userKey])) {
                        $prevVal = (float)$prevUsages[$userKey];
                        $dailyUsages[$userKey] = max(0.0, $cumVal - $prevVal);
                    } else {
                        $dailyUsages[$userKey] = $cumVal;
                    }
                }
            } else {
                foreach ($monthlyUsages as $userKey => $cumVal) {
                    $dailyUsages[$userKey] = $cumVal;
                }
            }
        }

        // Ensure all configured users exist in $monthlyUsages and $dailyUsages
        if (isset($this->iijmioConfig->users)) {
            foreach ($this->iijmioConfig->users as $userKey => $userVal) {
                $uKey = (string)$userKey;
                if (!isset($monthlyUsages[$uKey])) {
                    $monthlyUsages[$uKey] = 0.0;
                }
                if (!isset($dailyUsages[$uKey])) {
                    $dailyUsages[$uKey] = 0.0;
                }
            }
        }

        if ($savedTotalRemainingDataVolume !== null) {
            $remainingDataVolume = ['coupon' => $savedTotalRemainingDataVolume];
        } else {
            // Calculate remaining data volume estimate from plan volume - current usage
            $planDataVolume = 0.0;
            if (isset($this->iijmioConfig->users)) {
                foreach ($this->iijmioConfig->users as $user => $userInfo) {
                    $planDataVolume += $this->getUserPlanDataVolume((string)$user);
                }
            }
            $thisMonthTotalUsageVal = array_sum($monthlyUsages);
            $remainingDataVolume = ['current' => max(0.0, $planDataVolume - $thisMonthTotalUsageVal)];
        }

        return $this->buildSummary($remainingDataVolume, $monthlyUsages, $dailyUsages);
    }

    private function crawl(): array
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
                        "headers" => $this->getHttpHeaders(null),
                        "cookies" => $cookieJar,
                    ]
                );
                $this->checkResponse($response);

                $this->logger?->info("Logging in...");
                $response = $client->post(
                    "/api/member/login",
                    [
                        "headers" => $this->getHttpHeaders("application/json"),
                        "cookies" => $cookieJar,
                        "json" => [
                            "mioId" => $this->iijmioConfig->mio_id,
                            "password"  => $this->iijmioConfig->password,
                        ],
                    ]
                );
                $this->checkResponse($response);
                $loginBody = json_decode((string)$response->getBody(), true);
                if (!empty($loginBody['error'])) {
                    throw new \Exception("Login failed with error: " . $loginBody['error']);
                }

                $this->logger?->info("Fetching top page data (coupon data)...");
                $response = $client->post(
                    "/api/member/top",
                    [
                        "headers" => $this->getHttpHeaders("application/json"),
                        "cookies" => $cookieJar,
                        "json" => [
                            "billingFlag" => true,
                            "serviceCode"  => "",
                        ],
                    ]
                );
                $this->checkResponse($response);
                $body = json_decode((string)$response->getBody(), true);
                if (!empty($body['error'])) {
                    throw new \Exception("Could not get couponData due to error: " . $body['error']);
                }
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
                        "headers" => $this->getHttpHeaders(null),
                        "cookies" => $cookieJar,
                    ]
                );
                $this->checkResponse($response);
                $monthlyUsage = $this->parseMonthlyUsagePage((string)$response->getBody());

                $this->logger?->info("Fetching daily usage page...");
                $response = $client->get(
                    "/service/setup/hdc/viewdailydata/",
                    [
                        "headers" => $this->getHttpHeaders(null),
                        "cookies" => $cookieJar,
                    ]
                );
                $this->checkResponse($response);
                $dailyUsage = $this->parseDailyUsagePage((string)$response->getBody());

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

    private function getHttpHeaders(?string $contentType): array
    {
        $result = [
            // これを与えないと、HTMLが結構変わったり、検索時の書籍名がより短い（モバイル向け？）ものになる
            "User-Agent" => "Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Mobile Safari/537.36",
        ];

        if (!empty($contentType)) {
            $result["Content-Type"] = $contentType;
        }

        return $result;
    }

    private function checkResponse($response): void
    {
        if (!in_array($response->getStatusCode(), [200])) {
            throw new \Exception("Request error. [" . $response->getStatusCode() . "] " . $response->getReasonPhrase());
        }
    }

    private function parseMonthlyUsagePage(string $content): array
    {
        // 不要部分カット
        $content = preg_replace('/<h1>データ利用量照会（月別）<\/h1>/m', "", $content);

        $result = [];
        // ユーザーごとに分割
        $contentUsers = explode('<div class="viewdata">', $content);
        foreach ($contentUsers as $idx => $contentUser) {
            if ($idx === 0) {
                continue;
            }

            preg_match('/<input id="hdoCode" name="hdoCode" value="(hdo[0-9]+?)" type="hidden" value=""\/>/', $contentUser, $matches);
            if (!$matches || count($matches) < 2) {
                throw new \Exception("Could not get hdoCode usage: " . $contentUser);
            }
            $hdoCode = $matches[1];

            preg_match('/<td class="viewdata-detail-cell2">[\s]*?([0-9\.]+)GB[\s]*<\/td>/m', $contentUser, $matches);
            if (!$matches || count($matches) < 2) {
                throw new \Exception("Could not get monthly usage: " . $contentUser);
            }
            $usage = (float)$matches[1];

            $result[$hdoCode] = $usage;
        }

        return $result;
    }

    private function parseDailyUsagePage(string $content): array
    {
        // 不要部分カット
        $content = preg_replace('/<h1>データ利用量照会<\/h1>/m', "", $content);

        $result = [];
        // ユーザーごとに分割
        $contentUsers = explode('<div class="viewdata">', $content);
        foreach ($contentUsers as $idx => $contentUser) {
            if ($idx === 0) {
                continue;
            }

            preg_match('/<input id="hdoCode" name="hdoCode" value="(hdo[0-9]+?)" type="hidden" value=""\/>/', $contentUser, $matches);
            if (!$matches || count($matches) < 2) {
                throw new \Exception("Could not get hdoCode usage: " . $contentUser);
            }
            $hdoCode = $matches[1];

            preg_match('/<td class="viewdata-detail-cell2">[\s]*?([0-9\.]+)MB[\s]*<\/td>/m', $contentUser, $matches);
            if (!$matches || count($matches) < 2) {
                throw new \Exception("Could not get daily usage: " . $contentUser);
            }
            $usage = ((float)$matches[1] / 1000);  // MB -> GB

            $result[$hdoCode] = $usage;
        }

        return $result;
    }

    private function getUserName(string $user): string
    {
        if (isset($this->iijmioConfig->users->$user)) {
            $userInfo = $this->iijmioConfig->users->$user;
            if (is_object($userInfo) && isset($userInfo->name)) {
                return (string)$userInfo->name;
            }
            if (is_array($userInfo) && isset($userInfo['name'])) {
                return (string)$userInfo['name'];
            }
            if (is_string($userInfo)) {
                return $userInfo;
            }
        }
        return $user;
    }

    private function getUserPlanDataVolume(string $user): float
    {
        if (isset($this->iijmioConfig->users->$user)) {
            $userInfo = $this->iijmioConfig->users->$user;
            if (is_object($userInfo) && isset($userInfo->plan_data_volume)) {
                return (float)$userInfo->plan_data_volume;
            }
            if (is_array($userInfo) && isset($userInfo['plan_data_volume'])) {
                return (float)$userInfo['plan_data_volume'];
            }
        }
        return 0.0;
    }

    private function judgeResult(array $remainingDataVolume, array $monthlyUsages, array $dailyUsages): array
    {
        $summary = $this->buildSummary($remainingDataVolume, $monthlyUsages, $dailyUsages);
        return [$summary['isSend'], $summary['message']];
    }

    /**
     * @param array<string, float> $remainingDataVolume
     * @param array<string, float> $monthlyUsages
     * @param array<string, float> $dailyUsages
     * @return array<string, mixed>
     */
    public function buildSummary(array $remainingDataVolume, array $monthlyUsages, array $dailyUsages): array
    {
        $totalRemainingDataVolume = array_sum($remainingDataVolume);
        [$estimateUsage, $estimateDetails] = $this->estimateThisMonthUsage($monthlyUsages);

        $planDataVolume = 0.0;
        if (isset($this->iijmioConfig->users)) {
            foreach ($this->iijmioConfig->users as $user => $userInfo) {
                $planDataVolume += $this->getUserPlanDataVolume((string)$user);
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
        $usersList = [];
        foreach ($monthlyUsages as $user => $monthlyUsage) {
            $userKey = (string)$user;
            $monthlyUsageStr = sprintf("%.1f", $monthlyUsage);
            $dailyUsageVal = $dailyUsages[$userKey] ?? 0.0;
            $dailyUsageStr = sprintf("%.1f", $dailyUsageVal);
            $userName = $this->getUserName($userKey);
            $userPlanVol = $this->getUserPlanDataVolume($userKey);
            $estimatedUserUsageVal = $estimateDetails[$userKey]['estimatedUserUsage'] ?? 0.0;

            $thisMonthUsageList[] = "  {$userName}: {$monthlyUsageStr}GB  (+{$dailyUsageStr})";

            $usersList[] = [
                'code' => $userKey,
                'name' => $userName,
                'currentUsage' => round($monthlyUsage, 2),
                'dailyUsage' => round($dailyUsageVal, 2),
                'estimatedUserUsage' => round($estimatedUserUsageVal, 2),
                'planDataVolume' => round($userPlanVol, 2),
            ];
        }

        $thisMonthUsageListStr = implode("\n", $thisMonthUsageList);
        $thisMonthTotalUsageVal = array_sum($monthlyUsages);
        $thisMonthTotalUsage = sprintf("%.1f", $thisMonthTotalUsageVal);
        $dailyTotalUsageVal = array_sum($dailyUsages);
        $dailyTotalUsage = sprintf("%.1f", $dailyTotalUsageVal);
        $thisMonthTotalUsageRate = $planDataVolume > 0 ? (int)round($thisMonthTotalUsageVal / $planDataVolume * 100, 0) : 0;
        $estimateUsageRate = $planDataVolume > 0 ? (int)round($estimateUsage / $planDataVolume * 100, 0) : 0;
        $planDataVolumeStr = sprintf("%.1f", $planDataVolume);
        $totalRemainingDataVolumeStr = sprintf("%.1f", $totalRemainingDataVolume);

        $remainingConsumption = $estimateUsage - $thisMonthTotalUsageVal;
        $remainingConsumptionStr = sprintf("%.1f", $remainingConsumption);

        $shortageOrSurplus = $totalRemainingDataVolume - $remainingConsumption;
        $shortageOrSurplusStr = sprintf("%.1f", $shortageOrSurplus);

        $remainingDays = $now->daysInMonth() - $now->day;

        $detailList = [];
        $totalDailyRateVal = 0.0;
        foreach ($estimateDetails as $user => $detail) {
            $userName = $this->getUserName((string)$user);
            $dailyRateVal = (float)($detail['avgConsumptionPerDay'] ?? 0.0);
            $totalDailyRateVal += $dailyRateVal;

            $dailyRateStr = sprintf("%.1f", $dailyRateVal);
            $weeklyRateStr = sprintf("%.1f", $dailyRateVal * 7);
            $estimatedUserUsageStr = sprintf("%.1f", $detail['estimatedUserUsage']);

            $detailList[] = "  {$userName}: {$dailyRateStr}/日 {$weeklyRateStr}/週 → {$estimatedUserUsageStr}GB";
        }

        $totalDailyRateStr = sprintf("%.1f", $totalDailyRateVal);
        $totalWeeklyRateStr = sprintf("%.1f", $totalDailyRateVal * 7);
        $estimateUsageStr = sprintf("%.1f", $estimateUsage);

        $detailList[] = "  TOTAL: {$totalDailyRateStr}/日 {$totalWeeklyRateStr}/週 → {$estimateUsageStr}GB";

        $detailStr = implode("\n", $detailList);

        $webStr = !empty($this->webUrl) ? "\n\nWeb: " . trim($this->webUrl) : "";

        $message = <<<EOT
{$subject}

Usage:
{$thisMonthUsageListStr}
  TOTAL: {$thisMonthTotalUsage}GB  (+{$dailyTotalUsage}, {$thisMonthTotalUsageRate}%)

EoM: {$estimateUsage}GB  ({$estimateUsageRate}%)
Plan: {$planDataVolumeStr}GB
Left: {$totalRemainingDataVolumeStr}GB
残り消費予定: {$remainingConsumptionStr}GB
過不足予定: {$shortageOrSurplusStr}GB

[予測根拠] (残り{$remainingDays}日)
{$detailStr}{$webStr}
EOT;

        return [
            'isSend' => $isSend,
            'message' => $message,
            'monthlyUsages' => $monthlyUsages,
            'dailyUsages' => $dailyUsages,
            'planDataVolume' => round($planDataVolume, 2),
            'thisMonthTotalUsage' => round($thisMonthTotalUsageVal, 2),
            'dailyTotalUsage' => round($dailyTotalUsageVal, 2),
            'totalRemainingDataVolume' => round($totalRemainingDataVolume, 2),
            'estimateUsage' => round($estimateUsage, 2),
            'remainingConsumption' => round($remainingConsumption, 2),
            'shortageOrSurplus' => round($shortageOrSurplus, 2),
            'users' => $usersList,
        ];
    }

    private function estimateThisMonthUsage(array $monthlyUsage): array
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
            $userKey = (string)$user;
            // Baseline Rate calculation (R_baseline)
            $rPrev = null;
            if (!empty($prevHistory)) {
                $prevDateStr = (string)array_key_first($prevHistory);
                $prevUsages = $prevHistory[$prevDateStr];
                $prevUserUsage = null;
                if (is_object($prevUsages) && isset($prevUsages->$userKey)) {
                    $prevUserUsage = (float)$prevUsages->$userKey;
                } elseif (is_array($prevUsages) && isset($prevUsages[$userKey])) {
                    $prevUserUsage = (float)$prevUsages[$userKey];
                }

                if ($prevUserUsage !== null) {
                    $prevRecordDay = (new Carbon($prevDateStr, timezone: Consts::TIMEZONE))->day;
                    $rPrev = $prevUserUsage / $prevRecordDay;
                }
            }

            $userPlanVolume = $this->getUserPlanDataVolume($userKey);

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
                if (is_object($usages) && isset($usages->$userKey)) {
                    $userPastUsage = (float)$usages->$userKey;
                } elseif (is_array($usages) && isset($usages[$userKey])) {
                    $userPastUsage = (float)$usages[$userKey];
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

            $this->logger?->info("User {$userKey}: cumulative rate = {$rCumulative}GB/day, recent rate = " . ($rRecent !== null ? "{$rRecent}" : "N/A") . "GB/day, baseline rate = {$rBaseline}GB/day ({$baselineSource}), current weight = {$wCurrent}, projected rate = {$rProjected}GB/day. Estimated = {$estimatedUserUsage}GB");

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
            $details[$userKey] = $detail;
        }

        return [round($totalEstimated, 1), $details];
    }
}
