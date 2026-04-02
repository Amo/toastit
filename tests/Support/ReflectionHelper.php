<?php

namespace App\Tests\Support;

final class ReflectionHelper
{
    public static function setProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($object);

        while (false !== $reflection) {
            if ($reflection->hasProperty($property)) {
                $reflectionProperty = $reflection->getProperty($property);
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($object, $value);

                return;
            }

            $reflection = $reflection->getParentClass() ?: false;
        }

        throw new \InvalidArgumentException(sprintf('Property "%s" not found on %s.', $property, $object::class));
    }

    public static function setId(object $object, int $id): void
    {
        self::setProperty($object, 'id', $id);
    }
}
