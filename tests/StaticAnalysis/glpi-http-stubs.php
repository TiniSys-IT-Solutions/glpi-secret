<?php

declare(strict_types=1);

namespace Glpi\Controller {
    abstract class AbstractController
    {
        protected function render(string $view, array $parameters = [], \Symfony\Component\HttpFoundation\Response $response = new \Symfony\Component\HttpFoundation\Response()): \Symfony\Component\HttpFoundation\Response {}
    }
}

namespace Glpi\Exception\Http {
    class AccessDeniedHttpException extends \RuntimeException {}
    class BadRequestHttpException extends \RuntimeException {}
}

namespace Glpi\Http {
    class RedirectResponse extends \Symfony\Component\HttpFoundation\RedirectResponse {}
}

namespace Glpi\Application\View {
    class TemplateRenderer
    {
        public static function getInstance(): self {}
        public function display(string $template, array $parameters = []): void {}
    }
}
