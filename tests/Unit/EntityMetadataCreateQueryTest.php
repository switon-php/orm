<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Tests\Fixtures\Entity\CreateQueryProbeEntity;
use Switon\Orm\Tests\Fixtures\Repository\CreateQueryProbeRepository;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class EntityMetadataCreateQueryTest extends TestCase
{
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector->inject($this);
        CreateQueryProbeRepository::resetProbeState();
    }

    public function testCreateQueryInvokesRepositorySelectWithFields(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        CreateQueryProbeRepository::$probeReturn = $mockQuery;

        $result = $this->entityMetadata->createQuery(CreateQueryProbeEntity::class, ['id', 'name']);

        $this->assertSame($mockQuery, $result);
        $this->assertSame([['id', 'name']], CreateQueryProbeRepository::$selectRawFieldsHistory);
    }

    public function testCreateQueryInvokesSelectOnEachCallWithDistinctFields(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        CreateQueryProbeRepository::$probeReturn = $mockQuery;

        $this->assertSame($mockQuery, $this->entityMetadata->createQuery(CreateQueryProbeEntity::class, ['a']));
        $this->assertSame($mockQuery, $this->entityMetadata->createQuery(CreateQueryProbeEntity::class, ['b']));

        $this->assertSame([['a'], ['b']], CreateQueryProbeRepository::$selectRawFieldsHistory);
    }

    public function testCreateQueryPassesEmptyFieldsArrayToSelect(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        CreateQueryProbeRepository::$probeReturn = $mockQuery;

        $result = $this->entityMetadata->createQuery(CreateQueryProbeEntity::class, []);

        $this->assertSame($mockQuery, $result);
        $this->assertSame([[]], CreateQueryProbeRepository::$selectRawFieldsHistory);
    }
}
