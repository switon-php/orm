<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use Switon\Orm\Page;
use Switon\Orm\Tests\TestCase;

class PageAdvancedTest extends TestCase
{
    public function testPageOfCreatesInstanceWithDefaultLimit(): void
    {
        $page = Page::of(1);

        $this->assertSame(1, $page->getPage());
        $this->assertSame(10, $page->getLimit());
    }

    public function testPageOfCreatesInstanceWithCustomLimit(): void
    {
        $page = Page::of(2, 25);

        $this->assertSame(2, $page->getPage());
        $this->assertSame(25, $page->getLimit());
    }

    public function testPageWorksWithPageNumberOne(): void
    {
        $page = Page::of(1, 20);

        $this->assertSame(1, $page->getPage());
        $this->assertSame(20, $page->getLimit());
    }

    public function testPageWorksWithLargePageNumbers(): void
    {
        $page = Page::of(100, 50);

        $this->assertSame(100, $page->getPage());
        $this->assertSame(50, $page->getLimit());
    }

    public function testPageWorksWithSmallLimits(): void
    {
        $page = Page::of(1, 1);

        $this->assertSame(1, $page->getPage());
        $this->assertSame(1, $page->getLimit());
    }

    public function testPageWorksWithLargeLimits(): void
    {
        $page = Page::of(1, 1000);

        $this->assertSame(1, $page->getPage());
        $this->assertSame(1000, $page->getLimit());
    }

    public function testMultiplePageInstancesAreIndependent(): void
    {
        $page1 = Page::of(1, 10);
        $page2 = Page::of(2, 20);
        $page3 = Page::of(3, 30);

        $this->assertSame(1, $page1->getPage());
        $this->assertSame(10, $page1->getLimit());
        $this->assertSame(2, $page2->getPage());
        $this->assertSame(20, $page2->getLimit());
        $this->assertSame(3, $page3->getPage());
        $this->assertSame(30, $page3->getLimit());
    }

    public function testGetPageReturnsCorrectPageNumber(): void
    {
        $page1 = Page::of(1, 10);
        $page5 = Page::of(5, 10);
        $page10 = Page::of(10, 10);

        $this->assertSame(1, $page1->getPage());
        $this->assertSame(5, $page5->getPage());
        $this->assertSame(10, $page10->getPage());
    }

    public function testGetLimitReturnsCorrectLimit(): void
    {
        $page1 = Page::of(1, 10);
        $page2 = Page::of(1, 25);
        $page3 = Page::of(1, 100);

        $this->assertSame(10, $page1->getLimit());
        $this->assertSame(25, $page2->getLimit());
        $this->assertSame(100, $page3->getLimit());
    }

    public function testPageCanBeUsedForPaginationCalculations(): void
    {
        $page1 = Page::of(1, 10);
        $page2 = Page::of(2, 10);
        $page3 = Page::of(3, 10);

        $offset1 = ($page1->getPage() - 1) * $page1->getLimit();
        $offset2 = ($page2->getPage() - 1) * $page2->getLimit();
        $offset3 = ($page3->getPage() - 1) * $page3->getLimit();

        $this->assertSame(0, $offset1);
        $this->assertSame(10, $offset2);
        $this->assertSame(20, $offset3);
    }
}
