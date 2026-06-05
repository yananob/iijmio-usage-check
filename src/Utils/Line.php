<?php declare(strict_types=1);

namespace MyApp\Utils;

use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;

final class Line
{
    private MessagingApiApi $apiInstance;

    public function __construct(string $accessToken)
    {
        $config = new Configuration();
        $config->setAccessToken($accessToken);

        $this->apiInstance = new MessagingApiApi(
            config: $config
        );
    }

    public function sendPush(string $target, string $message): void
    {
        $request = new PushMessageRequest([
            'to' => $target,
            'messages' => [
                new TextMessage([
                    'type' => 'text',
                    'text' => $message,
                ]),
            ],
        ]);

        $this->apiInstance->pushMessage($request);
    }
}
