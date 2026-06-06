<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Orm\EntityManager;
use Switon\Orm\Event\EntityUnchanged;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;

class EntityManagerUpdateNullTest extends TestCase
{
    /**
     * Test that EntityManager::update() treats null values as "not provided".
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testUpdateRestoresOriginalValueWhenUpdateFieldIsNull(): void
    {
        $metadata = $this->createMock(\Switon\Orm\EntityMetadataInterface::class);
        $metadata->method('getPrimaryKey')->willReturn('id');
        $metadata->method('getFields')->willReturn(['id', 'name', 'status']);
        $metadata->method('getConstraints')->willReturn([]);
        $metadata->method('getColumnMap')->willReturn([]);

        $db = $this->createMock(\Switon\Db\ClientInterface::class);
        $lookup = $this->createMock(\Switon\Di\NamedLookupInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $entityUnchangedDispatched = false;

        $lookup->expects($this->never())->method('by');
        $db->expects($this->never())->method('update');
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$entityUnchangedDispatched): object {
                if ($event instanceof EntityUnchanged) {
                    $entityUnchangedDispatched = true;
                }
                return $event;
            });

        $this->container->set(\Switon\Orm\EntityMetadataInterface::class, $metadata);
        $this->container->set(\Switon\Di\NamedLookupInterface::class, $lookup);
        $this->container->set(EventDispatcherInterface::class, $eventDispatcher);

        $em = $this->container->make(EntityManager::class);

        $original = new TestEntity(['id' => 1, 'name' => 'Test', 'status' => 1]);
        $update = new TestEntity(['id' => 1, 'name' => 'Test', 'status' => null]);

        $em->update($update, $original);

        $this->assertSame(1, $update->status);
        $this->assertTrue($entityUnchangedDispatched, 'EntityUnchanged event should be dispatched');
    }
}
