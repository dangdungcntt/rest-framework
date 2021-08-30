<?php

namespace Rest\DI;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Rest\Contracts\DIContainer;
use Rest\Contracts\Singleton;
use Rest\Exceptions\DICannotConstructException;

class Container implements DIContainer
{
    protected static Container $instance;

    protected array $resolved = [];

    protected array $bind = [];

    protected array $singleton = [];

    public static function getInstance(): static
    {
        return self::$instance ??= new static();
    }

    /**
     * @param  mixed  $bindingName
     * @param  string|null  $contextClassName
     * @return mixed
     * @throws DICannotConstructException
     * @throws ReflectionException
     */
    public function resolve(mixed $bindingName, ?string $contextClassName = null): mixed
    {
        if (is_string($bindingName)) {
            if (is_string($contextClassName)) {
                $key = "$bindingName-$contextClassName";
                if (isset($this->resolved[$key])) {
                    return $this->resolved[$key];
                }

                if (isset($this->bind[$key])) {
                    return $this->saveSingletonAndReturn($key, $this->resolve($this->bind[$key], $contextClassName));
                }
            }

            if (isset($this->resolved[$bindingName])) {
                return $this->resolved[$bindingName];
            }

            if (isset($this->bind[$bindingName])) {
                return $this->saveSingletonAndReturn($bindingName, $this->resolve($this->bind[$bindingName], $contextClassName));
            }
        }

        return $this->saveSingletonAndReturn($bindingName, $this->constructInstance($bindingName));
    }

    protected function saveSingletonAndReturn(mixed $bindingName, mixed $object): mixed
    {
        $isSingleton = is_string($bindingName) && (
                isset($this->singleton[$bindingName])
                || (!isset($this->bind[$bindingName]) && $object instanceof Singleton)
            );
        if ($isSingleton && !isset($this->resolved[$bindingName])) {
            $this->resolved[$bindingName] = $object;
        }
        return $object;
    }

    /**
     * @param  mixed  $className
     * @return mixed
     * @throws \ReflectionException
     * @throws \Rest\Exceptions\DICannotConstructException
     */
    protected function constructInstance(mixed $className): mixed
    {
        if ($className instanceof Closure) {
            return $className($this);
        }

        if (!is_string($className)) {
            return $className;
        }

        if (interface_exists($className)) {
            throw new DICannotConstructException(
                sprintf('Cannot construct interface %s without bind. Please bind this interface in Container',
                    $className)
            );
        }

        if (!class_exists($className)) {
            throw new LogicException(sprintf('Class %s do not exists ', $className));
        }

        $reflection  = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (is_null($constructor) || $constructor->getNumberOfParameters() == 0) {
            return new $className();
        }

        $resolvedParams = $this->buildParams($className, $constructor->getParameters());

        return new $className(...$resolvedParams);
    }

    /**
     * @param  string  $className
     * @param  ReflectionParameter[]  $params
     * @return array
     * @throws \ReflectionException
     * @throws \Rest\Exceptions\DICannotConstructException
     */
    protected function buildParams(string $className, array $params): array
    {
        $resolvedParams = [];
        foreach ($params as $param) {
            $type = $param->getType();

            if (is_null($type) || $type instanceof \ReflectionUnionType || ($type instanceof \ReflectionNamedType && $type->isBuiltin())) {
                if ($param->isDefaultValueAvailable()) {
                    $resolvedParams[] = $param->getDefaultValue();
                    continue;
                }
                throw new DICannotConstructException(
                    "Cannot construct $className because \${$param->getName()} is not initializable"
                );
            }
            try {
                $resolvedParams[] = $this->resolve($type->getName(), $className);
            } catch (DICannotConstructException $exception) {
                if ($param->isDefaultValueAvailable()) {
                    $resolvedParams[] = $param->getDefaultValue();
                    continue;
                }
                throw $exception;
            }
        }
        return $resolvedParams;
    }

    public function bind(string $bindingName, $value, ?string $parentName = null): static
    {
        if (is_string($parentName)) {
            $bindingName = "$bindingName-$parentName";
        }
        $this->bind[$bindingName] = $value;
        return $this;
    }

    public function singleton(string $bindingName, $value, ?string $parentName = null): static
    {
        if (is_string($parentName)) {
            $bindingName = "$bindingName-$parentName";
        }

        $this->singleton[$bindingName] = true;

        $this->bind[$bindingName] = $value;

        return $this;
    }
}
