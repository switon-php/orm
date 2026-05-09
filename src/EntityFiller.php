<?php

declare(strict_types=1);

namespace Switon\Orm;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Switon\Core\Attribute\Autowired;
use Switon\Principal\IdentityInterface;
use Switon\Core\MakerInterface;
use function date;
use function property_exists;
use function time;

/**
 * Default audit-field filler for entity writes.
 *
 * Road-signs:
 * - resolve the first matching configured field
 * - timestamps cast by property type
 * - identity values cast by property type
 * - creating and updating fill different fields
 *
 * Guidance: Keep auto-filled audit fields typed as <code>int</code> or <code>string</code> so filler output stays predictable.
 *
 * @see \Switon\Orm\EntityFillerInterface
 * @see \Switon\Orm\Entity
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\EntityMetadataInterface::getFieldType()
 * @see \Switon\Principal\IdentityInterface
 */
class EntityFiller implements EntityFillerInterface
{
    #[Autowired] protected IdentityInterface $identity;
    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    #[Autowired] protected ?MakerInterface $maker = null;

    #[Autowired] protected array $created_at = ['created_at'];
    #[Autowired] protected array $updated_at = ['updated_at'];
    #[Autowired] protected array $created_by = ['created_by'];
    #[Autowired] protected array $updated_by = ['updated_by'];

    #[Autowired] protected string $date_format = 'Y-m-d H:i:s';

    protected function findField(Entity $entity, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (property_exists($entity, $field)) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Set timestamp value on entity field with appropriate type conversion.
     *
     * @param Entity $entity The entity to set the timestamp on
     * @param string $field The field name to set
     * @param int $timestamp The timestamp value to set
     * @return void
     */
    protected function setAt(Entity $entity, string $field, int $timestamp): void
    {
        $type = $this->entityMetadata->getFieldType($entity::class, $field);
        if ($type !== 'int' && $type !== 'string') {
            return;
        }

        $entity->$field = $type === 'int' ? $timestamp : date($this->date_format, $timestamp);
    }

    /**
     * Set user ID or user name on entity field with appropriate type conversion.
     *
     * Sets the field value based on the property type:
     * - **int properties**: Set to current user ID (or 0 if guest)
     * - **string properties**: Set to current user name (or empty string if guest)
     *
     * @param Entity $entity The entity to set the user information on
     * @param string $field The field name to set
     * @return void
     */
    protected function setBy(Entity $entity, string $field): void
    {
        $type = $this->entityMetadata->getFieldType($entity::class, $field);
        if ($type !== 'int' && $type !== 'string') {
            return;
        }

        if ($this->identity->isGuest()) {
            $entity->$field = $type === 'int' ? 0 : '';
        } else {
            $entity->$field = $type === 'int' ? $this->identity->getId() : $this->identity->getName();
        }
    }

    protected function fillPropertyStrategies(Entity $entity, bool $updating): void
    {
        $reflection = new ReflectionClass($entity);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes(
                PropertyWriteFillerInterface::class,
                ReflectionAttribute::IS_INSTANCEOF
            ) as $attribute) {
                /** @var PropertyWriteFillerInterface $strategy */
                $strategy = $this->maker !== null
                    ? $this->maker->make($attribute->getName(), $attribute->getArguments())
                    : $attribute->newInstance();

                if ($updating) {
                    $strategy->onUpdating($entity, $property);
                } else {
                    $strategy->onCreating($entity, $property);
                }
            }
        }
    }

    /** {@inheritDoc} */
    public function onCreating(Entity $entity): void
    {
        $timestamp = time();

        $created_at = $this->findField($entity, $this->created_at);
        if ($created_at !== null && !isset($entity->$created_at)) {
            $this->setAt($entity, $created_at, $timestamp);
        }

        $created_by = $this->findField($entity, $this->created_by);
        if ($created_by !== null && !isset($entity->$created_by)) {
            $this->setBy($entity, $created_by);
        }

        $updated_at = $this->findField($entity, $this->updated_at);
        if ($updated_at !== null && !isset($entity->$updated_at)) {
            $this->setAt($entity, $updated_at, $timestamp);
        }

        $updated_by = $this->findField($entity, $this->updated_by);
        if ($updated_by !== null && !isset($entity->$updated_by)) {
            $this->setBy($entity, $updated_by);
        }

        $this->fillPropertyStrategies($entity, false);
    }

    /** {@inheritDoc} */
    public function onUpdating(Entity $entity): void
    {
        $timestamp = time();

        $updated_at = $this->findField($entity, $this->updated_at);
        if ($updated_at !== null) {
            $this->setAt($entity, $updated_at, $timestamp);
        }

        $updated_by = $this->findField($entity, $this->updated_by);
        if ($updated_by !== null) {
            $this->setBy($entity, $updated_by);
        }

        $this->fillPropertyStrategies($entity, true);
    }
}
