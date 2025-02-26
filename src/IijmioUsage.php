<?php declare(strict_types=1);

namespace MyApp;

final class IijmioUsage
{
    private array $iijmio_config;
    private int $send_each_n_days;
    private $debugDate;

    public function __construct(array $iijmio_config, int $send_each_n_days)
    {
        $this->iijmio_config = $iijmio_config;
        $this->send_each_n_days = $send_each_n_days;
        $this->debugDate = null;
    }

    public function setDebugDate(string $debugDate): void
    {
        $this->debugDate = date_create($debugDate);
    }

    public function callApi(): object
    {
        $headers = [
            "contentType: application/x-www-form-urlencoded",
            "X-IIJmio-Developer: {$this->iijmio_config['developer_id']}",
            "X-IIJmio-Authorization: {$this->iijmio_config['token']}",
        ];
        $ch = curl_init();
        curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => "https://api.iijmio.jp/mobile/d/v2/log/packet/",
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => $headers,
        ]);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($httpcode != "200") {
            throw new \Exception("Request error. Http response code: [{$httpcode}]");
        }
        curl_close($ch);
        return json_decode($result);
    }

    public function judgeResult($packetInfo): array
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
        $max_usage = $this->iijmio_config["max_usage"];

        $today_usage_list = "";
        $today_usage_total = 0;
        foreach ($today_usages as $hdo_user => $usage) {
            $today_usage_list .= "  {$this->iijmio_config['users'][$hdo_user]}: {$usage}MB\n";
            $today_usage_total += $usage;
        }

        $monthly_estimate_usage = round($monthly_usage * 31 / $today_day);
        $monthly_usage_rate = round($monthly_usage / $max_usage * 100);
        $monthly_estimate_usage_rate = round($monthly_estimate_usage / $max_usage * 100);

        if ($monthly_estimate_usage >= $max_usage) {
            $isSend = True;
            $isWarning = True;
        }
        if ($today_day % $this->send_each_n_days == 0) {
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
