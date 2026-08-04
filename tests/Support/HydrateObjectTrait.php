<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

trait HydrateObjectTrait
{
    /**
     * @param mixed $data
     */
    private function hydrateObject(object $object, mixed $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }
    }
}
