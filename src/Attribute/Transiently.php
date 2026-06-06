<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

/**
 * Marker interface for property attributes that keep a field off the persistence path.
 *
 * Guidance: Application code should usually use <code>Transient</code>; relation attributes already implement this marker indirectly.
 *
 * @see \Switon\Orm\Attribute\Transient
 * @see \Switon\Orm\Attribute\RelationAttribute
 * @see \Switon\Orm\EntityMetadata::warmupFields()
 */
interface Transiently
{
}
