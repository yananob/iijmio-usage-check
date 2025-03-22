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
        $this->config = Utils::getConfig(path: __DIR__ . "/config.json.test", asArray: false);
    }

    // public function testCrawl(): void
    // {
    //     $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio);
        // REPLACE $this->config->iijmio->mio_io and password to test
    //     $result = Test::invokePrivateMethod($iijmio, "__crawl");
    //     $this->assertNotEmpty($result);
    // }

    public function testParseMonthlyUsagePage(): void
    {
        $content = file_get_contents(__DIR__ . "/data/monthly_usage.html");
        $this->assertNotFalse($content);
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio);
        $result = Test::invokePrivateMethod($iijmio, "__parseMonthlyUsagePage", $content);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey("hdo12345678", $result);
        $this->assertSame(5.3, $result["hdo12345678"]);
        $this->assertSame(1.64, $result["hdo22345678"]);
    }

    public function testParseDailyUsagePage(): void
    {
        $content = file_get_contents(__DIR__ . "/data/daily_usage.html");
        $this->assertNotFalse($content);
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio);
        $result = Test::invokePrivateMethod($iijmio, "__parseDailyUsagePage", $content);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey("hdo12345678", $result);
        $this->assertSame(0.189, $result["hdo12345678"]);
        $this->assertSame(0.008, $result["hdo22345678"]);
    }

    public function testJudgeResult(): void
    {
        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio, sendEachNDays: 5);

        // アラートなし（メール送信なし）
        Carbon::setTestNow(new Carbon('2024-11-11 12:00:00', timezone: Consts::TIMEZONE));
        [$isSendAlert, $message] = Test::invokePrivateMethod(
            $iijmio,
            "__judgeResult",
            ["202411" => 0.5, "202412" => 6.0],
            ["hdo12345678" => 0.9, "hdo22345678" => 1.0],
            ["hdo12345678" => 0.1, "hdo22345678" => 0.2],
        );

        $this->assertFalse($isSendAlert);
        $expectedMessage = <<<EOT
[INFO] Mobile usage report

Usage:
  user1: 0.9GB  (+0.1)
  user2: 1.0GB  (+0.2)
  TOTAL: 1.9GB  (+0.3, 32%)

EoM: 5.2GB  (87%)
Plan: 6.0GB
Left: 6.5GB
EOT;
        $this->assertEquals($expectedMessage, $message);

        // アラートなし（定期メール送信あり）
        Carbon::setTestNow(new Carbon('2024-11-20 12:00:00', timezone: Consts::TIMEZONE));
        [$isSendAlert, $message] = Test::invokePrivateMethod(
            $iijmio,
            "__judgeResult",
            ["202411" => 1.5, "202412" => 5.0],
            ["hdo12345678" => 0.9, "hdo22345678" => 1.0],
            ["hdo12345678" => 0.1, "hdo22345678" => 0.2],
        );
        $this->assertTrue($isSendAlert);

        // アラートあり（使用量同じだが、日付がまだ月初に近い）
        Carbon::setTestNow(new Carbon('2024-11-09 12:00:00', timezone: Consts::TIMEZONE));
        [$isSendAlert, $message] = Test::invokePrivateMethod(
            $iijmio,
            "__judgeResult",
            ["202411" => 0.5, "202412" => 6.0],
            ["hdo12345678" => 0.9, "hdo22345678" => 1.0],
            ["hdo12345678" => 0.1, "hdo22345678" => 0.2],
        );

        $this->assertTrue($isSendAlert);
        $expectedMessage = <<<EOT
[WARN] Mobile usage is not good

Usage:
  user1: 0.9GB  (+0.1)
  user2: 1.0GB  (+0.2)
  TOTAL: 1.9GB  (+0.3, 32%)

EoM: 6.3GB  (105%)
Plan: 6.0GB
Left: 6.5GB
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
            ["hdo12345678" => 0.1, "hdo22345678" => 0.2],
        );

        $this->assertNotEmpty($result);
        $this->assertSame(9.9, $result);
    }

}
