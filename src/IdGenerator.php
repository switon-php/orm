<?php

declare(strict_types=1);

namespace Switon\Orm;

use ReflectionProperty;
use Switon\Core\Attribute\Autowired;
use Switon\Core\MakerInterface;
use Switon\Db\ClientInterface;
use Switon\Di\NamedLookupInterface;
use Switon\Id\IdGeneratorInterface as IdPackageInterface;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Exception\InvalidIdStrategyException;

/**
 * Default implementation for ORM ID generation and pre-allocation.
 *
 * Road-signs:
 * - read strategy from Id
 * - cache generator by entity class
 * - custom generators come from the id package
 * - auto strategy allocates ranges through the database client
 *
 * Guidance: Keep <code>fillIds()</code> batches homogeneous in entity type and shard target.
 *
 * @see \Switon\Orm\IdGeneratorInterface
 * @see \Switon\Orm\Attribute\Id
 * @see \Switon\Orm\ShardingInterface
 */
class IdGenerator implements IdGeneratorInterface
{
    /**
     * Strategy to ID generator alias mapping.
     *
     * @var array<string, string>
     */
    #[Autowired] protected array $strategies = [
        'snowflake' => 'Switon\\Id\\IdGeneratorInterface#snowflake',
        'uuid' => 'Switon\\Id\\IdGeneratorInterface#uuid4',
        'uuid-v7' => 'Switon\\Id\\IdGeneratorInterface#uuid7',
        'ulid' => 'Switon\\Id\\IdGeneratorInterface#ulid',
        'nanoId' => 'Switon\\Id\\IdGeneratorInterface#nanoId',
        'ksuid' => 'Switon\\Id\\IdGeneratorInterface#ksuid',
        'mongoId' => 'Switon\\Id\\IdGeneratorInterface#mongoId',
        'cuid2' => 'Switon\\Id\\IdGeneratorInterface#cuid2',
    ];

    #[Autowired] protected ShardingInterface $sharding;
    #[Autowired] protected NamedLookupInterface $namedLookup;
    #[Autowired] protected MakerInterface $maker;
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    /** @var array<string, IdPackageInterface|null> Cached generator instances */
    protected array $generatorsCache = [];

    /**
     * Get the ID generator for an entity class (internal use)
     */
    protected function getGeneratorForClass(string $entityClass): ?IdPackageInterface
    {
        if (!array_key_exists($entityClass, $this->generatorsCache)) {
            $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);

            $rProperty = new ReflectionProperty($entityClass, $primaryKey);

            $attributes = $rProperty->getAttributes(Id::class);
            /** @var Id $id */
            $id = !empty($attributes) ? $attributes[0]->newInstance() : null;

            if ($id === null || $id->strategy === null || $id->strategy === 'auto') {
                $this->generatorsCache[$entityClass] = null; // Use database auto-increment
            } else {
                $generatorAlias = $this->strategies[$id->strategy] ?? null;
                if ($generatorAlias === null) {
                    InvalidIdStrategyException::raise('Unknown ID generation strategy: {strategy}', ['strategy' => $id->strategy]);
                }
                $this->generatorsCache[$entityClass] = $this->maker->make($generatorAlias);
            }
        }

        return $this->generatorsCache[$entityClass];
    }

    public function generateId(Entity $entity): null|int|string
    {
        $generator = $this->getGeneratorForClass($entity::class);
        if ($generator !== null) {
            // Adapt ID package generator to ORM interface
            return $generator->next();
        } else {
            return null;
        }
    }

    public function fillId(Entity $entity): void
    {
        $entityClass = $entity::class;
        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);
        $generator = $this->getGeneratorForClass($entityClass);
        if ($generator !== null) {
            $entity->$primaryKey = $generator->next();
        } else {
            // For auto-increment, we don't set the ID here
            // It will be set by the database when the record is inserted
        }
    }

    public function fillIds(array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        $entityClass = $entities[0]::class;
        $primaryKey = $this->entityMetadata->getPrimaryKey($entityClass);
        $entitiesNeedingIds = [];

        foreach ($entities as $entity) {
            if (!isset($entity->$primaryKey)) {
                $entitiesNeedingIds[] = $entity;
            }
        }

        if (empty($entitiesNeedingIds)) {
            return;
        }

        // Generate IDs for all entities needing them.
        // For sharded entities, context must be provided; otherwise sharding manager will resolve all shards.
        // createMany() ensures all entities resolve to a single shard before calling fillIds().
        $generatedIds = $this->generateIds($entityClass, count($entitiesNeedingIds), $entitiesNeedingIds[0]);

        // Assign generated IDs to entities
        foreach ($entitiesNeedingIds as $i => $entity) {
            $entity->$primaryKey = $generatedIds[$i];
        }
    }

    public function generateIds(string $entityClass, int $count, $context = null): array
    {
        $generator = $this->getGeneratorForClass($entityClass);
        if ($generator !== null) {
            // Adapt ID package generator to ORM interface
            $ids = [];
            for ($i = 0; $i < $count; $i++) {
                $ids[] = $generator->next();
            }
            return $ids;
        }

        // Get primary key property name and convert to column name for database operations
        $primaryKeyProperty = $this->entityMetadata->getPrimaryKey($entityClass);
        $columnMap = $this->entityMetadata->getColumnMap($entityClass);
        $primaryKeyColumn = $columnMap[$primaryKeyProperty] ?? $primaryKeyProperty;

        [$connection, $table] = $this->sharding->getUniqueShard($entityClass, $context);
        $db = $this->namedLookup->by(ClientInterface::class, $connection);

        [$startId, $endId] = $db->allocateIds($table, $primaryKeyColumn, $count);
        // allocateIds returns [startId, endId] where both are inclusive
        // range() returns a 0-indexed array, perfect for foreach with $i
        return range($startId, $endId);
    }
}
