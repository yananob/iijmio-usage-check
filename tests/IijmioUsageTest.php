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
        $this->config = Utils::getConfig(path: __DIR__ . "/config.json.test", asArray: false);
    }

    public function testCrawl(): void
    {
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio, sendEachNDays: 1);
        $result = Test::invokePrivateMethod($iijmio, "__crawl");
        $this->assertNotEmpty($result);
    }

    public function testParseMonthlyUsagePage(): void
    {
        $content = file_get_contents(__DIR__ . "/data/data_usage.html");
        $this->assertNotFalse($content);
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio, sendEachNDays: 1);
        $result = Test::invokePrivateMethod($iijmio, "__parseMonthlyUsagePage", $content);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey("hdo12345678", $result);
        $this->assertSame("5.3", $result["hdo12345678"]);
        $this->assertSame("1.64", $result["hdo22345678"]);
    }

    public function testJudgeResult(): void
    {
        $content = file_get_contents(__DIR__ . "/data/data_usage.html");
        $this->assertNotFalse($content);
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio, sendEachNDays: 5);
        $monthlyUsage = Test::invokePrivateMethod($iijmio, "__parseMonthlyUsagePage", $content);

        // OK case
        Carbon::setTestNow(new Carbon('2023-07-14 12:00:00'));
        $result = Test::invokePrivateMethod($iijmio, "__judgeResult", $monthlyUsage);

        $this->assertFalse($result->isSendAlert);
        $message = <<<EOT
[INFO] Mobile usage report

Today [20230714]
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
        Carbon::setTestNow(new Carbon('2023-07-15 12:00:00'));
        $result = Test::invokePrivateMethod($iijmio, "__judgeResult", $monthlyUsage);

        $this->assertTrue($result->isSendAlert);
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
