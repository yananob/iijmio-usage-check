<?php declare(strict_types=1);

namespace MyApp\Utils;

final class Test
{
    public static function invokePrivateMethod(object $object, string $methodName, ...$parameters): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
