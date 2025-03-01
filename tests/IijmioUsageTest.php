<?php declare(strict_types=1);

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use yananob\MyTools\Utils;
use yananob\MyTools\Test;
use MyApp\Consts;
use MyApp\IijmioUsage;

final class IijmioUsageTest extends TestCase
{
    private object $config;

    public function setUp(): void
    {
        parent::setUp();
        $this->config = Utils::getConfig(path: __DIR__ . "/config.json", asArray: false);
    }

    public function testCrawl(): void
    {
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio);
        $result = Test::invokePrivateMethod($iijmio, "__crawl");
        $this->assertNotEmpty($result);
    }

    public function testParseMonthlyUsagePage(): void
    {
        $content = file_get_contents(__DIR__ . "/data/data_usage.html");
        $this->assertNotFalse($content);
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio);
        $result = Test::invokePrivateMethod($iijmio, "__parseMonthlyUsagePage", $content);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey("hdo12345678", $result);
        $this->assertSame("5.3", $result["hdo12345678"]);
        $this->assertSame("1.64", $result["hdo22345678"]);
    }

    public function testJudgeResult(): void
    {
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio, sendEachNDays: 5);

        // OK case
        Carbon::setTestNow(new Carbon('2024-11-11 12:00:00', timezone: Consts::TIMEZONE));
        [$isSendAlert, $message] = Test::invokePrivateMethod(
            $iijmio,
            "__judgeResult",
            ["202411" => 1.5, "202412" => 5.0],
            ["hdo12345678" => 0.9, "hdo22345678" => 1.0],
        );

        $this->assertFalse($isSendAlert);
        $expectedMessage = <<<EOT
[INFO] Mobile usage report

Usage:
  user1: 0.9GB
  user2: 1.0GB
  TOTAL: 1.9GB  (29%)

Estimation: 5.2GB  (80%)
EOT;
        $this->assertEquals($expectedMessage, $message);

        // NG case
        Carbon::setTestNow(new Carbon('2024-11-09 12:00:00', timezone: Consts::TIMEZONE));
        [$isSendAlert, $message] = Test::invokePrivateMethod(
            $iijmio,
            "__judgeResult",
            ["202411" => 1.5, "202412" => 5.0],
            ["hdo12345678" => 0.9, "hdo22345678" => 1.0],
        );

        $this->assertTrue($isSendAlert);
        $expectedMessage = <<<EOT
[WARN] Mobile usage is not good

Usage:
  user1: 0.9GB
  user2: 1.0GB
  TOTAL: 1.9GB  (29%)

Estimation: 6.3GB  (97%)
EOT;
        $this->assertEquals($expectedMessage, $message);
    }

    public function testEstimateThisMonthUsage(): void
    {
        Carbon::setTestNow(new Carbon('2024-11-10 12:00:00', timezone: Consts::TIMEZONE));

        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio);
        $result = Test::invokePrivateMethod(
            $iijmio,
            "__estimateThisMonthUsage",
            ["hdo12345678" => 1.1, "hdo22345678" => 2.2],
        );

        $this->assertNotEmpty($result);
        $this->assertSame(9.9, $result);
    }

}
