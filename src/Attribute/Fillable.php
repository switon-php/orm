<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Explicitly marks whether a property is fillable (mass-assignable).
 *
 * Presence of this attribute means the decision has been confirmed (form/API or explicitly not).
 * - <code>#[Fillable]</code> or <code>#[Fillable(true)]</code>: fillable (default).
 * - <code>#[Fillable(false)]</code>: not fillable (explicitly confirmed).
 *
 * Only properties with {@see Id} or <code>#[Fillable(true)]</code> are fillable.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getFillable
 * @see \Switon\Orm\Attribute\Column For column name mapping only
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Fillable
{
    /**
     * @param bool $fillable true = fillable (default), false = explicitly not fillable
     */
    public function __construct(public bool $fillable = true)
    {
    }
}
