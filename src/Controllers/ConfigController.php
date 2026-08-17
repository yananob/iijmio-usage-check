<?php declare(strict_types=1);

namespace App\Controllers;

use App\AppConfig;
use App\Services\ConfigServiceInterface;
use App\Services\FirestoreConfigService;
use App\Services\MockConfigService;
use eftec\bladeone\BladeOne;
use Psr\Http\Message\ServerRequestInterface;

final class ConfigController
{
    private ?ConfigServiceInterface $service;

    public function __construct(?ConfigServiceInterface $service = null)
    {
        $this->service = $service;
    }

    public function handle(ServerRequestInterface $request): string
    {
        $isMock = ($request->getQueryParams()['mock'] ?? false) || (getenv('MOCK_FIRESTORE') === '1');
        $collectionName = AppConfig::getCollectionName();

        $service = $this->service ?? ($isMock
            ? new MockConfigService()
            : new FirestoreConfigService($collectionName));

        $message = null;
        $previewMessage = null;

        if ($request->getMethod() === 'POST') {
            $params = (array)$request->getParsedBody();
            $configData = $service->parseConfigFromParams($params);
            $action = $params['action'] ?? 'save';

            if ($action === 'save') {
                $message = $service->saveConfig($configData);
            } elseif ($action === 'preview') {
                $previewMessage = $service->generatePreview($configData);
            }
        } else {
            $configData = $service->getConfig();
        }

        $views = dirname(__DIR__, 2) . '/views';
        $cache = '/tmp/cache';
        if (!is_dir($cache)) {
            mkdir($cache, 0777, true);
        }
        $blade = new BladeOne($views, $cache, BladeOne::MODE_AUTO);

        return $blade->run("config", [
            "message" => $message,
            "previewMessage" => $previewMessage,
            "collectionName" => $collectionName,
            "config" => $configData,
            "appEnv" => getenv('APP_ENV') ?: 'unknown',
        ]);
    }
}
