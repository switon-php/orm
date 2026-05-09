<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Validating\AbstractConstraint;
use Switon\Validating\Exception\InvalidConstraintSourceException;
use Switon\Validating\Validation;
use function get_debug_type;

/**
 * Validation constraint that rejects changes to an already-persisted field.
 *
 * Road-signs:
 * - new entities pass
 * - null values pass
 * - existing rows compare against database value
 *
 * Guidance: Use this only for fields that are truly write-once, because validation performs a database lookup.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getConstraints()
 * @see \Switon\Orm\Attribute\Unique
 * @see \Switon\Orm\Attribute\Exists
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Immutable extends AbstractConstraint
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    /**
     * Validate that the field value has not changed from its database value.
     *
     * @param Validation $validation The validation context containing value and source entity
     * @return bool True if validation passes (value unchanged), false if validation fails (value changed)
     * @throws InvalidConstraintSourceException If source is not an Entity instance
     */
    public function validate(Validation $validation): bool
    {
        if ($validation->value === null) {
            return true;
        }

        $entity = $validation->source;
        if (!$entity instanceof Entity) {
            InvalidConstraintSourceException::raise('Constraint #{constraint} requires an Entity source, got {type}',
                ['constraint' => 'Immutable', 'type' => get_debug_type($entity)]
            );
        }
        $entityClass = $entity::class;

        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);
        if (!isset($entity->$primaryKey)) {
            return true;
        }

        $repository = $this->entityMetadata->getRepository($entityClass);
        return $validation->value === $repository->value([$primaryKey => $entity->$primaryKey], $validation->field);
    }
}
