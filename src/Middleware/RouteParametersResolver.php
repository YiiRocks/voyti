<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionParameter;
use Yiisoft\Middleware\Dispatcher\ParametersResolverInterface;
use Yiisoft\Router\CurrentRoute;

use function array_key_exists;

final readonly class RouteParametersResolver implements ParametersResolverInterface
{
    public function __construct(
        private CurrentRoute $currentRoute,
    ) {
    }

    /**
     * @psalm-return array<string, scalar>
     */
    #[\Override]
    public function resolve(array $parameters, ServerRequestInterface $request): array
    {
        $arguments = $this->currentRoute->getArguments();
        $resolved = [];

        /** @var ReflectionParameter $parameter */
        foreach ($parameters as $name => $parameter) {
            if (array_key_exists($name, $arguments)) {
                $value = $arguments[$name];

                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    continue;
                }

                if ($type instanceof \ReflectionNamedType) {
                    $resolved[$name] = match ($type->getName()) {
                        'int' => (int) $value,
                        'float' => (float) $value,
                        'bool' => (bool) $value,
                        'string' => $value,
                        default => $value,
                    };
                } else {
                    $resolved[$name] = $value;
                }
            }
        }

        /** @var array<string, scalar> $resolved */
        return $resolved;
    }
}
