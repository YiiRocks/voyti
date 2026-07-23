<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

trait HydrateObjectTrait
{
    /**
     * @param array<string, mixed> $data
     */
    private function hydrateObject(object $object, array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }
    }
}
