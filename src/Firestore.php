<?php declare(strict_types=1);

namespace App;

use Google\Cloud\Firestore\FirestoreClient;

final class Firestore
{
    private static ?FirestoreClient $client = null;

    public static function getClient(): FirestoreClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $serviceAccountKey = getenv('FIREBASE_SERVICE_ACCOUNT');
        $firestoreOptions = [];
        if ($serviceAccountKey) {
            $firestoreOptions['keyFile'] = json_decode($serviceAccountKey, true);
        }

        self::$client = new FirestoreClient($firestoreOptions);
        return self::$client;
    }
}
