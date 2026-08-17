<?php declare(strict_types=1);

namespace App\Controllers;

use App\AppConfig;
use App\Firestore;
use App\IijmioUsage;
use App\Utils\Logger;
use eftec\bladeone\BladeOne;
use Psr\Http\Message\ServerRequestInterface;

final class ConfigController
{
    public function handle(ServerRequestInterface $request): string
    {
        $isMock = ($request->getQueryParams()['mock'] ?? false) || (getenv('MOCK_FIRESTORE') === '1');
        $collectionName = AppConfig::getCollectionName();
        $message = null;
        $previewMessage = null;

        if ($isMock) {
            $configData = [
                'iijmio' => [
                    'mio_id' => 'MA1234567',
                    'password' => 'supersecret',
                    'users' => [
                        'hdo11111111' => [
                            'name' => 'Alice',
                            'plan_data_volume' => 5.0
                        ],
                        'hdo22222222' => [
                            'name' => 'Bob',
                            'plan_data_volume' => 10.0
                        ]
                    ]
                ],
                'alert' => [
                    'bot' => 'MyLineBot',
                    'target' => 'MyGroup',
                    'send_usage_each_n_days' => 3
                ]
            ];

            if ($request->getMethod() === 'POST') {
                $params = (array)$request->getParsedBody();
                $users = [];
                if (isset($params['iijmio']['users']) && is_array($params['iijmio']['users'])) {
                    foreach ($params['iijmio']['users'] as $user) {
                        if (!empty($user['code']) && !empty($user['name'])) {
                            $users[$user['code']] = [
                                'name' => $user['name'],
                                'plan_data_volume' => (float)($user['plan_data_volume'] ?? 0),
                            ];
                        }
                    }
                }
                $configData = [
                    'iijmio' => [
                        'mio_id' => $params['iijmio']['mio_id'] ?? '',
                        'password' => $params['iijmio']['password'] ?? '',
                        'users' => $users,
                    ],
                    'alert' => [
                        'bot' => $params['alert']['bot'] ?? '',
                        'target' => $params['alert']['target'] ?? '',
                        'send_usage_each_n_days' => (int)($params['alert']['send_usage_each_n_days'] ?? 0),
                    ],
                ];

                $action = $params['action'] ?? 'save';
                if ($action === 'save') {
                    $message = "Config updated successfully. (Mock Save)";
                } elseif ($action === 'preview') {
                    $previewMessage = "[IIJmioデータ利用状況]\n- Alice (hdo11111111): 1.2 GB / 5.0 GB\n- Bob (hdo22222222): 4.5 GB / 10.0 GB\n\n[予測根拠]\nAlice: 直近3日間の平均から算出。\nBob: 直近3日間の平均から算出。";
                }
            }
        } else {
            $firestore = Firestore::getClient();
            $docRef = $firestore->collection($collectionName)->document('config');
            if ($request->getMethod() === 'POST') {
                $params = (array)$request->getParsedBody();

                $users = [];
                if (isset($params['iijmio']['users']) && is_array($params['iijmio']['users'])) {
                    foreach ($params['iijmio']['users'] as $user) {
                        if (!empty($user['code']) && !empty($user['name'])) {
                            $users[$user['code']] = [
                                'name' => $user['name'],
                                'plan_data_volume' => (float)($user['plan_data_volume'] ?? 0),
                            ];
                        }
                    }
                }

                $configData = [
                    'iijmio' => [
                        'mio_id' => $params['iijmio']['mio_id'] ?? '',
                        'password' => $params['iijmio']['password'] ?? '',
                        'users' => $users,
                    ],
                    'alert' => [
                        'bot' => $params['alert']['bot'] ?? '',
                        'target' => $params['alert']['target'] ?? '',
                        'send_usage_each_n_days' => (int)($params['alert']['send_usage_each_n_days'] ?? 0),
                    ],
                ];

                $action = $params['action'] ?? 'save';
                if ($action === 'save') {
                    $docRef->set($configData);
                    $message = "Config updated successfully.";
                } elseif ($action === 'preview') {
                    $configObj = (object)json_decode((string)json_encode($configData));
                    $logger = new Logger("preview");
                    $historyDoc = $firestore->collection($collectionName)->document('history')->snapshot();
                    $history = $historyDoc->exists() ? (array)$historyDoc->data() : [];

                    $iijmio = new IijmioUsage(
                        $configObj->iijmio,
                        $configObj->alert->send_usage_each_n_days,
                        $logger,
                        $history
                    );
                    try {
                        [, $previewMessage] = $iijmio->getStats();
                    } catch (\Exception $e) {
                        $previewMessage = "Error: " . $e->getMessage();
                    }
                }
            } else {
                $doc = $docRef->snapshot();
                $configData = $doc->exists() ? (array)$doc->data() : [];
            }
        }

        $views = dirname(__DIR__, 2) . '/views';
        $cache = '/tmp/cache';
        if (!is_dir($cache)) {
            mkdir($cache, 0777, true);
        }
        $blade = new BladeOne($views, $cache, BladeOne::MODE_AUTO);

        return $blade->run("config", [
            "message" => $message,
            "previewMessage" => $previewMessage ?? null,
            "collectionName" => $collectionName,
            "config" => $configData,
            "appEnv" => getenv('APP_ENV') ?: 'unknown',
        ]);
    }
}
