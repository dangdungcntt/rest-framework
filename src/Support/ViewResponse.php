<?php

namespace Rest\Support;

class ViewResponse extends Response
{
    protected string $viewName;
    protected array $data;

    public function withView(string $viewName): static
    {
        $this->viewName = $viewName;
        return $this;
    }

    public function withData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @throws \ReflectionException
     * @throws \Rest\Exceptions\DICannotConstructException
     */
    public function render(): string
    {
        return app()->view->render($this->viewName, $this->data);
    }
}
