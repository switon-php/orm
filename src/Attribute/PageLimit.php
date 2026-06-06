<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Declares the maximum pagination depth for one action.
 *
 * Road-signs:
 * - method annotation
 * - PageResolver
 * - max depth cap
 * - Page paginate
 *
 * Guidance: Keep max aligned with query cost tolerance and enforce it in PageResolver.
 *
 * @see \Switon\Orm\PageResolver
 * @see \Switon\Orm\Page
 * @see \Switon\Orm\RepositoryInterface::paginate()
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class PageLimit
{
    public function __construct(
        public int $max
    ) {
    }
}
