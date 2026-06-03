<?php declare(strict_types=1);

namespace MyApp;

use Google\Cloud\Firestore\FirestoreClient;

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

        $serviceAccountKey = getenv('FIREBASE_SERVICE_ACCOUNT');
        $firestoreOptions = [];
        if ($serviceAccountKey) {
            $firestoreOptions['keyFile'] = json_decode($serviceAccountKey, true);
        }

        try {
            $firestore = new FirestoreClient($firestoreOptions);
            $doc = $firestore->collection('iijmio-usage-check')->document('config')->snapshot();

            if (!$doc->exists()) {
                throw new \RuntimeException("Config not found in Firestore: iijmio-usage-check/config");
            }

            // オブジェクトとして扱うため、一度JSONにしてからデコードする
            self::$config = (object)json_decode(json_encode($doc->data()));
            return self::$config;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to load config from Firestore: " . $e->getMessage());
        }
    }
}
