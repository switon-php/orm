<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Binding\Attribute\ResolvedBy;

/**
 * Immutable pagination input for repository queries.
 *
 * Road-signs:
 * - action param Page
 * - PageResolver
 * - PageLimit
 * - repository paginate
 *
 * Guidance: Use this as the repository pagination boundary instead of loose page and size integers.
 *
 * @see \Switon\Binding\Attribute\ResolvedBy
 * @see \Switon\Orm\RepositoryInterface::paginate()
 * @see \Switon\Query\Paginator
 * @see \Switon\Orm\PageResolver
 * @see \Switon\Orm\Attribute\PageLimit
 */
#[ResolvedBy(PageResolver::class)]
class Page
{
    protected int $page;
    protected int $limit;

    /**
     * Creates a normalized page request from caller-provided page and limit values.
     *
     * **Usage:**
     * <code>
     * $page = Page::of(1);      // Page 1, default 10 items per page
     * $page = Page::of(2, 25);  // Page 2, 25 items per page
     * </code>
     *
     * **Input Safety:** Invalid values (page < 1 or limit < 1) are automatically
     * corrected to 1.
     *
     * @param int $page Page number (1-indexed)
     * @param int $limit Number of items per page (default: 10)
     *
     * @return static New Page instance
     */
    public static function of(int $page, int $limit = 10): static
    {
        $instance = new static();

        $instance->page = max(1, $page);
        $instance->limit = max(1, $limit);

        return $instance;
    }

    /**
     * Returns the 1-based page number.
     *
     * @return int Page number (1-indexed)
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Returns the page size limit.
     *
     * @return int Number of items per page
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
}
