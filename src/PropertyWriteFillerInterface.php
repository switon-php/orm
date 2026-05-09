<?php

declare(strict_types=1);

namespace Switon\Orm;

use ReflectionProperty;

/**
 * Defines property-level write filling during entity create/update.
 *
 * Use when one property attribute should assign a value during ORM write lifecycle instead of controller assembly.
 *
 * @see \Switon\Orm\EntityFiller
 */
interface PropertyWriteFillerInterface
{
    /**
     * Fill one property during entity creation.
     */
    public function onCreating(Entity $entity, ReflectionProperty $property): void;

    /**
     * Fill one property during entity update.
     */
    public function onUpdating(Entity $entity, ReflectionProperty $property): void;
}
