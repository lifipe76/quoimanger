<?php

namespace App\Twig;

use App\Service\ComposantsServices;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\Environment;

class ComposantsExtension extends AbstractExtension
{
    public function __construct(
        private ComposantsServices $registry
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('composant', [$this, 'renderComponent'], ['needs_environment' => true, 'needs_context'     => true, 'is_safe' => ['html']]),
            new TwigFunction('addCss', [$this, 'addCss']),
            new TwigFunction('addJs', [$this, 'addJs']),
        ];
    }

    public function renderComponent(
        environment $twig,
        array $context,
        string $name,
        array $vars = []
    ): string {

        $dir = dirname($name);
        $last = basename($name);

        $this->registry->add("$dir/_$last");

        $mergedVars = array_merge($context, $vars);

        return $twig->render("$dir/_$last/$last.html.twig", $mergedVars);
    }

    public function addCss(string $path): void
    {
        $this->registry->addCss($path);
    }

    public function addJs(string $path): void
    {
        $this->registry->addJs($path);
    }
}
