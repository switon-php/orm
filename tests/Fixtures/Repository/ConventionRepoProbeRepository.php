<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Repository;

use Switon\Orm\AbstractRepository;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\Tests\Fixtures\Entity\ConventionRepoProbe;
use LogicException;

/**
 * Naming-convention probe for EntityMetadata::getRepository (Foo\Entity\Bar → Foo\Repository\BarRepository).
 * Instantiation is not required for metadata resolution tests; dependencies are unresolved stubs.
 */
class ConventionRepoProbeRepository extends AbstractRepository implements ConventionRepoProbeRepositoryInterface
{
    protected string $entityClass = ConventionRepoProbe::class;

    protected function getEntityManager(): EntityManagerInterface
    {
        throw new LogicException('ConventionRepoProbeRepository is only used for naming-convention resolution tests.');
    }

    protected function getQueryBuilder(): QueryBuilderInterface
    {
        throw new LogicException('ConventionRepoProbeRepository is only used for naming-convention resolution tests.');
    }
}
