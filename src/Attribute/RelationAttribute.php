<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Switon\Core\MakerInterface;
use Switon\Orm\RelationInterface;

/**
 * Marker interface for relation attribute classes.
 *
 * This interface is implemented by all relation attribute classes (HasManyToMany, BelongsTo, etc.)
 * to allow EntityMetadata to discover them. It extends Transiently to indicate that properties
 * with relation attributes are transient (not persisted to database).
 *
 * Each relation attribute is responsible for creating its corresponding relation handler instance.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getRelations() Builder entry
 * @see \Switon\Orm\EntityMetadata::buildRelations() Attribute discovery + bind() boundary
 * @see \Switon\Orm\RelationManagerInterface Typical consumer
 * @see \Switon\Orm\RelationInterface::bind() Relation binding contract
 * @see \Switon\Orm\Attribute\BelongsTo
 * @see \Switon\Orm\Attribute\HasMany
 * @see \Switon\Orm\Attribute\HasOne
 * @see \Switon\Orm\Attribute\HasManyToMany
 */
interface RelationAttribute extends Transiently
{
    /**
     * Create the relation handler instance for this attribute.
     *
     * This method only creates and configures the relation handler with the attribute's
     * configuration parameters. The entity binding is done separately via bind().
     *
     * @param MakerInterface $maker The maker instance for creating relation handlers
     * @return RelationInterface The relation handler instance
     */
    public function createRelation(MakerInterface $maker): RelationInterface;
}
