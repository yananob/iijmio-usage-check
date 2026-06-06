<?php declare(strict_types=1);

namespace App;

final class AppConfig
{
    public const ENV_PRODUCTION = 'production';
    public const ENV_TEST = 'test';
    public const ENV_LOCAL = 'local';

    public const COLLECTION_NAME = 'iijmio-usage-check';
    public const COLLECTION_NAME_TEST = 'iijmio-usage-check-test';

    /**
     * 環境に応じたコレクション名を取得する
     *
     * @return string
     */
    public static function getCollectionName(): string
    {
        $env = getenv('APP_ENV');
        if ($env === self::ENV_PRODUCTION) {
            return self::COLLECTION_NAME;
        }

        return self::COLLECTION_NAME_TEST;
    }
}
