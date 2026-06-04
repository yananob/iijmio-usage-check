<?php declare(strict_types=1);

namespace MyApp\Utils;

use CloudEvents\V1\CloudEventInterface;

final class CFUtils
{
    public static function isLocalEvent(CloudEventInterface $event): bool
    {
        // Cloud Functions context usually has certain attributes.
        // For simplicity and matching common patterns in these tools:
        return getenv('FUNCTION_TARGET') !== null && getenv('K_SERVICE') === false;
    }
}
