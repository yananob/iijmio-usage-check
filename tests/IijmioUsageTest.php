<?php declare(strict_types=1);

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use MyApp\IijmioUsage;
use yananob\MyTools\Utils;
use yananob\MyTools\Test;

final class IijmioUsageTest extends TestCase
{
    private object $config;

    public function setUp(): void
    {
        parent::setUp();
        $this->config = Utils::getConfig(path: __DIR__ . "/../configs/config.json", asArray: false);
    }

    public function testCrawl(): void
    {
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio, sendEachNDays: 1);
        $hoge = Test::invokePrivateMethod($iijmio, "__crawl");
        $this->assertEmpty($hoge);
    }

    public function testJudgeResult(): void
    {
        $contents = file_get_contents(dirname(__FILE__) . "/usage_data.json");
        $contents_json = json_decode($contents);

        Carbon::setTestNow(new Carbon('2023-07-15 12:00:00'));

        // OK case
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio, sendEachNDays: 1);
        $hoge = Test::invokePrivateMethod($iijmio, "__judgeResult");

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
