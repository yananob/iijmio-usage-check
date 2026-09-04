<?php declare(strict_types=1);

namespace App\Controllers;

use App\AppConfig;
use App\Services\ConfigServiceInterface;
use App\Services\FirestoreConfigService;
use App\Services\MockConfigService;
use eftec\bladeone\BladeOne;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTPリクエストをルーティングし、Web画面描画またはAPIレスポンスを返却するコントローラー
 */
final class ConfigController
{
    private ?ConfigServiceInterface $service;

    public function __construct(?ConfigServiceInterface $service = null)
    {
        $this->service = $service;
    }

    /**
     * HTTPリクエストのハンドリングを行う
     *
     * @param ServerRequestInterface $request
     * @return string
     */
    public function handle(ServerRequestInterface $request): string
    {
        $isMock = ($request->getQueryParams()['mock'] ?? false) || (getenv('MOCK_FIRESTORE') === '1');
        $collectionName = AppConfig::getCollectionName();

        $service = $this->service ?? ($isMock
            ? new MockConfigService()
            : new FirestoreConfigService($collectionName));

        $path = $request->getUri()->getPath();
        $queryParams = $request->getQueryParams();
        $page = $queryParams['page'] ?? null;
        $action = $queryParams['action'] ?? null;

        // API エンドポイント: 当月利用状況サマリー
        if ($path === '/api/usage' || $action === 'api_usage') {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            $refresh = ($queryParams['refresh'] ?? null) === '1';
            return json_encode($service->getUsageSummary($refresh), JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        // API エンドポイント: 履歴データ
        if ($path === '/api/history' || $action === 'api_history') {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            return json_encode($service->getHistoryData(), JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        $views = dirname(__DIR__, 2) . '/views';
        $cache = '/tmp/cache';
        if (!is_dir($cache)) {
            mkdir($cache, 0777, true);
        }
        $blade = new BladeOne($views, $cache, BladeOne::MODE_AUTO);

        $appEnv = getenv('APP_ENV') ?: 'unknown';

        // 画面: 設定ページ (`/config`)
        if ($path === '/config' || $page === 'config') {
            $message = null;
            $previewMessage = null;

            if ($request->getMethod() === 'POST') {
                $params = (array)$request->getParsedBody();
                $configData = $service->parseConfigFromParams($params);
                $postAction = $params['action'] ?? 'save';

                if ($postAction === 'save') {
                    $message = $service->saveConfig($configData);
                } elseif ($postAction === 'preview') {
                    $previewMessage = $service->generatePreview($configData);
                }
            } else {
                $configData = $service->getConfig();
            }

            return $blade->run("config", [
                "message" => $message,
                "previewMessage" => $previewMessage,
                "collectionName" => $collectionName,
                "config" => $configData,
                "appEnv" => $appEnv,
                "currentPage" => "config",
            ]);
        }

        // 画面: 日別グラフ (`/daily`)
        if ($path === '/daily' || $page === 'daily') {
            return $blade->run("daily", [
                "collectionName" => $collectionName,
                "appEnv" => $appEnv,
                "currentPage" => "daily",
            ]);
        }

        // 画面: 月別グラフ (`/monthly`)
        if ($path === '/monthly' || $page === 'monthly') {
            return $blade->run("monthly", [
                "collectionName" => $collectionName,
                "appEnv" => $appEnv,
                "currentPage" => "monthly",
            ]);
        }

        // デフォルト画面: メインダッシュボード (`/`)
        return $blade->run("main", [
            "collectionName" => $collectionName,
            "appEnv" => $appEnv,
            "currentPage" => "main",
        ]);
    }
}
