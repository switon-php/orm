<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception as CoreException;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;
use Switon\Validating\AbstractConstraint;
use Switon\Validating\Exception\InvalidConstraintSourceException;
use Switon\Validating\Validation;

use function get_debug_type;

/**
 * Validation constraint that ensures a value exists in one explicit target entity.
 *
 * Road-signs:
 * - target entity is explicit
 * - null values pass
 * - repository lookup confirms existence
 *
 * Guidance: Pass the target entity class explicitly; field names do not participate in lookup.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getConstraints()
 * @see \Switon\Orm\Attribute\Unique
 * @see \Switon\Orm\Attribute\Immutable
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Exists extends AbstractConstraint
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    /**
     * @param class-string<Entity> $entityClass
     * @param string|null $message
     */
    public function __construct(
        public readonly string $entityClass,
        ?string                $message = null
    ) {
        parent::__construct($message);
    }

    /**
     * Validate that the value exists in the target entity.
     *
     * @param Validation $validation The validation context containing value and source entity
     *
     * @return bool True if validation passes, false if validation fails
     *
     * @throws InvalidConstraintSourceException If source is not an Entity instance
     */
    public function validate(Validation $validation): bool
    {
        if (!$validation->source instanceof Entity) {
            InvalidConstraintSourceException::raise(
                'Constraint #{constraint} requires an Entity source, got {type}',
                ['constraint' => 'Exists', 'type' => get_debug_type($validation->source)]
            );
        }

        $value = $validation->value;
        if ($value === null) {
            return true;
        }

        try {
            $repository = $this->entityMetadata->getRepository($this->entityClass);
            $repository->get($value);
        } catch (CoreException) {
            return false;
        }

        return true;
    }
}
