<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionMethod;
use Switon\Core\InputInterface;
use Switon\Core\Exception\InvalidArgumentException;
use Switon\Orm\Attribute\PageLimit;
use Switon\Orm\Page;
use Switon\Orm\PageResolver;
use Switon\Orm\Tests\TestCase;

/**
 * @group orm
 */
#[AllowMockObjectsWithoutExpectations]
class PageResolverTest extends TestCase
{
    public function testResolveBuildsPageFromInput(): void
    {
        $resolver = $this->createResolver(2, 30);
        $parameter = (new ReflectionMethod(PageResolverNoLimitFixture::class, 'indexAction'))->getParameters()[0];

        $page = $resolver->resolve($parameter, Page::class);

        $this->assertSame(2, $page->getPage());
        $this->assertSame(30, $page->getLimit());
    }

    public function testResolveUsesMethodPageLimit(): void
    {
        $resolver = $this->createResolver(2, 10);
        $parameter = (new ReflectionMethod(PageResolverFixture::class, 'methodLimitAction'))->getParameters()[0];

        $page = $resolver->resolve($parameter, Page::class);

        $this->assertSame(2, $page->getPage());
        $this->assertSame(10, $page->getLimit());
    }

    public function testResolveThrowsWhenMethodLimitExceeded(): void
    {
        $resolver = $this->createResolver(3, 10);
        $parameter = (new ReflectionMethod(PageResolverFixture::class, 'methodLimitAction'))->getParameters()[0];

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($parameter, Page::class);
    }

    public function testResolveUsesDefaultMaxWhenNoPageLimitAnnotation(): void
    {
        $resolver = $this->createResolver(50, 50);
        $parameter = (new ReflectionMethod(PageResolverNoLimitFixture::class, 'indexAction'))->getParameters()[0];

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($parameter, Page::class);
    }

    public function testResolveThrowsWhenPageIsNotPositive(): void
    {
        $resolver = $this->createResolver(0, 10);
        $parameter = (new ReflectionMethod(PageResolverNoLimitFixture::class, 'indexAction'))->getParameters()[0];

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($parameter, Page::class);
    }

    public function testResolveThrowsWhenSizeIsNotPositive(): void
    {
        $resolver = $this->createResolver(1, 0);
        $parameter = (new ReflectionMethod(PageResolverNoLimitFixture::class, 'indexAction'))->getParameters()[0];

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($parameter, Page::class);
    }

    public function testResolveThrowsWhenMethodLimitIsNotPositive(): void
    {
        $resolver = $this->createResolver(1, 10);
        $parameter = (new ReflectionMethod(PageResolverInvalidLimitFixture::class, 'invalidLimitAction'))->getParameters()[0];

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve($parameter, Page::class);
    }

    protected function createResolver(int $page, int $size): PageResolver
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('get')->willReturnMap([
            ['page', 1, $page],
            ['size', 10, $size],
        ]);

        return new class($input) extends PageResolver {
            public function __construct(InputInterface $input)
            {
                $this->input = $input;
            }
        };
    }
}

class PageResolverFixture
{
    #[PageLimit(20)]
    public function methodLimitAction(Page $page): void
    {
    }
}

class PageResolverNoLimitFixture
{
    public function indexAction(Page $page): void
    {
    }
}

class PageResolverInvalidLimitFixture
{
    #[PageLimit(0)]
    public function invalidLimitAction(Page $page): void
    {
    }
}
