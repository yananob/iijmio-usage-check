<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Firestore;
use App\AppConfig;

$configFile = __DIR__ . '/../configs/config.json';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/../configs/config.json.sample';
}

echo "Reading config from: $configFile\n";
$configData = json_decode(file_get_contents($configFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON in config file.\n");
}

try {
    $firestore = Firestore::getClient();
    $collectionName = AppConfig::getCollectionName();
    $docRef = $firestore->collection($collectionName)->document('config');
    $docRef->set($configData);
    echo "Successfully migrated config to Firestore: {$collectionName}/config\n";
} catch (\Exception $e) {
    echo "Error migrating config: " . $e->getMessage() . "\n";
    echo "Note: This might fail if FIREBASE_SERVICE_ACCOUNT is not set or invalid in this environment.\n";
}
