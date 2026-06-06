<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Declares the naming strategy used for inferred table and column names on an entity.
 *
 * Guidance: Use this only when inference should differ from the default strategy; explicit <code>Table</code> or <code>Column</code> mappings still win.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getNamingStrategy()
 * @see \Switon\Orm\NamingStrategyInterface
 * @see \Switon\Orm\NamingStrategy\CamelNamingStrategy
 */
#[Attribute(Attribute::TARGET_CLASS)]
class NamingStrategy
{
    /**
     * Built-in camel naming strategy constant.
     *
     * Keeps camelCase property names unchanged and converts
     * PascalCase class names to camelCase table names.
     */
    public const string CAMEL = 'Switon\Orm\NamingStrategy\CamelNamingStrategy';

    /**
     * Initialize the naming strategy attribute.
     *
     * @param string $strategy The naming strategy class name or constant
     */
    public function __construct(public string $strategy)
    {

    }
}
