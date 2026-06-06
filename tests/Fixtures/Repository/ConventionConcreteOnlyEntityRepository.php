<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Repository;

use Switon\Orm\AbstractRepository;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\Tests\Fixtures\Entity\ConventionConcreteOnlyEntity;
use LogicException;

/**
 * No matching *RepositoryInterface: EntityMetadata::getRepository must use this concrete class token.
 */
class ConventionConcreteOnlyEntityRepository extends AbstractRepository
{
    protected string $entityClass = ConventionConcreteOnlyEntity::class;

    protected function getEntityManager(): EntityManagerInterface
    {
        throw new LogicException('ConventionConcreteOnlyEntityRepository is only used for metadata resolution tests.');
    }

    protected function getQueryBuilder(): QueryBuilderInterface
    {
        throw new LogicException('ConventionConcreteOnlyEntityRepository is only used for metadata resolution tests.');
    }
}
