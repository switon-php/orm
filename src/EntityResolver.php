<?php

declare(strict_types=1);

namespace Switon\Orm;

use ReflectionClass;
use ReflectionParameter;
use Switon\Binding\InputBinderInterface;
use Switon\Binding\ValueResolverInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\RuntimeException;
use Switon\Core\InputInterface;
use Switon\Orm\Exception\PrimaryKeyMissingException;
use Switon\Principal\IdentityInterface;
use ReflectionProperty;

use function array_key_exists;

/**
 * Resolves existing entity action parameters from unified input payload.
 *
 * Guidance: Entity parameters require a primary key and resolve an existing row before applying
 * request payload data; use request-body DTOs for create-only payloads.
 *
 * Road-signs:
 * - InputInterface all()
 * - repository firstOrFail()
 * - fillable-only hydration
 * - entity Owner metadata for identity-scoped loads
 * - ArgumentResolvable precedence
 *
 * @see \Switon\Orm\Attribute\Owner
 * @see \Switon\Orm\Entity
 * @see \Switon\Core\InputInterface
 * @see \Switon\Orm\EntityMetadataInterface::getRepository()
 * @see \Switon\Orm\RepositoryInterface::fill()
 */
class EntityResolver implements ValueResolverInterface
{
    #[Autowired] protected InputInterface $input;
    #[Autowired] protected IdentityInterface $identity;
    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    #[Autowired] protected InputBinderInterface $inputBinder;

    /**
     * @param class-string<Entity> $type
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function normalizeInput(string $type, array $data): array
    {
        $reflection = new ReflectionClass($type);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $field = $property->getName();
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->inputBinder->normalizePropertyInput($property, $data[$field]);
        }

        return $data;
    }

    /**
     * @param class-string<Entity> $type
     */
    public function resolve(ReflectionParameter $parameter, string $type): Entity
    {
        if (!is_a($type, Entity::class, true)) {
            RuntimeException::raise(
                'EntityResolver only supports "{expected}" subclasses, got "{actual}".',
                ['expected' => Entity::class, 'actual' => $type]
            );
        }

        $raw = $this->input->all();
        $primaryKey = $this->entityMetadata->getPrimaryKey($type);
        if (!array_key_exists($primaryKey, $raw) || $raw[$primaryKey] === null || $raw[$primaryKey] === '') {
            PrimaryKeyMissingException::raise(
                'Entity parameter "{type}" requires primary key "{primaryKey}".',
                ['type' => $type, 'primaryKey' => $primaryKey]
            );
        }

        $repository = $this->entityMetadata->getRepository($type);
        $normalized = $this->normalizeInput($type, $raw);

        $loadFilters = [$primaryKey => $raw[$primaryKey]];
        $ownerField = $this->entityMetadata->getOwnerField($type);
        if ($ownerField !== null) {
            $ownerValue = $this->identity->getId();
            $normalized[$ownerField] = $ownerValue;
            $loadFilters[$ownerField] = $ownerValue;
        }

        $entity = $repository->firstOrFail($loadFilters);
        $incoming = $repository->fill($normalized);
        foreach (array_keys($normalized) as $field) {
            if ($field === $primaryKey) {
                continue;
            }

            if (property_exists($incoming, $field) || property_exists($entity, $field)) {
                $entity->$field = $incoming->$field ?? $normalized[$field];
            }
        }

        $entity->$primaryKey = $raw[$primaryKey];

        return $entity;
    }

}
