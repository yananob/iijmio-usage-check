<?php declare(strict_types=1);

namespace MyApp;

final class AppConfig
{
    private static ?object $config = null;

    /**
     * Firestoreから設定を取得する
     *
     * @return object
     * @throws \RuntimeException
     */
    public static function get(): object
    {
        if (self::$config !== null) {
            return self::$config;
        }

        try {
            $firestore = Firestore::getClient();
            $collectionName = self::getCollectionName();
            $doc = $firestore->collection($collectionName)->document('config')->snapshot();

            if (!$doc->exists()) {
                throw new \RuntimeException("Config not found in Firestore: {$collectionName}/config");
            }

            self::$config = (object)json_decode(json_encode($doc->data()));
            return self::$config;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to load config from Firestore: " . $e->getMessage());
        }
    }

    /**
     * 環境に応じたコレクション名を取得する
     *
     * @return string
     */
    public static function getCollectionName(): string
    {
        $env = getenv('APP_ENV');
        if ($env === 'production') {
            return 'iijmio-usage-check';
        }

        return 'iijmio-usage-check-test';
    }
}
