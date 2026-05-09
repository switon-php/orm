<?php

declare(strict_types=1);

namespace Switon\Orm;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\Event\EntityEventInterface;
use Switon\Query\QueryInterface;
use Switon\Validating\ValidatorInterface;

/**
 * Base persistence pipeline for entity managers.
 *
 * Road-signs:
 * - validate constraint-backed fields
 * - dispatch entity lifecycle events
 * - query delegates to queryBuilder
 * - relation loading delegates to relationManager
 *
 * Guidance: Keep normal writes on create/update/delete paths so validation, fillers, and events stay aligned.
 *
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\EntityMetadataInterface
 * @see \Switon\Orm\EntityFillerInterface
 * @see \Switon\Orm\QueryBuilderInterface
 * @see \Switon\Orm\RelationManagerInterface
 * @see \Switon\Orm\Event\EntityEventInterface
 */
abstract class AbstractEntityManager implements EntityManagerInterface
{
    #[Autowired] protected EntityFillerInterface $autoFiller;
    #[Autowired] protected EntityHydratorInterface $entityHydrator;
    #[Autowired] protected EntityMetadataInterface $entityMetadata;
    #[Autowired] protected ShardingInterface $sharding;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected ValidatorInterface $validator;
    #[Autowired] protected RelationManagerInterface $relationManager;

    /**
     * Validates entity fields using constraint attributes.
     *
     * Validates the specified fields of an entity using the constraints defined
     * in the entity's metadata. Only validates fields that have constraints defined.
     *
     * @param Entity $entity The entity to validate
     * @param array $fields Array of field names to validate
     * @return void
     */
    protected function validate(Entity $entity, array $fields): void
    {
        $entityClass = $entity::class;

        $constraints = $this->entityMetadata->getConstraints($entityClass);

        $validation = $this->validator->beginValidate($entity);
        foreach ($fields as $field) {
            if (($fieldConstraints = $constraints[$field] ?? []) !== []) {
                $validation->field = $field;
                $validation->value = $entity->$field ?? null;

                foreach ($fieldConstraints as $constraint) {
                    if (!$validation->validate($constraint)) {
                        break;
                    }
                }

                if (!$validation->hasError($field)) {
                    $entity->$field = $validation->value;
                }
            }
        }
        $this->validator->endValidate($validation);
    }

    /**
     * Gets the query builder instance for creating queries.
     *
     * @return \Switon\Orm\QueryBuilderInterface Query builder instance
     */
    abstract protected function getQueryBuilder(): \Switon\Orm\QueryBuilderInterface;

    /**
     * {@inheritDoc}
     */
    public function query(string $entityClass, ?string $alias = null): QueryInterface
    {
        return $this->getQueryBuilder()->create($entityClass, $alias);
    }

    /**
     * Dispatches an entity lifecycle event.
     *
     * Calls the entity's onEvent() method first, then dispatches the event
     * through the PSR-14 event dispatcher for external listeners.
     *
     * @param EntityEventInterface $event The entity event to dispatch
     * @return void
     */
    protected function dispatchEvent(EntityEventInterface $event): void
    {
        $entity = $event->getEntity();

        $entity->onEvent($event);

        $this->eventDispatcher->dispatch($event);
    }
}
