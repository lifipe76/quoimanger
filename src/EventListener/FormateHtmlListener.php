<?php

namespace App\EventListener;

use App\Service\ComposantsServices;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: 10)]
class FormateHtmlListener
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly ComposantsServices $registry,
    ) {}

    public function __invoke(ResponseEvent $event): void
    {

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Header noindex sur les routes API / APP
        if (
            str_starts_with($request->getPathInfo(), '/api') ||
            str_starts_with($request->getPathInfo(), '/app')
        ) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Ignorer les fichiers en stream ou en téléchargement direct
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return;
        }

        if ($response->isRedirection()) {
            return;
        }

        $content = $response->getContent();

        if (!$content || !str_contains($content, '</head>')) {
            return;
        }

        $css = '';
        $js = '';

        // 1. Traitement des fichiers CSS de l'assets registry
        foreach (array_unique($this->registry->getCss()) as $cssFile) {
            $cssPath = $this->projectDir . "/assets/" . $cssFile;

            if (file_exists($cssPath)) {
                $css .= file_get_contents($cssPath);
            }
        }

        // 2. Traitement des fichiers JS de l'assets registry
        foreach (array_unique($this->registry->getJs()) as $jsFile) {
            $jsPath = $this->projectDir . "/assets/" . $jsFile;

            if (file_exists($jsPath)) {
                $js .= file_get_contents($jsPath);
            }
        }

        // 3. Traitement des composants Twig spécifiques
        foreach (array_unique($this->registry->all()) as $component) {
            $last = substr(basename($component), 1);

            $cssPath = $this->projectDir . "/templates/$component/$last.css";
            $jsPath = $this->projectDir . "/templates/$component/$last.js";

            if (file_exists($cssPath)) {
                $css .= file_get_contents($cssPath);
            }

            if (file_exists($jsPath)) {
                $js .= file_get_contents($jsPath);
            }
        }

        // 4. Injection dans le code HTML
        if ($css !== '') {
            $content = str_replace('</head>', "<style>$css</style></head>", $content);
        }

        if ($js !== '') {
            $content = str_replace('</body>', "<script>$js</script></body>", $content);
        }

        // Si tu souhaites réactiver la minification HTML :
        // $response->setContent($this->minifyHtml($content));

        $response->setContent($content);
    }

    private function minifyHtml(string $html): string
    {
        // 🔹 1. Sauvegarder les blocs à ignorer
        $preserved = [];
        $html = preg_replace_callback(
            '/<!--\s*MINIFY-IGNORE-START\s*-->(.*?)<!--\s*MINIFY-IGNORE-END\s*-->/isU',
            function ($m) use (&$preserved) {
                $key = '###MINIFY_IGNORE_' . count($preserved) . '###';
                $preserved[$key] = $m[1];
                return $key;
            },
            $html
        );

        // 🔹 2. Minifier les <style> (inline CSS)
        $html = preg_replace_callback(
            '/<style\b[^>]*>(.*?)<\/style>/is',
            function ($m) {
                $css = $this->minifyCss($m[1]);
                return "<style>{$css}</style>";
            },
            $html
        );

        // 🔹 3. Minifier les <script> inline
        $html = preg_replace_callback(
            '/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/is',
            function ($m) {
                if (preg_match('/type=["\']?(module|importmap|application\/json|modulemap)["\']?/i', $m[0])) {
                    return $m[0];
                }

                $js = $this->minifyJs($m[1]);
                return "<script>{$js}</script>";
            },
            $html
        );

        // 🔹 4. Nettoyage des espaces et retours à la ligne
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace("/\n+/", " ", $html);
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s{2,}/', ' ', $html);
        $html = trim($html);

        // 🔹 5. Restaurer les blocs préservés
        if ($preserved) {
            $html = str_replace(array_keys($preserved), array_values($preserved), $html);
        }

        return $html;
    }

    private function minifyCss(string $css): string
    {
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $css = preg_replace('/\s*([{};:,])\s*/', '$1', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/;}/', '}', $css);

        return trim($css);
    }

    private function minifyJs(string $js): string
    {
        $js = preg_replace('!/\\*.*?\\*/!s', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);

        return trim($js);
    }
}
