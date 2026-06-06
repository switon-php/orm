<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Repository;

use Switon\Orm\AbstractRepository;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\Tests\Fixtures\Entity\CreateQueryProbeEntity;
use Switon\Query\QueryInterface;
use LogicException;

/**
 * Probe repository for {@see \Switon\Orm\EntityMetadata::createQuery()}: overrides {@see selectRaw()} only.
 */
class CreateQueryProbeRepository extends AbstractRepository
{
    protected string $entityClass = CreateQueryProbeEntity::class;

    /** @var list<array<int|string, mixed>> */
    public static array $selectRawFieldsHistory = [];

    public static ?QueryInterface $probeReturn = null;

    public static function resetProbeState(): void
    {
        self::$selectRawFieldsHistory = [];
        self::$probeReturn = null;
    }

    protected function selectRaw(array $fields = []): QueryInterface
    {
        self::$selectRawFieldsHistory[] = $fields;

        if (self::$probeReturn === null) {
            throw new LogicException('Set CreateQueryProbeRepository::$probeReturn before createQuery().');
        }

        return self::$probeReturn;
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        throw new LogicException('CreateQueryProbeRepository is only used for createQuery probe.');
    }

    protected function getQueryBuilder(): QueryBuilderInterface
    {
        throw new LogicException('CreateQueryProbeRepository is only used for createQuery probe.');
    }
}
