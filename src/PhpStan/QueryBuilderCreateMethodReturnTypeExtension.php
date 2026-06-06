<?php

declare(strict_types=1);

namespace Switon\Orm\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Switon\Orm\QueryBuilderInterface;

final class QueryBuilderCreateMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    use EntityClassTypeResolver;

    public function getClass(): string
    {
        return QueryBuilderInterface::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'create';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        return $this->resolveQueryTypeFromEntityClassArgument($methodCall, $scope);
    }
}
