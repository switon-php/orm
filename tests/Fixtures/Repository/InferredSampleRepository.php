<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Repository;

use Switon\Orm\AbstractRepository;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\QueryBuilderInterface;
use LogicException;

class InferredSampleRepository extends AbstractRepository
{
    public function exposeEntityClass(): string
    {
        return $this->getEntityClass();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        throw new LogicException('Not used in inferEntityClass test');
    }

    protected function getQueryBuilder(): QueryBuilderInterface
    {
        throw new LogicException('Not used in inferEntityClass test');
    }
}
