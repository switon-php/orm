<?php

declare(strict_types=1);

namespace Switon\Orm;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\Event\EarlyLoaded;
use Switon\Orm\Event\EarlyLoading;
use Switon\Orm\Event\LazyLoaded;
use Switon\Orm\Event\LazyLoading;
use Switon\Orm\Exception\RelationNotFoundException;
use Switon\Orm\Exception\RelationPayloadInvalidException;
use Switon\Query\QueryInterface;
use function is_array;
use function is_callable;
use function is_int;

/**
 * Default dispatcher for relation lookup and loading.
 *
 * Road-signs:
 * - resolve relation by name
 * - getQuery applies eager payload
 * - earlyLoad attaches batches
 * - lazyLoad builds one relation query
 *
 * Guidance: Keep eager payloads strict and let relation implementations own relation-specific query logic.
 *
 * @see \Switon\Orm\RelationManagerInterface
 * @see \Switon\Orm\EntityMetadataInterface::getRelations()
 * @see \Switon\Orm\RelationInterface
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Orm\Exception\RelationNotFoundException
 * @see \Switon\Orm\Exception\RelationPayloadInvalidException
 */
class RelationManager implements RelationManagerInterface
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    /** {@inheritDoc} */
    public function has(string $entityClass, string $name): bool
    {
        return $this->get($entityClass, $name) !== null;
    }

    /** {@inheritDoc} */
    public function get(string $entityClass, string $name): ?RelationInterface
    {
        $relations = $this->entityMetadata->getRelations($entityClass);
        return $relations[$name] ?? null;
    }

    /** {@inheritDoc} */
    public function getQuery(string $entityClass, string $name, mixed $data): QueryInterface
    {
        $relation = $this->get($entityClass, $name);
        if ($relation === null) {
            RelationNotFoundException::raise(
                'Relation "{name}" is not defined for entity "{entity}".',
                ['name' => $name, 'entity' => $entityClass]
            );
        }

        $query = $relation->getRelatedQuery();

        if ($data === null) {
            //no-op
        } elseif (is_array($data)) {
            $query->select($data);
        } elseif (is_callable($data)) {
            $data($query);
        } else {
            RelationPayloadInvalidException::raise(
                'Invalid eager-load payload for relation "{name}".',
                ['name' => $name]
            );
        }

        return $query;
    }

    /** {@inheritDoc} */
    public function earlyLoad(string $entityClass, array $r, array $withs): array
    {
        foreach ($this->groupWithsByRelation($withs) as $name => $config) {
            $data = $config['data'];
            $childWiths = $config['childWiths'];

            if (($relation = $this->get($entityClass, $name)) === null) {
                RelationNotFoundException::raise(
                    'Relation "{name}" is not defined for entity "{entity}".',
                    ['name' => $name, 'entity' => $entityClass]
                );
            }

            $this->eventDispatcher->dispatch(new EarlyLoading($entityClass, $name, $r));

            $relatedQuery = $data instanceof QueryInterface
                ? $data
                : $this->getQuery($entityClass, $name, $data);

            if ($childWiths !== []) {
                $relatedQuery->with($childWiths);
            }

            $r = $relation->earlyLoad($r, $relatedQuery, $name);

            $this->eventDispatcher->dispatch(new EarlyLoaded($entityClass, $name, $r));
        }

        return $r;
    }

    /**
     * Validates eager-load payloads and groups them by top-level relation name.
     *
     * @param array $withs
     * @return array<string, array{data: mixed, childWiths: array<string, mixed>}>
     */
    protected function groupWithsByRelation(array $withs): array
    {
        $grouped = [];

        foreach ($withs as $k => $v) {
            if (!is_string($k)) {
                RelationPayloadInvalidException::raise(
                    'Invalid eager-load relation key "{name}".',
                    ['name' => (string)$k]
                );
            }

            if (!$this->isSupportedWithValue($v)) {
                RelationPayloadInvalidException::raise(
                    'Invalid eager-load payload for relation "{name}".',
                    ['name' => $k]
                );
            }

            $grouped[$k] = $this->normalizeRelationWithPayload($v);
        }

        return $grouped;
    }

    /**
     * Validates supported eager-load relation payload types.
     */
    protected function isSupportedWithValue(mixed $value): bool
    {
        return is_array($value)
            || $value instanceof QueryInterface
            || is_callable($value);
    }

    /**
     * Splits one relation payload into direct select fields and child with-config.
     *
     * Array payload supports:
     * - numeric keys with string values: select fields
     * - string keys: child relations
     *
     * @return array{data: mixed, childWiths: array<string, mixed>}
     */
    protected function normalizeRelationWithPayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            return ['data' => $payload, 'childWiths' => []];
        }

        $fields = [];
        $childWiths = [];

        foreach ($payload as $k => $v) {
            if (is_int($k)) {
                $fields[] = $v;
                continue;
            }

            $childWiths[$k] = $v;
        }

        return ['data' => $fields === [] ? null : $fields, 'childWiths' => $childWiths];
    }

    /** {@inheritDoc} */
    public function lazyLoad(Entity $entity, string $relationName): QueryInterface
    {
        if (($relation = $this->get($entity::class, $relationName)) === null) {
            RelationNotFoundException::raise(
                'Relation "{name}" is not defined for entity "{entity}".',
                ['name' => $relationName, 'entity' => $entity::class]
            );
        }

        $this->eventDispatcher->dispatch(new LazyLoading($entity, $relationName));
        $query = $relation->lazyLoad($entity);
        $this->eventDispatcher->dispatch(new LazyLoaded($entity, $relationName));
        return $query;
    }
}
