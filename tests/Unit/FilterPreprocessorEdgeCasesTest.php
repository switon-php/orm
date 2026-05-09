<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\FilterPreprocessor;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FilterPreprocessorEdgeCasesTest extends TestCase
{
    public function testPreprocessSkipsDateRangeWhenValueArrayNotLength2(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $filters = [
            'created_at@=' => ['2024-01-01'],
        ];

        $result = $fp->preprocess($filters, TestEntity::class);

        $this->assertSame($filters, $result, 'Should not rewrite malformed date range filter');
    }

    public function testPreprocessConvertsDateRangeWhenMinIsEmptyStringAndMaxProvided(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $filters = [
            'created_at@=' => ['', '2024-12-31'],
        ];

        $expectedMax = strtotime('2024-12-31 23:59:59');

        $result = $fp->preprocess($filters, TestEntity::class);

        $this->assertArrayNotHasKey('created_at@=', $result);
        $this->assertArrayHasKey('created_at<=', $result);
        $this->assertSame($expectedMax, $result['created_at<=']);
        $this->assertArrayNotHasKey('created_at>=', $result);
        $this->assertArrayNotHasKey('created_at~=', $result);
    }

    public function testPreprocessConvertsNumericStringsToFormattedDatesWhenDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $min = '1704067200';
        $max = '1704153600';

        $expectedMin = date('Y-m-d H:i:s', (int)$min);
        $expectedMax = date('Y-m-d H:i:s', (int)$max);

        $result = $fp->preprocess(
            ['created_at@=' => [$min, $max]],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [$expectedMin, $expectedMax]],
            $result
        );
    }

    public function testPreprocessRemovesDateRangeFilterWhenBothMinAndMaxEmptyStrings(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $filters = [
            'created_at@=' => ['', ''],
        ];

        $result = $fp->preprocess($filters, TestEntity::class);

        $this->assertSame([], $result);
    }

    public function testPreprocessSkipsDateRangeWhenValueArrayMissingNumericIndexes(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $filters = [
            'created_at@=' => [
                'min' => '2024-01-01',
                'max' => '2024-12-31',
            ],
        ];

        $result = $fp->preprocess($filters, TestEntity::class);

        $this->assertSame($filters, $result, 'Should not rewrite when array keys are not 0/1');
    }

    public function testPreprocessKeepsDateRangeStringValuesContainingColonWhenDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $min = '2024-01-01 12:34:56';
        $max = '2024-12-31 23:59:59';

        $result = $fp->preprocess(
            ['created_at@=' => [$min, $max]],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [$min, $max]],
            $result
        );
    }

    public function testPreprocessConvertsDateRangeWhenDateFormatIsEmptyAndMinMaxContainColon(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        // Falsy date format triggers strtotime-based conversion.
        $entityMetadata->method('getDateFormat')->willReturn('');

        $fp = new FilterPreprocessor($entityMetadata);

        $min = '2024-01-01 12:34:56'; // contains ":"
        $max = '2024-01-02 23:59:59'; // contains ":"

        $expectedMin = strtotime($min);
        $expectedMax = strtotime($max);

        $result = $fp->preprocess(
            ['created_at@=' => [$min, $max]],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [$expectedMin, $expectedMax]],
            $result
        );
    }

    public function testPreprocessConvertsNumericMaxWhenMinContainsColonAndDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $min = '2024-01-01 12:34:56'; // contains ":"
        $max = '1704153600'; // numeric seconds

        $expectedMax = date('Y-m-d H:i:s', (int)$max);

        $result = $fp->preprocess(
            ['created_at@=' => [$min, $max]],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [$min, $expectedMax]],
            $result
        );
    }

    public function testPreprocessConvertsNumericStringsToTimestampsWhenDateFormatIsU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $min = '1704067200';
        $max = '1704153600';

        $result = $fp->preprocess(
            ['created_at@=' => [$min, $max]],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [(int)$min, (int)$max]],
            $result
        );
    }

    public function testPreprocessConvertsNumericMinAndMaxZeroStringsToIntsWhenDateFormatIsU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $result = $fp->preprocess(
            ['created_at@=' => ['0', '1704153600']],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [0, 1704153600]],
            $result
        );
    }

    public function testPreprocessConvertsNumericZeroStringsToFormattedDatesWhenDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $min = '0';
        $max = '1704153600';
        $expectedMin = date('Y-m-d H:i:s', 0);
        $expectedMax = date('Y-m-d H:i:s', (int)$max);

        $result = $fp->preprocess(
            ['created_at@=' => [$min, $max]],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [$expectedMin, $expectedMax]],
            $result
        );
    }

    public function testPreprocessConvertsZeroIntMinAndNumericMaxIntsToFormattedDatesWhenDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $min = 0;
        $max = 1704153600;
        $expectedMin = date('Y-m-d H:i:s', 0);
        $expectedMax = date('Y-m-d H:i:s', $max);

        $result = $fp->preprocess(
            ['created_at@=' => [$min, $max]],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [$expectedMin, $expectedMax]],
            $result
        );
    }

    public function testPreprocessConvertsZeroIntMinToGteWhenMaxEmptyStringAndDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $expectedMin = date('Y-m-d H:i:s', 0);

        $result = $fp->preprocess(
            ['created_at@=' => [0, '']],
            TestEntity::class
        );

        $this->assertArrayNotHasKey('created_at@=', $result);
        $this->assertArrayHasKey('created_at>=', $result);
        $this->assertSame($expectedMin, $result['created_at>=']);
        $this->assertArrayNotHasKey('created_at<=', $result);
        $this->assertArrayNotHasKey('created_at~=', $result);
    }

    public function testPreprocessConvertsNumericMinZeroStringWhenMaxEmptyStringDateFormatIsU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $result = $fp->preprocess(
            ['created_at@=' => ['0', '']],
            TestEntity::class
        );

        $this->assertArrayNotHasKey('created_at@=', $result);
        $this->assertArrayHasKey('created_at>=', $result);
        $this->assertSame(0, $result['created_at>=']);
        $this->assertArrayNotHasKey('created_at<=', $result);
        $this->assertArrayNotHasKey('created_at~=', $result);
    }

    public function testPreprocessConvertsDateRangeWhenMinIsNullAndMaxProvided(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $filters = [
            'created_at@=' => [null, '2024-12-31'],
        ];

        $expectedMax = strtotime('2024-12-31 23:59:59');

        $result = $fp->preprocess($filters, TestEntity::class);

        $this->assertArrayNotHasKey('created_at@=', $result);
        $this->assertArrayHasKey('created_at<=', $result);
        $this->assertSame($expectedMax, $result['created_at<=']);
        $this->assertArrayNotHasKey('created_at>=', $result);
        $this->assertArrayNotHasKey('created_at~=', $result);
    }

    public function testPreprocessConvertsNumericMinAndKeepsColonMaxWhenDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $min = '1704067200'; // numeric seconds
        $max = '2024-12-31 23:59:59'; // contains ":"

        $expectedMin = date('Y-m-d H:i:s', (int)$min);

        $result = $fp->preprocess(
            ['created_at@=' => [$min, $max]],
            TestEntity::class
        );

        $this->assertSame(
            ['created_at~=' => [$expectedMin, $max]],
            $result
        );
    }

    public function testPreprocessConvertsDateRangeWhenMinProvidedAndMaxEmptyString(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('U');

        $fp = new FilterPreprocessor($entityMetadata);

        $filters = [
            'created_at@=' => ['2024-01-01', ''],
        ];

        $expectedMin = strtotime('2024-01-01 00:00:00');

        $result = $fp->preprocess($filters, TestEntity::class);

        $this->assertArrayNotHasKey('created_at@=', $result);
        $this->assertArrayHasKey('created_at>=', $result);
        $this->assertSame($expectedMin, $result['created_at>=']);
        $this->assertArrayNotHasKey('created_at<=', $result);
        $this->assertArrayNotHasKey('created_at~=', $result);
    }

    public function testPreprocessConvertsDateRangeMinOnlyWhenDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $filters = [
            'created_at@=' => ['2024-01-01', ''],
        ];

        $expectedMin = date('Y-m-d H:i:s', strtotime('2024-01-01 00:00:00'));

        $result = $fp->preprocess($filters, TestEntity::class);

        $this->assertArrayNotHasKey('created_at@=', $result);
        $this->assertArrayHasKey('created_at>=', $result);
        $this->assertSame($expectedMin, $result['created_at>=']);
        $this->assertArrayNotHasKey('created_at<=', $result);
        $this->assertArrayNotHasKey('created_at~=', $result);
    }

    public function testPreprocessConvertsDateRangeWhenMinIsNullAndMaxIsNumericSecondsDateFormatNotU(): void
    {
        $entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $entityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        $fp = new FilterPreprocessor($entityMetadata);

        $max = '1704153600';
        $expectedMax = date('Y-m-d H:i:s', (int)$max);

        $filters = [
            'created_at@=' => [null, $max],
        ];

        $result = $fp->preprocess($filters, TestEntity::class);

        $this->assertArrayNotHasKey('created_at@=', $result);
        $this->assertArrayHasKey('created_at<=', $result);
        $this->assertSame($expectedMax, $result['created_at<=']);
        $this->assertArrayNotHasKey('created_at>=', $result);
        $this->assertArrayNotHasKey('created_at~=', $result);
    }
}

