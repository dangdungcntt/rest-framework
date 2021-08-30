<?php


namespace Rest\Contracts;


interface DIContainer
{
    public function resolve(string $bindingName, ?string $contextClassName = null);

    public function bind(string $bindingName, $value, ?string $parentName = null);

    public function singleton(string $bindingName, $value, ?string $parentName = null);
}
