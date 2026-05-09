<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use ReflectionProperty;
use Switon\Core\Attribute\Autowired;
use Switon\Principal\IdentityInterface;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\PropertyWriteFillerInterface;

/**
 * Assigns current identity id or name during entity write lifecycle.
 *
 * Guidance: Use <code>#[CurrentIdentity]</code> when a field should fall back to the current identity id or name.
 *
 * @see \Switon\Orm\PropertyWriteFillerInterface
 * @see \Switon\Orm\EntityFiller
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CurrentIdentity implements PropertyWriteFillerInterface
{
    #[Autowired] protected IdentityInterface $identity;
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    public function __construct(
        public bool $onCreate = true,
        public bool $onUpdate = false,
        public bool $overwrite = false,
    )
    {
    }

    protected function shouldFill(Entity $entity, string $name): bool
    {
        if (!isset($entity->$name)) {
            return true;
        }

        $value = $entity->$name;
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return true;
        }

        return $this->overwrite;
    }

    protected function resolveValue(Entity $entity, ReflectionProperty $property): int|string
    {
        $type = $this->entityMetadata->getFieldType($entity::class, $property->getName());

        if ($type === 'int') {
            return $this->identity->isGuest() ? 0 : $this->identity->getId();
        }

        return $this->identity->isGuest() ? '' : $this->identity->getName();
    }

    public function onCreating(Entity $entity, ReflectionProperty $property): void
    {
        if (!$this->onCreate) {
            return;
        }

        $name = $property->getName();
        if (!$this->shouldFill($entity, $name)) {
            return;
        }

        $entity->$name = $this->resolveValue($entity, $property);
    }

    public function onUpdating(Entity $entity, ReflectionProperty $property): void
    {
        if (!$this->onUpdate) {
            return;
        }

        $name = $property->getName();
        if (!$this->shouldFill($entity, $name)) {
            return;
        }

        $entity->$name = $this->resolveValue($entity, $property);
    }
}
