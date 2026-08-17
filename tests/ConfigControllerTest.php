<?php declare(strict_types=1);

namespace Tests;

use App\Controllers\ConfigController;
use App\Services\MockConfigService;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ConfigControllerTest extends TestCase
{
    public function testHandleGetRequestMock(): void
    {
        $request = (new ServerRequest('GET', '/'))->withQueryParams(['mock' => '1']);
        $controller = new ConfigController();
        $html = $controller->handle($request);

        $this->assertStringContainsString('<title>IIJmio Usage Checker Config</title>', $html);
        $this->assertStringContainsString('MA1234567', $html);
        $this->assertStringContainsString('Alice', $html);
    }

    public function testHandlePostSaveMock(): void
    {
        $request = (new ServerRequest('POST', '/'))
            ->withQueryParams(['mock' => '1'])
            ->withParsedBody([
                'action' => 'save',
                'iijmio' => [
                    'mio_id' => 'MA9999999',
                    'password' => 'newpassword',
                    'users' => [
                        ['code' => 'hdo123', 'name' => 'Charlie', 'plan_data_volume' => '10']
                    ]
                ],
                'alert' => [
                    'bot' => 'TestBot',
                    'target' => 'TestTarget',
                    'send_usage_each_n_days' => '2'
                ]
            ]);

        $controller = new ConfigController();
        $html = $controller->handle($request);

        $this->assertStringContainsString('Config updated successfully. (Mock Save)', $html);
        $this->assertStringContainsString('MA9999999', $html);
        $this->assertStringContainsString('Charlie', $html);
    }

    public function testHandlePostPreviewMock(): void
    {
        $request = (new ServerRequest('POST', '/'))
            ->withQueryParams(['mock' => '1'])
            ->withParsedBody([
                'action' => 'preview',
                'iijmio' => [
                    'mio_id' => 'MA1234567',
                    'password' => 'secret',
                    'users' => [
                        ['code' => 'hdo11111111', 'name' => 'Alice', 'plan_data_volume' => '5']
                    ]
                ],
                'alert' => [
                    'bot' => 'MyLineBot',
                    'target' => 'MyGroup',
                    'send_usage_each_n_days' => '3'
                ]
            ]);

        $controller = new ConfigController();
        $html = $controller->handle($request);

        $this->assertStringContainsString('[IIJmioデータ利用状況]', $html);
    }

    public function testMockConfigServiceParseConfigFromParams(): void
    {
        $service = new MockConfigService();
        $parsed = $service->parseConfigFromParams([
            'iijmio' => [
                'mio_id' => 'MA5555555',
                'password' => 'pass123',
                'users' => [
                    ['code' => 'hdo111', 'name' => 'User1', 'plan_data_volume' => '2'],
                    ['code' => '', 'name' => 'InvalidUser'], // should be skipped
                ]
            ],
            'alert' => [
                'bot' => 'Bot1',
                'target' => 'Target1',
                'send_usage_each_n_days' => '5'
            ]
        ]);

        $expected = [
            'iijmio' => [
                'mio_id' => 'MA5555555',
                'password' => 'pass123',
                'users' => [
                    'hdo111' => [
                        'name' => 'User1',
                        'plan_data_volume' => 2.0
                    ]
                ]
            ],
            'alert' => [
                'bot' => 'Bot1',
                'target' => 'Target1',
                'send_usage_each_n_days' => 5
            ]
        ];

        $this->assertSame($expected, $parsed);
    }

    public function testConfigControllerWithInjectedService(): void
    {
        $mockService = new MockConfigService();
        $controller = new ConfigController($mockService);
        $request = new ServerRequest('GET', '/');
        $html = $controller->handle($request);

        $this->assertStringContainsString('<title>IIJmio Usage Checker Config</title>', $html);
        $this->assertStringContainsString('MA1234567', $html);
    }
}
