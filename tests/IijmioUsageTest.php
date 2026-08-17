<?php declare(strict_types=1);

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use App\Utils\Test;
use App\Consts;
use App\IijmioUsage;

final class IijmioUsageTest extends TestCase
{
    private object $config;

    public function setUp(): void
    {
        parent::setUp();
        $configPath = __DIR__ . "/config.json.test";
        $content = file_get_contents($configPath);
        if ($content === false) {
            throw new \RuntimeException("Test config not found: {$configPath}");
        }
        $this->config = (object)json_decode($content, false);
    }

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
残り消費予定: 3.3GB
過不足予定: -3.2GB

[予測根拠] (残り19日)
  user1: 0.9GB (+0.1) → 2.5GB
  user2: 1.0GB (+0.2) → 2.7GB
  TOTAL: 1.9GB (+0.3) → 5.2GB
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
        $this->assertStringContainsString("残り消費予定: 1.0GB\n過不足予定: -5.5GB", $message);
        $this->assertStringContainsString("[予測根拠] (残り10日)\n  user1: 0.9GB (+0.1) → 1.4GB\n  user2: 1.0GB (+0.2) → 1.5GB\n  TOTAL: 1.9GB (+0.3) → 2.9GB", $message);

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
残り消費予定: 4.4GB
過不足予定: -2.1GB

[予測根拠] (残り21日)
  user1: 0.9GB (+0.1) → 3.0GB
  user2: 1.0GB (+0.2) → 3.3GB
  TOTAL: 1.9GB (+0.3) → 6.3GB
EOT;
        $this->assertEquals($expectedMessage, $message);
    }

    public function testEstimateThisMonthUsage(): void
    {
        Carbon::setTestNow(new Carbon('2024-11-10 12:00:00', timezone: Consts::TIMEZONE));

        $iijmio = new IijmioUsage(iijmioConfig: $this->config->iijmio);
        [$result, $details] = Test::invokePrivateMethod(
            $iijmio,
            "__estimateThisMonthUsage",
            ["hdo12345678" => 1.1, "hdo22345678" => 2.2]
        );

        $this->assertSame(9.9, $result);
        $this->assertCount(2, $details);
        $this->assertSame('blended', $details['hdo12345678']['type']);
        $this->assertSame(10, $details['hdo12345678']['currentDay']);
        $this->assertSame(1.1, $details['hdo12345678']['currentUsage']);
        $this->assertSame(0.11, $details['hdo12345678']['avgConsumptionPerDay']);
        $this->assertSame(20, $details['hdo12345678']['remainingDays']);
    }

    public function testEstimateThisMonthUsageWithHistory(): void
    {
        Carbon::setTestNow(new Carbon('2024-11-10 12:00:00', timezone: Consts::TIMEZONE));

        $history = [
            "2024-11-06" => [
                "hdo12345678" => 0.7,
                "hdo22345678" => 1.8
            ],
            "2024-10-31" => [
                "hdo12345678" => 0.5,
                "hdo22345678" => 1.5
            ]
        ];

        $iijmio = new IijmioUsage(
            iijmioConfig: $this->config->iijmio,
            history: $history
        );

        [$result, $details] = Test::invokePrivateMethod(
            $iijmio,
            "__estimateThisMonthUsage",
            ["hdo12345678" => 1.1, "hdo22345678" => 2.2]
        );

        $this->assertSame(8.6, $result);
        $this->assertSame('blended', $details['hdo12345678']['type']);
        $this->assertSame('11/06', $details['hdo12345678']['pastDate']);
        $this->assertSame(4, $details['hdo12345678']['dayDiff']);
        $this->assertSame(0.105, $details['hdo12345678']['avgConsumptionPerDay']);
        $this->assertSame(20, $details['hdo12345678']['remainingDays']);
    }

    public function testEstimateThisMonthUsageWithNegativeConsumptionHistory(): void
    {
        Carbon::setTestNow(new Carbon('2024-11-10 12:00:00', timezone: Consts::TIMEZONE));

        $history = [
            "2024-11-06" => [
                "hdo12345678" => 1.5,
            ]
        ];

        $iijmio = new IijmioUsage(
            iijmioConfig: $this->config->iijmio,
            history: $history
        );

        [$result, $details] = Test::invokePrivateMethod(
            $iijmio,
            "__estimateThisMonthUsage",
            ["hdo12345678" => 1.1, "hdo22345678" => 2.2]
        );

        $this->assertSame(8.8, $result);
        $this->assertSame('blended', $details['hdo12345678']['type']);
        $this->assertSame(0.055, $details['hdo12345678']['avgConsumptionPerDay']);
    }

    public function testEstimateThisMonthUsageWithMultipleHistory(): void
    {
        Carbon::setTestNow(new Carbon('2024-11-15 12:00:00', timezone: Consts::TIMEZONE));

        $history = [
            "2024-11-14" => [ // 1 day ago
                "hdo12345678" => 1.4,
            ],
            "2024-11-10" => [ // 5 days ago
                "hdo12345678" => 1.0,
            ],
            "2024-11-08" => [ // 7 days ago (target)
                "hdo12345678" => 0.8,
            ],
            "2024-11-06" => [ // 9 days ago
                "hdo12345678" => 0.6,
            ]
        ];

        $iijmio = new IijmioUsage(
            iijmioConfig: $this->config->iijmio,
            history: $history
        );

        [$result, $details] = Test::invokePrivateMethod(
            $iijmio,
            "__estimateThisMonthUsage",
            ["hdo12345678" => 1.5, "hdo22345678" => 2.0]
        );

        $this->assertSame('blended', $details['hdo12345678']['type']);
        $this->assertSame('11/08', $details['hdo12345678']['pastDate']);
        $this->assertSame(7, $details['hdo12345678']['dayDiff']);
    }

    public function testEstimateThisMonthUsageWithHistoryTieBreaking(): void
    {
        Carbon::setTestNow(new Carbon('2024-11-15 12:00:00', timezone: Consts::TIMEZONE));

        $history = [
            "2024-11-09" => [ // 6 days ago
                "hdo12345678" => 0.9,
            ],
            "2024-11-07" => [ // 8 days ago (larger dayDiff tie-breaker)
                "hdo12345678" => 0.7,
            ]
        ];

        $iijmio = new IijmioUsage(
            iijmioConfig: $this->config->iijmio,
            history: $history
        );

        [$result, $details] = Test::invokePrivateMethod(
            $iijmio,
            "__estimateThisMonthUsage",
            ["hdo12345678" => 1.5, "hdo22345678" => 2.0]
        );

        $this->assertSame('blended', $details['hdo12345678']['type']);
        $this->assertSame('11/07', $details['hdo12345678']['pastDate']);
        $this->assertSame(8, $details['hdo12345678']['dayDiff']);
    }

    public function testEstimateThisMonthUsageWithBaselineAndPreviousMonth(): void
    {
        // 11月4日 (経過日数 T = 4)
        Carbon::setTestNow(new Carbon('2024-11-04 12:00:00', timezone: Consts::TIMEZONE));

        $history = [
            "2024-10-31" => [ // 前月の最終履歴レコード (31日)
                "hdo12345678" => 3.1,
                "hdo22345678" => 6.2
            ]
        ];

        $iijmio = new IijmioUsage(
            iijmioConfig: $this->config->iijmio,
            history: $history
        );

        [$result, $details] = Test::invokePrivateMethod(
            $iijmio,
            "__estimateThisMonthUsage",
            ["hdo12345678" => 0.8, "hdo22345678" => 1.6]
        );

        // hdo12345678 の期待値計算:
        // T = 4, wCurrent = (4-1)/7 = 3/7 = 0.4285714
        // rCumulative = 0.8 / 4 = 0.2
        // rBaseline (前月実績) = 3.1 / 31 = 0.1
        // rCurrentBlended = rCumulative = 0.2
        // rProjected = 0.4285714 * 0.2 + (1 - 0.4285714) * 0.1 = 0.142857
        // estimated = 0.8 + 0.142857 * 26 = 4.514

        // hdo22345678 の期待値計算:
        // rCumulative = 1.6 / 4 = 0.4
        // rBaseline (前月実績) = 6.2 / 31 = 0.2
        // rCurrentBlended = rCumulative = 0.4
        // rProjected = 0.4285714 * 0.4 + (1 - 0.4285714) * 0.2 = 0.285714
        // estimated = 1.6 + 0.285714 * 26 = 9.028

        // Total = 4.514 + 9.028 = 13.542 => round(13.5, 1) = 13.5
        $this->assertSame(13.5, $result);
        $this->assertSame(0.1429, $details['hdo12345678']['avgConsumptionPerDay']);
        $this->assertSame(0.2857, $details['hdo22345678']['avgConsumptionPerDay']);
        $this->assertSame('previous_month', $details['hdo12345678']['baselineSource']);
    }
}