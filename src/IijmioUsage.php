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
        $estimateUsage = $this->__estimateThisMonthUsage($monthlyUsages);

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

        $message = <<<EOT
{$subject}

Usage:
{$thisMonthUsageList}
  TOTAL: {$thisMonthTotalUsage}GB  (+{$dailyTotalUsage}, {$thisMonthTotalUsageRate}%)

EoM: {$estimateUsage}GB  ({$estimateUsageRate}%)
Plan: {$planDataVolumeStr}GB
Left: {$totalRemainingDataVolume}GB
EOT;

        return [$isSend, $message];
    }

    private function __estimateThisMonthUsage(array $monthlyUsage): float
    {
        $now = new Carbon(timezone: Consts::TIMEZONE);
        $todayStr = $now->format('Y-m-d');
        $currentYearMonth = $now->format('Y-m');
        $daysInMonth = $now->daysInMonth();
        $currentDay = $now->day;

        $monthlyHistory = [];
        foreach ($this->history as $dateStr => $usages) {
            if (str_starts_with($dateStr, $currentYearMonth) && $dateStr < $todayStr) {
                $monthlyHistory[$dateStr] = $usages;
            }
        }
        krsort($monthlyHistory);

        $totalEstimated = 0.0;
        foreach ($monthlyUsage as $user => $currentUsage) {
            $estimatedUserUsage = null;
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
                        $consumption = $currentUsage - $userPastUsage;
                        $avgConsumptionPerDay = max(0.0, $consumption / $dayDiff);
                        $remainingDays = $daysInMonth - $currentDay;
                        $estimatedUserUsage = $currentUsage + ($avgConsumptionPerDay * $remainingDays);
                        $this->logger?->info("User {$user}: estimated using history of {$dateStr}. Past: {$userPastUsage}GB, Current: {$currentUsage}GB, Diff days: {$dayDiff}, Avg/Day: {$avgConsumptionPerDay}GB. Estimate: {$estimatedUserUsage}GB");
                        break;
                    }
                }
            }

            if ($estimatedUserUsage === null) {
                $estimatedUserUsage = ($currentUsage / $currentDay) * $daysInMonth;
                $this->logger?->info("User {$user}: no history found. Estimate using simple proportion: {$estimatedUserUsage}GB");
            }

            $totalEstimated += $estimatedUserUsage;
        }

        return round($totalEstimated, 1);
    }

}
