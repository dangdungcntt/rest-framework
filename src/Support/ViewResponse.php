<?php

namespace Rest\Support;

class ViewResponse extends Response
{
    protected string $viewName;
    protected array $data;

    public function withView(string $viewName): self
    {
        $this->viewName = $viewName;
        return $this;
    }

    public function withData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function render(): string
    {
        return app()->view->render($this->viewName, $this->data);
    }
}
