<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Declares the date format used for class-level or field-level date conversion.
 *
 * Guidance: Use property-level format only for real exceptions; class-level defaults keep metadata simpler and more predictable.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getDateFormat()
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class DateFormat
{
    protected string $format;

    /**
     * Initialize the date format attribute.
     *
     * @param string $format The date format string following PHP DateTime format specification
     */
    public function __construct(string $format)
    {
        $this->format = $format;
    }

    /**
     * Get the configured date format string.
     *
     * @return string The date format string
     */
    public function get(): string
    {
        return $this->format;
    }
}
