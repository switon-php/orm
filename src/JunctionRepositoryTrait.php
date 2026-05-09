<?php

declare(strict_types=1);

namespace Switon\Orm;

use ReflectionClass;
use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Exception\JunctionFieldsInferenceException;
use Switon\Orm\Relation\BelongsToRelation;

/**
 * Sync and attach helpers for junction repositories.
 *
 * Road-signs:
 * - infer two sides from BelongsTo
 * - sync computes attach and detach
 * - attach can fill prefixed fields
 * - inference results are cached
 *
 * Guidance: Use this only on repositories whose entity defines the expected two <code>BelongsTo</code> relations.
 *
 * @phpstan-require-extends AbstractRepository
 *
 * @see \Switon\Orm\AbstractRepository
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Orm\Attribute\BelongsTo
 * @see \Switon\Orm\Relation\HasManyToManyRelation
 * @see \Switon\Orm\Exception\JunctionFieldsInferenceException
 */
trait JunctionRepositoryTrait
{
    /**
     * Cache for junction field information to avoid repeated computation.
     *
     * Caches the result of field inference for each entity class. The cache key is the entity class name,
     * and the value is a tuple containing [selfField, relatedField, relatedEntityClass].
     *
     * @var array<string, array{0: string, 1: string, 2: string}> Cache mapping:
     *      [entityClass => [selfField, relatedField, relatedEntityClass]]
     */
    protected array $junctionFieldsCache = [];

    /**
     * Syncs the many-to-many relationship to match the provided list.
     *
     * This method ensures the relationship exactly matches the provided list by:
     * - Removing relationships that exist but are not in the list
     * - Adding relationships that are in the list but don't exist
     * - Leaving existing relationships that are in the list unchanged
     *
     * This is the most commonly used method for updating many-to-many relationships from a form or API request.
     *
     * @param string $entityClass The entity class for the primary side (e.g., Admin::class)
     * @param int|string $entityId The primary entity ID (e.g., $admin_id)
     * @param array $relatedIds Array of related entity IDs to sync to (e.g., $role_ids)
     *
     * @return array{attached: int, detached: int} Statistics:
     *         - `attached`: Number of new relationships created
     *         - `detached`: Number of relationships removed
     *
     * @example
     * // Sync admin roles: ensure admin has exactly these roles
     * $result = $adminRoleRepository->sync(Admin::class, $admin_id, [1, 2, 3]);
     * // If admin previously had roles [1, 4, 5], and now should have [1, 2, 3]:
     * // - Removes: [4, 5] (detached: 2)
     * // - Adds: [2, 3] (attached: 2)
     * // - Keeps: [1] (unchanged)
     * // Returns: ['attached' => 2, 'detached' => 2]
     */
    public function sync(string $entityClass, int|string $entityId, array $relatedIds): array
    {
        [$selfField, $relatedField] = $this->getJunctionFields($entityClass);

        $existing = $this->values([$selfField => $entityId], $relatedField);
        $toRemove = array_diff($existing, $relatedIds);
        $toAdd = array_diff($relatedIds, $existing);

        $detached = $toRemove ? $this->deleteAll([$selfField => $entityId, $relatedField => array_values($toRemove)]) : 0;
        $attached = $toAdd ? $this->attach($entityClass, $entityId, $toAdd) : 0;

        return ['attached' => $attached, 'detached' => $detached];
    }

    /**
     * Attaches new relationships (only adds, doesn't remove existing ones).
     *
     * This method adds new relationships without removing any existing ones. Useful when you want to
     * grant additional permissions or roles without affecting existing ones.
     *
     * **Behavior:**
     * - Only creates relationships that don't already exist
     * - Skips relationships that already exist (no error thrown)
     * - Automatically fills matching fields from related entities
     *
     * @param string $entityClass The entity class for the primary side (e.g., Admin::class)
     * @param int|string $entityId The primary entity ID (e.g., $admin_id)
     * @param array $relatedIds Array of related entity IDs to attach (e.g., $role_ids)
     *
     * @return int Number of new relationships created (skips duplicates)
     *
     * @example
     * // Add roles to admin without removing existing ones
     * $count = $adminRoleRepository->attach(Admin::class, $admin_id, [2, 3]);
     * // If admin already has role [1], and we attach [2, 3]:
     * // - Skips: [1] (already exists)
     * // - Adds: [2, 3] (new relationships)
     * // Returns: 2 (number of new relationships created)
     */
    public function attach(string $entityClass, int|string $entityId, array $relatedIds): int
    {
        [$selfField, $relatedField, $relatedEntityClass] = $this->getJunctionFields($entityClass);
        $existing = $this->values([$selfField => $entityId], $relatedField);
        $toAdd = array_diff($relatedIds, $existing);

        if (!$toAdd) {
            return 0;
        }

        $junctionClass = $this->getEntityClass();

        // Load entities in batch for better performance
        $entityRepo = $this->entityMetadata->getRepository($entityClass);
        $entity = $entityRepo->get($entityId);

        $relatedPrimaryKey = $this->entityMetadata->getPrimaryKey($relatedEntityClass);
        $relatedRepo = $this->entityMetadata->getRepository($relatedEntityClass);
        $relatedEntities = $relatedRepo->allBy([$relatedPrimaryKey => array_values($toAdd)], $relatedPrimaryKey);

        // Create entities one-by-one to ensure each goes through the full Repository
        // lifecycle (validation, events, auto-filling). Junction entities may have event
        // listeners (e.g., logging, cache invalidation) that require per-entity triggering.
        $count = 0;
        foreach ($toAdd as $relatedId) {
            $junctionEntity = new $junctionClass();
            $junctionEntity->$selfField = $entityId;
            $junctionEntity->$relatedField = $relatedId;

            // Automatically fill all matching fields using pre-loaded entities
            $this->autoFillFieldsFromEntities($junctionEntity, $entity, $relatedEntities[$relatedId] ?? null);

            $this->create($junctionEntity);
            $count++;
        }

        return $count;
    }

    /**
     * Detaches relationships (removes specified or all relationships).
     *
     * This method removes relationships. If no related IDs are provided, removes all relationships
     * for the specified entity.
     *
     * **Behavior:**
     * - Removes only the specified relationships if `$relatedIds` is provided
     * - Removes all relationships for the entity if `$relatedIds` is empty
     * - Safe to call even if relationships don't exist (no error thrown)
     *
     * @param string $entityClass The entity class for the primary side (e.g., Admin::class)
     * @param int|string $entityId The primary entity ID (e.g., $admin_id)
     * @param array $relatedIds Array of related entity IDs to detach (default: empty array = detach all)
     *
     * @return int Number of relationships removed
     *
     * @example
     * // Remove specific roles
     * $count = $adminRoleRepository->detach(Admin::class, $admin_id, [1, 2]);
     * // Removes roles [1, 2] from admin
     * // Returns: 2 (number of relationships removed)
     *
     * // Remove all roles from admin
     * $count = $adminRoleRepository->detach(Admin::class, $admin_id);
     * // Removes all relationships for this admin
     * // Returns: number of all relationships removed
     */
    public function detach(string $entityClass, int|string $entityId, array $relatedIds = []): int
    {
        [$selfField, $relatedField] = $this->getJunctionFields($entityClass);
        $conditions = [$selfField => $entityId];

        if ($relatedIds) {
            $conditions[$relatedField] = array_values($relatedIds);
        }

        return $this->deleteAll($conditions);
    }

    /**
     * Gets junction field information from BelongsTo relationships.
     *
     * This method automatically infers the foreign key fields and related entity class by analyzing
     * the BelongsTo relationships defined on the junction entity. Results are cached to avoid
     * repeated computation.
     *
     * **Inference Logic:**
     * - Finds all `#[BelongsTo]` relationships on the junction entity
     * - Matches the provided entity class to determine which is "self" (primary) and which is "related"
     * - Extracts foreign key field names from the relationships
     * - Caches results for performance
     *
     * @param string $entityClass The entity class for the primary side (e.g., Admin::class)
     *
     * @return array{0: string, 1: string, 2: string} Tuple containing:
     *         - `[0]` selfField: Foreign key field name in junction entity for primary entity (e.g., `admin_id`)
     *         - `[1]` relatedField: Foreign key field name in junction entity for related entity (e.g., `role_id`)
     *         - `[2]` relatedEntityClass: Related entity class name (e.g., `Role::class`)
     */
    protected function getJunctionFields(string $entityClass): array
    {
        // Check cache first
        if (isset($this->junctionFieldsCache[$entityClass])) {
            return $this->junctionFieldsCache[$entityClass];
        }

        $junctionClass = $this->getEntityClass();
        $relations = $this->entityMetadata->getRelations($junctionClass);
        $selfField = null;
        $relatedField = null;
        $relatedEntityClass = null;

        // Get related entity classes from BelongsTo relationships via reflection
        $rClass = new ReflectionClass($junctionClass);
        foreach ($rClass->getProperties() as $property) {
            if ($property->getAttributes(BelongsTo::class) !== []) {
                $relationName = $property->getName();
                $relation = $relations[$relationName] ?? null;
                if ($relation instanceof BelongsToRelation) {
                    $fk = $relation->getForeignKey();

                    // Get related entity class from property type
                    $type = $property->getType();
                    if ($type && !$type->isBuiltin()) {
                        $relatedEntityClass = $type->getName();

                        // Determine which is "self" (matching $entityClass) and which is "related"
                        if ($relatedEntityClass === $entityClass) {
                            $selfField = $fk;
                        } else {
                            $relatedField = $fk;
                            $relatedEntityClass = $relatedEntityClass;
                        }
                    }
                }
            }
        }

        if (!$selfField || !$relatedField || !$relatedEntityClass) {
            JunctionFieldsInferenceException::raise(
                'Cannot infer junction fields for {junction}: define two BelongsTo relations.',
                ['junction' => $junctionClass]
            );
        }

        $this->junctionFieldsCache[$entityClass] = [$selfField, $relatedField, $relatedEntityClass];

        return $this->junctionFieldsCache[$entityClass];
    }

    /**
     * Automatically fills all matching fields from pre-loaded entities.
     *
     * This method is optimized for batch operations where entities are already loaded. It fills
     * matching fields from both the primary entity and the related entity based on foreign key prefixes.
     *
     * @param Entity $junctionEntity The junction entity being created (will be modified)
     * @param Entity $entity The primary entity (already loaded, e.g., Admin)
     * @param Entity|null $relatedEntity The related entity (already loaded, e.g., Role, null if not found)
     */
    protected function autoFillFieldsFromEntities(
        Entity  $junctionEntity,
        Entity  $entity,
        ?Entity $relatedEntity
    ): void
    {
        $junctionFields = $this->entityMetadata->getFields($this->getEntityClass());
        $junctionFieldsMap = array_flip($junctionFields);

        // Fill fields from main entity (e.g., Admin)
        $this->fillMatchingFieldsFromEntity($junctionEntity, $entity, $junctionFieldsMap);

        // Fill fields from related entity (e.g., Role)
        if ($relatedEntity) {
            $this->fillMatchingFieldsFromEntity($junctionEntity, $relatedEntity, $junctionFieldsMap);
        }
    }

    /**
     * Automatically fills all matching fields from related entities (loads entities on-demand).
     *
     * This method loads entities on-demand. For batch operations, use {@see self::autoFillFieldsFromEntities() autoFillFieldsFromEntities}
     * instead for better performance.
     *
     * @param Entity $junctionEntity The junction entity being created (will be modified)
     * @param string $entityClass The primary entity class (e.g., Admin::class)
     * @param int|string $entityId The primary entity ID
     * @param string $relatedEntityClass The related entity class (e.g., Role::class)
     * @param int|string $relatedId The related entity ID
     */
    protected function autoFillFields(
        Entity     $junctionEntity,
        string     $entityClass,
        int|string $entityId,
        string     $relatedEntityClass,
        int|string $relatedId
    ): void
    {
        $entityRepo = $this->entityMetadata->getRepository($entityClass);
        $entity = $entityRepo->get($entityId);

        $relatedRepo = $this->entityMetadata->getRepository($relatedEntityClass);
        $relatedEntity = $relatedRepo->get($relatedId);

        $this->autoFillFieldsFromEntities($junctionEntity, $entity, $relatedEntity);
    }

    /**
     * Fills all matching fields from a pre-loaded entity based on foreign key prefix.
     *
     * This method automatically copies fields from the related entity to the junction entity if they
     * match the naming convention. The matching is based on extracting the prefix from the foreign key.
     *
     * **Matching Rules:**
     * - Extracts prefix from foreign key: `admin_id` → prefix `admin_`
     * - Finds all fields in related entity starting with prefix: `admin_code`, `admin_url`, `admin_name`, etc.
     * - If junction entity also has the same field, copies the value (only if not already set)
     *
     * **Example:**
     * - Foreign key: `admin_id` → prefix: `admin_`
     * - Related entity (Admin) fields: `admin_code`, `admin_url`, `admin_name`
     * - Junction entity fields: `admin_code`, `admin_url`, `admin_name`
     * - Result: All matching fields are automatically copied from Admin to junction entity
     *
     * @param Entity $junctionEntity The junction entity being created (will be modified)
     * @param Entity $relatedEntity The related entity (already loaded, e.g., Admin or Role)
     * @param array $junctionFieldsMap Map of junction entity field names for fast lookup
     */
    protected function fillMatchingFieldsFromEntity(
        Entity $junctionEntity,
        Entity $relatedEntity,
        array  $junctionFieldsMap
    ): void
    {
        $relatedEntityClass = $relatedEntity::class;

        // Get the referenced key (e.g., admin_id)
        $referencedKey = $this->entityMetadata->getReferencedKey($relatedEntityClass);

        // Extract prefix (e.g., admin_id -> admin_)
        if (str_ends_with($referencedKey, '_id')) {
            $prefix = substr($referencedKey, 0, -3) . '_';  // admin_id -> admin_
        } else {
            return; // Doesn't follow naming convention, skip
        }

        // Get all fields from related entity
        $relatedFields = $this->entityMetadata->getFields($relatedEntityClass);

        // Iterate through all fields in related entity and find matching ones
        foreach ($relatedFields as $field) {
            // Check if field starts with the prefix
            if (str_starts_with($field, $prefix)) {
                // Check if junction entity also has the same field
                if (isset($junctionFieldsMap[$field]) && !isset($junctionEntity->$field)) {
                    // If field exists and has a value, copy it
                    if (isset($relatedEntity->$field)) {
                        $junctionEntity->$field = $relatedEntity->$field;
                    }
                }
            }
        }
    }

    /**
     * Fills all matching fields from related entity (loads entity on-demand).
     *
     * This method loads the entity on-demand. For batch operations, use
     * {@see self::fillMatchingFieldsFromEntity() fillMatchingFieldsFromEntity} instead for better performance.
     *
     * @param Entity $junctionEntity The junction entity being created (will be modified)
     * @param string $relatedEntityClass The related entity class (e.g., Admin::class or Role::class)
     * @param int|string $relatedId The related entity ID
     * @param array $junctionFieldsMap Map of junction entity field names for fast lookup
     */
    protected function fillMatchingFields(
        Entity     $junctionEntity,
        string     $relatedEntityClass,
        int|string $relatedId,
        array      $junctionFieldsMap
    ): void
    {
        $relatedRepo = $this->entityMetadata->getRepository($relatedEntityClass);
        $relatedEntity = $relatedRepo->get($relatedId);
        $this->fillMatchingFieldsFromEntity($junctionEntity, $relatedEntity, $junctionFieldsMap);
    }
}
