<?php declare(strict_types=1);

namespace MyApp;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
// use yananob\MyTools\Logger;

final class IijmioUsage
{
    public function __construct(private object $iijmioConfig, private int $sendEachNDays = 10) {
    }

    public function getStats(): array
    {
        [$remainingDataVolume, $monthlyUsage] = $this->__crawl();
        return $this->__judgeResult($remainingDataVolume, $monthlyUsage);
    }

    private function __crawl(): array
    {
        $client = new Client([
            'base_uri' => 'https://www.iijmio.jp/',
            'timeout'  => 30.0,
        ]);
        $cookieJar = new CookieJar();

        $response = $client->get(
            "/member/",
            [
                "headers" => $this->__getHttpHeaders(null),
                "cookies" => $cookieJar,
            ]
        );
        $this->__checkResponse($response);
        // var_dump($response);

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
        // var_dump($response);
        $body = json_decode((string)$response->getBody(), true);
        if (empty($body["serviceInfoList"][0]["couponData"])) {
            throw new \Exception("Could not get couponData: " . var_export($body, true));
        }
        $remainingDataVolume = [];
        foreach (json_decode((string)$response->getBody(), true)["serviceInfoList"][0]["couponData"] as $couponData) {
            $remainingDataVolume[$couponData["month"]] = $couponData["couponValue"];
        }

        $response = $client->get(
            "/service/setup/hdc/viewmonthlydata/",
            [
                "headers" => $this->__getHttpHeaders(null),
                "cookies" => $cookieJar,
            ]
        );
        $this->__checkResponse($response);
        // var_dump((string)$response->getBody());
        $monthlyUsage = $this->__parseMonthlyUsagePage((string)$response->getBody());

        return [$remainingDataVolume, $monthlyUsage];
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
            $usage = $matches[1];

            $result[$hdoCode] = $usage;
        }

        return $result;
    }

    private function __judgeResult(array $remainingDataVolume, array $monthlyUsages): array
    {
        $totalRemainingDataVolume = array_sum($remainingDataVolume);
        $estimateUsage = $this->__estimateThisMonthUsage($monthlyUsages);

        $isSend = false;
        if ($estimateUsage > $this->iijmioConfig->plan_data_volume * 0.9) {
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
            $thisMonthUsageList[] = "  {$this->iijmioConfig->users->$user} : {$monthlyUsage}GB";
        }
        $thisMonthUsageList = implode("\n", $thisMonthUsageList);
        $thisMonthTotalUsage = sprintf("%.1f", array_sum($monthlyUsages));
        $thisMonthTotalUsageRate = round($thisMonthTotalUsage / $totalRemainingDataVolume * 100, 0);
        $estimateUsageRate = round($estimateUsage / $totalRemainingDataVolume * 100, 0);
        $planDataVolume = sprintf("%.1f", $this->iijmioConfig->plan_data_volume);
        $totalRemainingDataVolume = sprintf("%.1f", $totalRemainingDataVolume);

        $message = <<<EOT
{$subject}

Usage:
{$thisMonthUsageList}
  TOTAL: {$thisMonthTotalUsage}GB  ({$thisMonthTotalUsageRate}%)

Estimation: {$estimateUsage}GB  ({$estimateUsageRate}%)
Plan      : {$planDataVolume}GB
Remaining : {$totalRemainingDataVolume}GB
EOT;

        return [$isSend, $message];
    }

    private function __estimateThisMonthUsage(array $monthlyUsage): float
    {
        $now = new Carbon(timezone: Consts::TIMEZONE);
        return round(array_sum($monthlyUsage) * $now->daysInMonth() / $now->day, 1);
    }

}
