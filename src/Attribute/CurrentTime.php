<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use ReflectionProperty;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\PropertyWriteFillerInterface;

use function date;
use function time;

/**
 * Assigns current time during entity write lifecycle.
 *
 * Guidance: Use <code>#[CurrentTime('status', 1, 0)]</code> for status-driven publish or done time fields.
 *
 * @see \Switon\Orm\PropertyWriteFillerInterface
 * @see \Switon\Orm\EntityFiller
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CurrentTime implements PropertyWriteFillerInterface
{
    protected const string SKIP = '__switon.current_time.skip__';

    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    #[Autowired] protected string $date_format = 'Y-m-d H:i:s';

    public function __construct(
        public ?string $field = null,
        public mixed   $value = null,
        public mixed   $otherwise = self::SKIP,
        public bool    $onCreate = true,
        public bool    $onUpdate = true,
        public bool    $overwrite = false,
    ) {
    }

    protected function shouldMatch(Entity $entity): ?bool
    {
        if ($this->field === null) {
            return true;
        }

        if (!isset($entity->{$this->field})) {
            return null;
        }

        return $entity->{$this->field} === $this->value;
    }

    protected function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === 0 || $value === '0';
    }

    protected function shouldAssignCurrentTime(Entity $entity, string $name): bool
    {
        if (!isset($entity->$name)) {
            return true;
        }

        if ($this->overwrite) {
            return true;
        }

        return $this->isEmptyValue($entity->$name);
    }

    protected function resolveCurrentTime(Entity $entity, ReflectionProperty $property): int|string
    {
        $type = $this->entityMetadata->getFieldType($entity::class, $property->getName());
        $timestamp = time();

        return $type === 'string' ? date($this->date_format, $timestamp) : $timestamp;
    }

    protected function apply(Entity $entity, ReflectionProperty $property): void
    {
        $name = $property->getName();
        $matched = $this->shouldMatch($entity);

        if ($matched === true) {
            if ($this->shouldAssignCurrentTime($entity, $name)) {
                $entity->$name = $this->resolveCurrentTime($entity, $property);
            }
            return;
        }

        if ($matched === false && $this->otherwise !== self::SKIP) {
            $entity->$name = $this->otherwise;
        }
    }

    public function onCreating(Entity $entity, ReflectionProperty $property): void
    {
        if ($this->onCreate) {
            $this->apply($entity, $property);
        }
    }

    public function onUpdating(Entity $entity, ReflectionProperty $property): void
    {
        if ($this->onUpdate) {
            $this->apply($entity, $property);
        }
    }
}
