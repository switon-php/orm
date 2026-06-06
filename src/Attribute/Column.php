<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Maps a property to a database column (optional name).
 *
 * Use when the column name differs from the property name. For mass-assignment (fillable), use {@see Fillable}.
 *
 * **Usage:**
 * <code>
 * #[Column('user_name')]
 * public string $username;  // property $username → column user_name
 * </code>
 *
 * If omitted, the property name is used as the column name.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getColumnMap
 * @see \Switon\Orm\Attribute\Fillable For fillable (mass-assignment) control
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * @param string|null $name Database column name. If null, uses property name.
     */
    public function __construct(public ?string $name = null)
    {
    }
}
