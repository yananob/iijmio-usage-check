<?php declare(strict_types=1);

namespace MyApp;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use yananob\MyTools\Logger;

final class IijmioUsage
{
    private $debugDate;
    private Logger $logger;

    public function __construct(private object $iijmioConfig, private int $sendEachNDays)
    {
        $this->logger = new Logger(get_class($this));
    }

    // public function setDebugDate(string $debugDate): void
    // {
    //     $this->debugDate = date_create($debugDate);
    // }

    public function getStats(): array
    {
        $json = $this->__crawl();
        return $this->__judgeResult($json);
    }

    private function __crawl(): string
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

        // $response = $client->get(
        //     "/auth/login/",
        //     [
        //         "headers" => $this->__getHttpHeaders(),
        //         "cookies" => $cookieJar,
        //     ]
        // );
        // $this->__checkResponse($response);
        // // var_dump($response);

        // $response = $client->post(
        //     "/api/front/loginInfo",
        //     [
        //         "headers" => $this->__getHttpHeaders(),
        //         "cookies" => $cookieJar,
        //     ]
        // );
        // $this->__checkResponse($response);
        // var_dump((string)$response->getBody());

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
        // var_dump($response);

        // $response = $client->post(
        //     "/api/member/getPermissionInfo",
        //     [
        //         "headers" => $this->__getHttpHeaders(),
        //         "cookies" => $cookieJar,
        //     ]
        // );
        // $this->__checkResponse($response);
        // var_dump($response);

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
        var_dump((string)$response->getBody());

        return (string)$response->getBody();
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

    private function __judgeResult($packetInfo): array
    {
        $today = date("Ymd");
        $today_day = (int)date("d");
        $this_month = date("Ym");
        if ($this->debugDate != null) {
            $today = $this->debugDate->format("Ymd");
            $today_day = (int)$this->debugDate->format("d");
            $this_month = $this->debugDate->format("Ym");
        }

        $monthly_usage = 0;
        $today_usages = [];

        foreach ($packetInfo->packetLogInfo as $hdd_info) {
            foreach ($hdd_info->hdoInfo as $hdo_info) {
                foreach ($hdo_info->packetLog as $daily_info) {
                    if ($this_month == date_format(date_create($daily_info->date), "Ym")) {
                        $monthly_usage += $daily_info->withCoupon;
                    }

                    // todo: store today's info for each person
                    if ($today == $daily_info->date) {
                        $today_usages[$hdo_info->hdoServiceCode] = $daily_info->withCoupon;
                    }
                }
            }
        }

        $isSend = False;
        $isWarning = False;
        $max_usage = $this->iijmioConfig->max_usage;

        $today_usage_list = "";
        $today_usage_total = 0;
        foreach ($today_usages as $hdo_user => $usage) {
            $today_usage_list .= "  {$this->iijmioConfig->users->$hdo_user}: {$usage}MB\n";
            $today_usage_total += $usage;
        }

        $monthly_estimate_usage = round($monthly_usage * 31 / $today_day);
        $monthly_usage_rate = round($monthly_usage / $max_usage * 100);
        $monthly_estimate_usage_rate = round($monthly_estimate_usage / $max_usage * 100);

        if ($monthly_estimate_usage >= $max_usage) {
            $isSend = True;
            $isWarning = True;
        }
        if ($today_day % $this->sendEachNDays == 0) {
            $isSend = True;
        }
        $subject = $isWarning ? "[WARN] Mobile usage is not good" : "[INFO] Mobile usage report";

        $message = <<<EOT
{$subject}

Today [{$today}]
{$today_usage_list}
  TOTAL: {$today_usage_total}MB

Now: {$monthly_usage}MB  ({$monthly_usage_rate}%)
Estimate: {$monthly_estimate_usage}MB  ({$monthly_estimate_usage_rate}%)
EOT;

        return [
            "isSend" => $isSend,
            "message" => $message
        ];
    }
}
