<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Repository;

use Switon\Orm\AbstractRepository;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\Tests\Fixtures\Entity\AttributeDirectedEntity;
use LogicException;

class AttributeDirectedCustomRepository extends AbstractRepository
{
    protected string $entityClass = AttributeDirectedEntity::class;

    protected function getEntityManager(): EntityManagerInterface
    {
        throw new LogicException('AttributeDirectedCustomRepository is only used for metadata tests.');
    }

    protected function getQueryBuilder(): QueryBuilderInterface
    {
        throw new LogicException('AttributeDirectedCustomRepository is only used for metadata tests.');
    }
}
