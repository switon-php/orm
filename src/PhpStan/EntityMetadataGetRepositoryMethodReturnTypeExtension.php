<?php

declare(strict_types=1);

namespace Switon\Orm\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Switon\Orm\EntityMetadataInterface;

final class EntityMetadataGetRepositoryMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    use ClassNameResolverTrait;

    public function getClass(): string
    {
        return EntityMetadataInterface::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getRepository';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $args = $methodCall->getArgs();
        if (!isset($args[0])) {
            return null;
        }

        $entityType = $scope->getType($args[0]->value);
        $entityClassNames = $entityType->getObjectTypeOrClassStringObjectType()->getObjectClassNames();
        if ($entityClassNames === []) {
            return null;
        }

        $repositoryClass = $this->resolveRepositoryClassName($entityClassNames[0]);
        if ($repositoryClass === null) {
            return null;
        }

        return $this->resolveExistingClassName($repositoryClass);
    }
}
