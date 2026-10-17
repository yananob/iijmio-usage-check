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

        // PWA 静的アセット (manifest.json, sw.js, アイコン)
        $pwaRoutes = [
            '/manifest.json' => 'application/manifest+json',
            '/sw.js' => 'text/javascript',
            '/icon-192.png' => 'image/png',
            '/icon-512.png' => 'image/png',
            '/icon.svg' => 'image/svg+xml',
        ];

        if (isset($pwaRoutes[$path])) {
            $publicDir = dirname(__DIR__, 2) . '/public';
            $filePath = $publicDir . $path;
            if (file_exists($filePath)) {
                return $this->fileResponse($filePath, $pwaRoutes[$path]);
            }
        }

        // API エンドポイント: 当月利用状況サマリー
        if ($path === '/api/usage' || $action === 'api_usage') {
            $refresh = ($queryParams['refresh'] ?? null) === '1';
            return $this->jsonResponse($service->getUsageSummary($refresh));
        }

        // API エンドポイント: 履歴データ
        if ($path === '/api/history' || $action === 'api_history') {
            return $this->jsonResponse($service->getHistoryData());
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

    /**
     * JSON APIレスポンスを生成する
     *
     * @param array<string, mixed> $data
     * @return string
     */
    private function jsonResponse(array $data): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * ファイルレスポンスを生成する
     *
     * @param string $filePath
     * @param string $contentType
     * @return string
     */
    private function fileResponse(string $filePath, string $contentType): string
    {
        if (!headers_sent()) {
            header('Content-Type: ' . $contentType);
            header('Cache-Control: public, max-age=86400');
        }
        return file_get_contents($filePath) ?: '';
    }
}
