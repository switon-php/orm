<?php

declare(strict_types=1);

namespace Switon\Orm;

use ReflectionAttribute;
use ReflectionParameter;
use Switon\Binding\ValueResolverInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\InputInterface;
use Switon\Core\Exception\InvalidArgumentException;
use Switon\Orm\Attribute\PageLimit;

/**
 * Resolves Page from request input and validates optional PageLimit annotation.
 *
 * Road-signs:
 * - ValueResolverInterface
 * - input page/size
 * - PageLimit max
 * - page*size guard
 *
 * Guidance: Keep pagination policy minimal here; enforce depth guard only and leave query strategy to caller code.
 *
 * @see \Switon\Binding\ValueResolverInterface
 * @see \Switon\Orm\Page
 * @see \Switon\Orm\Attribute\PageLimit
 * @see \Switon\Orm\RepositoryInterface::paginate()
 */
class PageResolver implements ValueResolverInterface
{
    #[Autowired] protected InputInterface $input;
    #[Autowired] protected int $max = 1000;

    public function resolve(ReflectionParameter $parameter, string $type): mixed
    {
        $rawPage = (int)$this->input->get('page', 1);
        $rawSize = (int)$this->input->get('size', 10);
        if ($rawPage < 1 || $rawSize < 1) {
            InvalidArgumentException::raise(
                'Pagination page and size must be > 0, got page={page}, size={size}.',
                ['page' => $rawPage, 'size' => $rawSize]
            );
        }
        $page = Page::of($rawPage, $rawSize);

        $max = $this->resolveLimit($parameter);
        if ($max < 1) {
            InvalidArgumentException::raise('Pagination max must be >= 1, got {max}.', ['max' => $max]);
        }

        $depth = $page->getPage() * $page->getLimit();
        if ($depth > $max) {
            InvalidArgumentException::raise(
                'Pagination depth exceeded: page({page}) * size({size}) = {depth}, max is {max}.',
                ['page' => $page->getPage(), 'size' => $page->getLimit(), 'depth' => $depth, 'max' => $max]
            );
        }

        return $page;
    }

    protected function resolveLimit(ReflectionParameter $parameter): int
    {
        $rMethod = $parameter->getDeclaringFunction();

        $methodLimit = $rMethod->getAttributes(PageLimit::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if ($methodLimit !== null) {
            return $methodLimit->newInstance()->max;
        }

        return $this->max;
    }
}
