<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Orm\Page;
use Switon\Orm\Tests\TestCase;

class PageTest extends TestCase
{
    public function testOfCreatesPageWithDefaultLimit(): void
    {
        $page = Page::of(1);

        $this->assertSame(1, $page->getPage());
        $this->assertSame(10, $page->getLimit());
    }

    public function testOfCreatesPageWithSpecifiedPageAndLimit(): void
    {
        $page = Page::of(2, 25);

        $this->assertSame(2, $page->getPage());
        $this->assertSame(25, $page->getLimit());
    }

    public function testGetPageReturnsPageNumber(): void
    {
        $page = Page::of(3, 15);

        $result = $page->getPage();

        $this->assertSame(3, $result);
    }

    public function testGetLimitReturnsLimit(): void
    {
        $page = Page::of(4, 30);

        $result = $page->getLimit();

        $this->assertSame(30, $result);
    }

    public function testPageInstancesAreDifferent(): void
    {
        $page1 = Page::of(1, 10);
        $page2 = Page::of(1, 10);

        $this->assertNotSame($page1, $page2);
    }
}

