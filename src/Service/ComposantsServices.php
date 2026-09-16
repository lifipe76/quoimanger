<?php

namespace App\Service;

class ComposantsServices
{
    private array $components = [];
    private array $css = [];
    private array $js = [];

    public function add(string $name): void
    {
        $this->components[$name] = true;
    }

    public function addCss(string $path): void
    {
        if (!in_array($path, $this->css)) {
            $this->css[] = $path;
        }
    }

    public function addJs(string $path): void
    {
        if (!in_array($path, $this->js)) {
            $this->js[] = $path;
        }
    }

    public function all(): array
    {
        return array_keys($this->components);
    }

    public function getCss(): array
    {
        return $this->css;
    }

    public function getJs(): array
    {
        return $this->js;
    }

    public function reset(): void
    {
        $this->components = [];
    }
}
