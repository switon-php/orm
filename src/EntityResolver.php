<?php

declare(strict_types=1);

namespace Switon\Orm;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;
use Switon\Binding\PropertyNormalizerInterface;
use Switon\Binding\ValueResolverInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\InputInterface;
use Switon\Core\MakerInterface;
use Switon\Core\Exception\RuntimeException;
use Switon\Orm\Exception\PrimaryKeyMissingException;
use Switon\Principal\IdentityInterface;
use function array_key_exists;
use function is_object;
use function method_exists;

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
 * - ArgumentResolvable precedence
 *
 * @see \Switon\Orm\Entity
 * @see \Switon\Core\InputInterface
 * @see \Switon\Orm\EntityMetadataInterface::getRepository()
 * @see \Switon\Orm\RepositoryInterface::fill()
 */
class EntityResolver implements ValueResolverInterface
{
    protected const string IDENTITY_FILTER_ATTRIBUTE = 'Switon\\Http\\Attribute\\IdentityFilter';

    #[Autowired] protected InputInterface $input;
    #[Autowired] protected IdentityInterface $identity;
    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    #[Autowired] protected ?MakerInterface $maker = null;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function normalizeInput(string $type, array $data): array
    {
        $reflection = new ReflectionClass($type);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $field = $property->getName();
            if (!array_key_exists($field, $data)) {
                continue;
            }

            foreach ($property->getAttributes(
                PropertyNormalizerInterface::class,
                ReflectionAttribute::IS_INSTANCEOF
            ) as $attribute) {
                $normalizer = $this->instantiateAttribute($attribute);
                $data[$field] = $normalizer->normalizeInput($property, $data[$field]);
            }
        }

        return $data;
    }

    protected function instantiateAttribute(ReflectionAttribute $attribute): PropertyNormalizerInterface
    {
        if ($this->maker !== null) {
            $instance = $this->maker->make($attribute->getName(), $attribute->getArguments());
            if (!$instance instanceof PropertyNormalizerInterface) {
                RuntimeException::raise(
                    'Attribute "{attribute}" must implement {contract}.',
                    ['attribute' => $attribute->getName(), 'contract' => PropertyNormalizerInterface::class]
                );
            }

            return $instance;
        }

        $instance = $attribute->newInstance();
        if (!$instance instanceof PropertyNormalizerInterface) {
            RuntimeException::raise(
                'Attribute "{attribute}" must implement {contract}.',
                ['attribute' => $attribute->getName(), 'contract' => PropertyNormalizerInterface::class]
            );
        }

        return $instance;
    }

    public function resolve(ReflectionParameter $parameter, string $type): mixed
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
        $identityFilter = $this->resolveIdentityFilter($parameter);
        $ownerField = $this->resolveOwnerField($parameter, $type, $identityFilter);
        if ($ownerField !== null) {
            $ownerType = $this->entityFieldType($type, $ownerField);
            $normalized[$ownerField] = $this->resolveCurrentIdentityId($identityFilter, $ownerType);
        }

        $criteria = [$primaryKey => $raw[$primaryKey]];
        if ($ownerField !== null) {
            $criteria[$ownerField] = $normalized[$ownerField];
        }

        $entity = $repository->firstOrFail($criteria);
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

    protected function resolveIdentityFilter(ReflectionParameter $parameter): object|null
    {
        foreach ($parameter->getDeclaringFunction()->getAttributes() as $attribute) {
            if ($attribute->getName() !== self::IDENTITY_FILTER_ATTRIBUTE) {
                continue;
            }

            if ($this->maker !== null) {
                return $this->maker->make($attribute->getName(), $attribute->getArguments());
            }

            return $attribute->newInstance();
        }

        return null;
    }

    protected function identityFilterAppliesTo(object $identityFilter, ReflectionParameter $parameter): bool
    {
        return method_exists($identityFilter, 'appliesTo')
            ? (bool)$identityFilter->appliesTo($parameter)
            : true;
    }

    protected function identityFilterCurrentIdentityId(object $identityFilter, ?string $targetType): mixed
    {
        if (method_exists($identityFilter, 'currentIdentityId')) {
            return $identityFilter->currentIdentityId($targetType);
        }

        return null;
    }

    protected function identityFilterField(object $identityFilter): string
    {
        return is_object($identityFilter) && isset($identityFilter->field) ? (string)$identityFilter->field : '';
    }

    protected function resolveOwnerField(
        ReflectionParameter $parameter,
        string              $entityClass,
        object|null         $identityFilter,
    ): ?string
    {
        if ($identityFilter !== null) {
            if (!$this->identityFilterAppliesTo($identityFilter, $parameter)) {
                return null;
            }

            $field = $this->identityFilterField($identityFilter);
            return $field === '' ? null : $field;
        }

        return $this->entityMetadata->getOwnerField($entityClass);
    }

    protected function resolveCurrentIdentityId(object|null $identityFilter, ?string $targetType): int|string
    {
        if ($identityFilter !== null) {
            return $this->identityFilterCurrentIdentityId($identityFilter, $targetType);
        }

        $id = $this->identity->getId();
        if ($targetType === 'int' || is_int($id)) {
            return (int)$id;
        }

        return is_string($id) ? $id : (string)$id;
    }

    protected function entityFieldType(string $type, string $field): ?string
    {
        if ($field === '') {
            return null;
        }

        $reflection = new ReflectionClass($type);
        if (!$reflection->hasProperty($field)) {
            return null;
        }

        return $reflection->getProperty($field)->getType()?->getName();
    }

}
