<?php


namespace app\container;


use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;


class ComponentDependencyResolver {
    /** @var array<string, object> */
    private array $instances = [];

    public function __construct(
            private readonly AppContainer $container
    ) {
    }

    public function resolve(string $className): object {
        if (isset($this->instances[$className])) {
            return $this->instances[$className];
        }

        foreach ((new \ReflectionClass($this->container))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (!$returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
                continue;
            }

            if ($returnType->getName() !== $className) {
                continue;
            }

            $instance = $method->invoke($this->container);
            if (!is_object($instance)) {
                break;
            }

            return $this->instances[$className] = $instance;
        }

        throw new RuntimeException("Unable to resolve component dependency '{$className}'");
    }
}
