<?php declare(strict_types=1);

namespace MyApp\Utils;

use GuzzleHttp\Client;

final class Line
{
    private array $config;

    public function __construct(string $configPath)
    {
        $this->config = Utils::getConfig($configPath, true);
    }

    public function sendPush(string $bot, string $target, string $message): void
    {
        if (!isset($this->config[$bot])) {
            throw new \RuntimeException("Bot config not found: {$bot}");
        }

        $accessToken = $this->config[$bot]['access_token'];

        $client = new Client([
            'base_uri' => 'https://api.line.me/v2/bot/message/',
            'timeout'  => 10.0,
        ]);

        $client->post('push', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'json' => [
                'to' => $target,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $message,
                    ],
                ],
            ],
        ]);
    }
}
