<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use MyApp\IijmioUsage;
use MyApp\common\Utils;

final class IijmioUsageTest extends TestCase
{
    // can't call with dummy developer id
    // public function testCallApi(): void
    // {
    //     $config = Utils::getConfig(dirname(__FILE__) . "/../configs/config.json");
    //     $iijmio = new IijmioUsage(
    //         $config["iijmio"], 5
    //     );
    //     $result = $iijmio->callApi();
    //     $this->assertNotNull($result);
    // }

    public function testJudgeResult(): void
    {
        $contents = file_get_contents(dirname(__FILE__) . "/usage_data.json");
        $contents_json = json_decode($contents);

        // OK case
        $iijmio = new IijmioUsage([
            "developer_id" => "dummy",
            "token" => "dummy",
            "users" => [
                "hdo12345601" => "user1",
                "hdo12345602" => "user2"
            ],
            "max_usage" => 730
        ], 10);
        $iijmio->setDebugDate("2023/07/15");
        $alert_info = $iijmio->judgeResult($contents_json);

        $this->assertFalse($alert_info["isSend"]);
        $message = <<<EOT
[INFO] Mobile usage report

Today [20230715]
  user1: 50MB
  user2: 60MB

  TOTAL: 110MB

Now: 350MB  (48%)
Estimate: 723MB  (99%)
EOT;
        $this->assertEquals(
            $message,
            $alert_info["message"]
        );

        // NG case
        $iijmio = new IijmioUsage([
            "developer_id" => "dummy",
            "token" => "dummy",
            "users" => [
                "hdo12345601" => "user1",
                "hdo12345602" => "user2"
            ],
            "max_usage" => 600
        ], 10);
        $iijmio->setDebugDate("2023/07/15");
        $alert_info = $iijmio->judgeResult($contents_json);

        $this->assertTrue($alert_info["isSend"]);
        $message = <<<EOT
[WARN] Mobile usage is not good

Today [20230715]
  user1: 50MB
  user2: 60MB

  TOTAL: 110MB

Now: 350MB  (58%)
Estimate: 723MB  (121%)
EOT;
        $this->assertEquals(
            $message,
            $alert_info["message"]
        );
    }
}
