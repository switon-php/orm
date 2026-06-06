<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use ReflectionProperty;
use Switon\Orm\Entity;
use Switon\Orm\PropertyWriteFillerInterface;

/**
 * Assigns one fixed value during entity create or update lifecycle.
 *
 * Guidance: Use <code>#[FixedValue(0)]</code> when a field must be written as one system-owned constant.
 *
 * @see \Switon\Orm\PropertyWriteFillerInterface
 * @see \Switon\Orm\EntityFiller
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class FixedValue implements PropertyWriteFillerInterface
{
    public function __construct(
        public mixed $value,
        public bool  $onCreate = true,
        public bool  $onUpdate = false,
        public bool  $overwrite = true,
    ) {
    }

    public function onCreating(Entity $entity, ReflectionProperty $property): void
    {
        if (!$this->onCreate) {
            return;
        }

        $name = $property->getName();
        if (!$this->overwrite && isset($entity->$name)) {
            return;
        }

        $entity->$name = $this->value;
    }

    public function onUpdating(Entity $entity, ReflectionProperty $property): void
    {
        if (!$this->onUpdate) {
            return;
        }

        $name = $property->getName();
        if (!$this->overwrite && isset($entity->$name)) {
            return;
        }

        $entity->$name = $this->value;
    }
}
