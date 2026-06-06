<?php

declare(strict_types=1);

namespace Switon\Orm\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use ReflectionClass;
use Switon\Orm\AbstractRepository;
use Switon\Orm\Entity;
use Switon\Orm\RepositoryInterface;
use Switon\Query\Query;

use function class_exists;
use function is_string;
use function preg_match;

/**
 * Resolves entity and query types for ORM PHPStan extensions.
 */
trait EntityClassTypeResolver
{
    use ClassNameResolverTrait;

    private function resolveQueryTypeFromEntityClassArgument(MethodCall $methodCall, Scope $scope, int $argumentIndex = 0): Type
    {
        $entityType = $this->resolveEntityTypeFromMethodArgument($methodCall, $scope, $argumentIndex);

        return $entityType !== null
            ? $this->createQueryTypeForEntity($entityType)
            : new ObjectType(Query::class);
    }

    private function resolveEntityTypeFromMethodArgument(MethodCall $methodCall, Scope $scope, int $argumentIndex = 0): ?ObjectType
    {
        $args = $methodCall->getArgs();
        if (!isset($args[$argumentIndex])) {
            return null;
        }

        $resolved = $this->resolveClassType($scope->getType($args[$argumentIndex]->value));

        return $resolved instanceof ObjectType ? $resolved : null;
    }

    private function createQueryTypeForEntity(ObjectType $entityType): Type
    {
        return new GenericObjectType(Query::class, ['Model' => $entityType]);
    }

    private function resolveEntityTypeFromReceiver(MethodCall $methodCall, Scope $scope): ?ObjectType
    {
        $receiverType = TypeCombinator::removeNull($scope->getType($methodCall->var));

        if ($receiverType instanceof GenericObjectType) {
            $templateType = $receiverType->getTemplateType(RepositoryInterface::class, 'T');
            $entityType = $this->narrowToEntityObjectType($templateType);
            if ($entityType !== null) {
                return $entityType;
            }
        }

        foreach ($receiverType->getObjectClassNames() as $className) {
            $entityType = $this->resolveEntityTypeFromRepositoryClassName($className);
            if ($entityType !== null) {
                return $entityType;
            }
        }

        return null;
    }

    private function narrowToEntityObjectType(Type $type): ?ObjectType
    {
        if (!$type instanceof ObjectType) {
            return null;
        }

        $classReflection = $type->getClassReflection();
        if ($classReflection === null) {
            return null;
        }

        if (!$classReflection->is(Entity::class)) {
            return null;
        }

        return $type;
    }

    private function resolveEntityTypeFromRepositoryClassName(string $className): ?ObjectType
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflection = new ReflectionClass($className);
        if (!$reflection->isSubclassOf(AbstractRepository::class)) {
            return null;
        }

        if ($reflection->hasProperty('entityClass')) {
            $property = $reflection->getProperty('entityClass');
            if ($property->hasDefaultValue()) {
                $default = $property->getDefaultValue();
                if (is_string($default)) {
                    $resolved = $this->resolveExistingClassName($default);

                    return $resolved instanceof ObjectType ? $resolved : null;
                }
            }
        }

        if (preg_match('#^(.*)\\\\Repository\\\\(.*)Repository$#', $className, $match) !== 1) {
            return null;
        }

        $resolved = $this->resolveExistingClassName($match[1] . '\\Entity\\' . $match[2]);

        return $resolved instanceof ObjectType ? $resolved : null;
    }
}
