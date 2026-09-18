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

        self::$client = new FirestoreClient();
        return self::$client;
    }
}
