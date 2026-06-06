<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClassName;
use Switon\Db\ClientInterface;
use Switon\Db\TransactionManagerInterface;
use Switon\Di\NamedLookupInterface;
use Switon\Orm\Event\EntitiesCreated;
use Switon\Orm\Event\EntitiesCreating;
use Switon\Orm\Event\EntityCreated;
use Switon\Orm\Event\EntityCreating;
use Switon\Orm\Event\EntityDeleted;
use Switon\Orm\Event\EntityDeleting;
use Switon\Orm\Event\EntityUnchanged;
use Switon\Orm\Event\EntityUpdated;
use Switon\Orm\Event\EntityUpdating;
use Switon\Orm\Exception\CreateManyEntityTypeMismatchException;
use Switon\Orm\Exception\CreateManyInvalidEntityException;
use Switon\Orm\Exception\PrimaryKeyImmutableException;
use Switon\Orm\Exception\PrimaryKeyMissingException;
use Switon\Query\Table;
use Switon\Sharding\ShardingManagerInterface;

use function array_key_exists;
use function in_array;

/**
 * SQL entity manager for ORM write operations.
 *
 * Road-signs:
 * - resolve one shard for writes
 * - fill ids and audit fields
 * - write insert update delete
 * - reload database defaults when needed
 *
 * Guidance: Keep batch writes homogeneous and on one resolved shard before calling <code>createMany()</code>.
 *
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\AbstractEntityManager
 * @see \Switon\Orm\EntityFillerInterface
 * @see \Switon\Orm\ShardingInterface::getUniqueShard()
 * @see \Switon\Orm\IdGeneratorInterface
 * @see \Switon\Db\TransactionManagerInterface::useTransaction()
 */
class EntityManager extends AbstractEntityManager
{
    /** @var NamedLookupInterface<ClientInterface> */
    #[Autowired] protected NamedLookupInterface $namedLookup;

    /** @var QueryBuilderInterface<Entity> */
    #[Autowired] protected QueryBuilderInterface $queryBuilder;

    #[Autowired] protected IdGeneratorInterface $idGenerator;

    #[Autowired] protected TransactionManagerInterface $transactionManager;

    #[Autowired] protected ShardingManagerInterface $shardingManager;

    /**
     * Gets the query builder instance for creating queries.
     *
     * @return QueryBuilderInterface<Entity> Query builder instance
     */
    protected function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->queryBuilder;
    }

    /**
     * @param array<string> $fields
     * @param array<string, mixed> $entityValues
     * @param array<string, mixed> $originalValues
     *
     * @return array<string>
     */
    protected function getChangedFields(array $fields, array $entityValues, array $originalValues): array
    {
        $changedFields = [];
        foreach ($fields as $field) {
            $entityHas = isset($entityValues[$field]);
            $originalHas = isset($originalValues[$field]);

            if ($entityHas !== $originalHas || ($entityHas && $entityValues[$field] !== $originalValues[$field])) {
                $changedFields[] = $field;
            }
        }

        return $changedFields;
    }

    protected function describeEntityType(mixed $value): string
    {
        if (is_object($value)) {
            return ClassName::short($value::class);
        }

        return get_debug_type($value);
    }

    /** {@inheritDoc} */
    public function create(Entity $entity): Entity
    {
        $entityClass = $entity::class;
        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);

        if (!isset($entity->$primaryKey)) {
            $this->idGenerator->fillId($entity);
        }

        $this->autoFiller->onCreating($entity);

        $fields = $this->entityMetadata->getFields($entityClass);
        $columnMap = $this->entityMetadata->getColumnMap($entityClass);

        // EntityMetadata::getFillable() returns a map: field => type.
        // validate() expects a list of field names.
        $this->validate($entity, array_keys($this->entityMetadata->getFillable($entityClass)));

        [$connection, $table] = $this->sharding->getUniqueShard($entityClass, $entity);

        $useTransaction = $this->transactionManager->useTransaction($connection);
        if ($useTransaction) {
            $this->transactionManager->ensureConnection($connection);
        }

        $this->dispatchEvent(new EntityCreating($entity));

        $fieldValues = $this->entityHydrator->dehydrate($entity, $fields);
        $defaultValueFields = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $fieldValues) && $field !== $primaryKey) {
                $defaultValueFields[] = $field;
            }
        }

        foreach ($columnMap as $property => $column) {
            if (array_key_exists($property, $fieldValues)) {
                $fieldValues[$column] = $fieldValues[$property];
                unset($fieldValues[$property]);
            }
        }

        $db = $useTransaction
            ? $this->transactionManager->getCurrentClient($connection)
            : $this->namedLookup->by(ClientInterface::class, $connection);
        if (!isset($entity->$primaryKey)) {
            $entity->$primaryKey = (int)$db->insert($table, $fieldValues, true);
        } else {
            $db->insert($table, $fieldValues);
        }

        if ($defaultValueFields) {
            $query = $this->queryBuilder->create($entityClass);
            $query->setTable(Table::of($table, $connection));
            $query->setColumnMap($columnMap);
            $query->select($defaultValueFields)->where(
                [$columnMap[$primaryKey] ?? $primaryKey => $entity->$primaryKey]
            );
            if ($r = $query->execute()) {
                if (isset($r[0])) {
                    $this->entityHydrator->hydrateInto($entity, $r[0], $defaultValueFields);
                }
            }
        }

        $this->dispatchEvent(new EntityCreated($entity));

        return $entity;
    }

    /**
     * {@inheritDoc}
     *
     * Guidance: Keep batch inputs homogeneous and already single-shard before calling this method.
     */
    public function createMany(array $entities): array
    {
        if (empty($entities)) {
            return [];
        }

        $firstKey = array_key_first($entities);
        $firstEntity = $entities[$firstKey];

        if (!$firstEntity instanceof Entity) {
            CreateManyInvalidEntityException::raise(
                'createMany() expects Entity[]; item {index} is {type}.',
                [
                    'index' => (string)$firstKey,
                    'type' => $this->describeEntityType($firstEntity),
                ]
            );
        }

        $entityClass = $firstEntity::class;
        $entityType = ClassName::short($entityClass);

        foreach ($entities as $index => $entity) {
            if (!$entity instanceof Entity) {
                CreateManyInvalidEntityException::raise('createMany() expects Entity[]; item {index} is {type}.', [
                    'index' => (string)$index,
                    'type' => $this->describeEntityType($entity),
                ]);
            }

            // EntityMetadata resolution depends on the entity class; mixed classes can produce wrong mapping.
            if ($entity::class !== $entityClass) {
                CreateManyEntityTypeMismatchException::raise('createMany() expects {expected}[]; item {index} is {found}.', [
                    'expected' => $entityType,
                    'found' => $this->describeEntityType($entity),
                    'index' => (string)$index,
                ]);
            }
        }

        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);

        $this->eventDispatcher->dispatch(new EntitiesCreating($entityClass, $entities));

        // Resolve and validate shard for all entities (based on entity values).
        // Bulk insert requires a single database + single table.
        // NOTE: $connection/$table from metadata are strategies (may contain ':' or ',').
        $connection = $this->entityMetadata->getConnection($entityClass);
        $table = $this->entityMetadata->getTable($entityClass);
        [$connection, $table] = $this->shardingManager->unique($connection, $table, $entities);

        // Fill IDs (may allocate IDs in advance for auto-increment strategy).
        $this->idGenerator->fillIds($entities);

        // EntityMetadata::getFillable() returns a map: field => type.
        // validate() expects a list of field names.
        $fillable = array_keys($this->entityMetadata->getFillable($entityClass));
        foreach ($entities as $entity) {
            $this->autoFiller->onCreating($entity);
            $this->validate($entity, $fillable);
        }

        foreach ($entities as $entity) {
            $this->dispatchEvent(new EntityCreating($entity));
        }

        $useTransaction = $this->transactionManager->useTransaction($connection);
        if ($useTransaction) {
            $this->transactionManager->ensureConnection($connection);
        }

        $db = $useTransaction
            ? $this->transactionManager->getCurrentClient($connection)
            : $this->namedLookup->by(ClientInterface::class, $connection);

        $bulkRecords = [];
        $defaultValueFields = [];
        $entityIds = [];
        $fields = $this->entityMetadata->getFields($entityClass);
        $columnMap = $this->entityMetadata->getColumnMap($entityClass);
        foreach ($entities as $entity) {
            $fieldValues = $this->entityHydrator->dehydrate($entity, $fields);
            $hasOmittedFields = false;

            foreach ($fields as $field) {
                if ($field !== $primaryKey && !array_key_exists($field, $fieldValues)) {
                    if (!in_array($field, $defaultValueFields, true)) {
                        $defaultValueFields[] = $field;
                    }
                    $hasOmittedFields = true;
                }
            }

            foreach ($columnMap as $property => $column) {
                if (array_key_exists($property, $fieldValues)) {
                    $fieldValues[$column] = $fieldValues[$property];
                    unset($fieldValues[$property]);
                }
            }

            $bulkRecords[] = $fieldValues;

            if ($hasOmittedFields) {
                $entityIds[] = $entity->$primaryKey;
            }
        }

        $db->bulkInsert($table, $bulkRecords);

        if (!empty($defaultValueFields) && !empty($entityIds)) {
            $query = $this->queryBuilder->create($entityClass);
            $query->setTable(Table::of($table, $connection));
            $query->setColumnMap($columnMap);

            $primaryKeyColumn = $columnMap[$primaryKey] ?? $primaryKey;

            $results = $query->select(array_merge([$primaryKey], $defaultValueFields))
                ->where([$primaryKeyColumn => $entityIds])
                ->execute();

            $resultMap = [];
            foreach ($results as $result) {
                $resultMap[$result[$primaryKey]] = $result;
            }

            // Intentionally trigger warning on missing key to detect data inconsistency.
            foreach ($entities as $entity) {
                $result = $resultMap[$entity->$primaryKey];
                if (is_array($result)) {
                    $this->entityHydrator->hydrateInto($entity, $result, $defaultValueFields);
                }
            }
        }

        foreach ($entities as $entity) {
            $this->dispatchEvent(new EntityCreated($entity));
        }

        $this->eventDispatcher->dispatch(new EntitiesCreated($entityClass, $entities));

        return $entities;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Switon\Orm\EntityManager::create() Contrasting pipeline (validate, onCreating, default reload)
     */
    public function put(Entity $entity): Entity
    {
        $entityClass = $entity::class;
        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);

        if (!isset($entity->$primaryKey)) {
            $this->idGenerator->fillId($entity);
        }

        $fields = $this->entityMetadata->getFields($entityClass);
        $columnMap = $this->entityMetadata->getColumnMap($entityClass);

        [$connection, $table] = $this->sharding->getUniqueShard($entityClass, $entity);

        $useTransaction = $this->transactionManager->useTransaction($connection);
        if ($useTransaction) {
            $this->transactionManager->ensureConnection($connection);
        }

        $this->dispatchEvent(new EntityCreating($entity));

        $fieldValues = $this->entityHydrator->dehydrate($entity, $fields);

        foreach ($columnMap as $property => $column) {
            if (array_key_exists($property, $fieldValues)) {
                $fieldValues[$column] = $fieldValues[$property];
                unset($fieldValues[$property]);
            }
        }

        $db = $useTransaction
            ? $this->transactionManager->getCurrentClient($connection)
            : $this->namedLookup->by(ClientInterface::class, $connection);
        if (!isset($entity->$primaryKey)) {
            $entity->$primaryKey = (int)$db->insert($table, $fieldValues, true);
        } else {
            $db->insert($table, $fieldValues);
        }

        $this->dispatchEvent(new EntityCreated($entity));

        return $entity;
    }

    /** {@inheritDoc} */
    public function update(Entity $entity, Entity $original): Entity
    {
        $entityClass = $entity::class;
        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);

        if (!isset($entity->$primaryKey)) {
            PrimaryKeyMissingException::raise('Cannot update entity {entity}: primary key value is missing', ['entity' => $entityClass]);
        }

        if ($entity->$primaryKey !== $original->$primaryKey) {
            PrimaryKeyImmutableException::raise('Cannot update entity {entity}: primary key is immutable', ['entity' => $entityClass]);
        }

        $fields = $this->entityMetadata->getFields($entityClass);
        // Unset fields on $entity follow $original (no null column semantics); needed for diff/validate.
        foreach ($fields as $field) {
            if (!isset($entity->$field) && isset($original->$field)) {
                $entity->$field = $original->$field;
            }
        }

        $originalValues = $this->entityHydrator->dehydrate($original, $fields);
        $entityValues = $this->entityHydrator->dehydrate($entity, $fields);
        $changedFields = $this->getChangedFields($fields, $entityValues, $originalValues);

        if ($changedFields === []) {
            $this->eventDispatcher->dispatch(new EntityUnchanged($entity, $original));
            return $entity;
        }

        // Incoming diff only: fail fast before filler / {@see EntityUpdating} side effects.
        $this->validate($entity, $changedFields);

        $this->autoFiller->onUpdating($entity);
        $this->dispatchEvent(new EntityUpdating($entity, $original));

        $entityValues = $this->entityHydrator->dehydrate($entity, $fields);

        $fieldValues = [];
        foreach ($fields as $field) {
            if (!isset($entityValues[$field])) {
                continue;
            }

            if (!isset($originalValues[$field]) || $entityValues[$field] !== $originalValues[$field]) {
                $fieldValues[$field] = $entityValues[$field];
            }
        }

        if ($fieldValues === []) {
            $this->eventDispatcher->dispatch(new EntityUnchanged($entity, $original));

            return $entity;
        }

        [$connection, $table] = $this->sharding->getUniqueShard($entityClass, $entity);

        $useTransaction = $this->transactionManager->useTransaction($connection);
        if ($useTransaction) {
            $this->transactionManager->ensureConnection($connection);
        }

        $columnMap = $this->entityMetadata->getColumnMap($entityClass);
        foreach ($columnMap as $property => $column) {
            if (array_key_exists($property, $fieldValues)) {
                $fieldValues[$column] = $fieldValues[$property];
                unset($fieldValues[$property]);
            }
        }

        $db = $useTransaction
            ? $this->transactionManager->getCurrentClient($connection)
            : $this->namedLookup->by(ClientInterface::class, $connection);
        $db->update($table, $fieldValues, [$columnMap[$primaryKey] ?? $primaryKey => $entity->$primaryKey]);

        $this->dispatchEvent(new EntityUpdated($entity, $original));

        return $entity;
    }

    /** {@inheritDoc} */
    public function delete(Entity $entity): Entity
    {
        $entityClass = $entity::class;

        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);

        if (!isset($entity->$primaryKey)) {
            PrimaryKeyMissingException::raise('Cannot delete entity {entity}: primary key value is missing', ['entity' => $entityClass]);
        }

        [$connection, $table] = $this->sharding->getUniqueShard($entityClass, $entity);

        $useTransaction = $this->transactionManager->useTransaction($connection);
        if ($useTransaction) {
            $this->transactionManager->ensureConnection($connection);
        }

        $this->dispatchEvent(new EntityDeleting($entity));

        $columnMap = $this->entityMetadata->getColumnMap($entityClass);
        $db = $useTransaction
            ? $this->transactionManager->getCurrentClient($connection)
            : $this->namedLookup->by(ClientInterface::class, $connection);

        $db->delete($table, [$columnMap[$primaryKey] ?? $primaryKey => $entity->$primaryKey]);

        $this->dispatchEvent(new EntityDeleted($entity));

        return $entity;
    }
}
