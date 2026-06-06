<?php

declare(strict_types=1);

namespace Switon\Orm\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Switon\Orm\RepositoryInterface;
use Switon\Query\Paginator;

/**
 * Infers repository method return types from the receiver's entity template {@code T}.
 */
final class AbstractRepositoryMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    use EntityClassTypeResolver;

    private const array ENTITY_METHODS = [
        'get' => true,
        'firstOrFail' => true,
        'fill' => true,
        'create' => true,
        'save' => true,
        'put' => true,
        'update' => true,
        'delete' => true,
        'updateById' => true,
    ];

    private const array NULLABLE_ENTITY_METHODS = [
        'find' => true,
        'first' => true,
        'deleteById' => true,
    ];

    private const array ARRAY_ENTITY_METHODS = [
        'all' => true,
        'createMany' => true,
    ];

    private const array ARRAY_KEYED_ENTITY_METHODS = [
        'allBy' => true,
    ];

    public function getClass(): string
    {
        return RepositoryInterface::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $name = $methodReflection->getName();

        return isset(self::ENTITY_METHODS[$name])
            || isset(self::NULLABLE_ENTITY_METHODS[$name])
            || isset(self::ARRAY_ENTITY_METHODS[$name])
            || isset(self::ARRAY_KEYED_ENTITY_METHODS[$name])
            || $name === 'paginate';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $entityType = $this->resolveEntityTypeFromReceiver($methodCall, $scope);
        if ($entityType === null) {
            return null;
        }

        return match ($methodReflection->getName()) {
            'paginate' => new ObjectType(Paginator::class),
            'all', 'createMany' => new ArrayType(new IntegerType(), $entityType),
            'allBy' => new ArrayType(new UnionType([new IntegerType(), new StringType()]), $entityType),
            'find', 'first', 'deleteById' => TypeCombinator::union($entityType, new NullType()),
            default => $entityType,
        };
    }
}
