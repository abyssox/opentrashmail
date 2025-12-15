<?php
declare(strict_types=1);

namespace OpenTrashmail\Controllers;

abstract class AbstractController
{
    /** @var array<string,mixed> */
    protected array $settings;

    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    protected function error(string $text): string
    {
        return '<h1>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</h1>';
    }

    final public function render(string $templateName, array $variables = []): string
    {
        return $this->renderTemplate($templateName, $variables);
    }

    /**
     * @param array<string,mixed> $variables
     */
    protected function renderTemplate(string $templateName, array $variables = []): string
    {
        ob_start();

        if (!empty($variables)) {
            extract($variables, EXTR_SKIP);
        }

        $templateBase = TEMPLATES_PATH;

        if (file_exists($templateBase . $templateName . '.php')) {
            include $templateBase . $templateName . '.php';
        } elseif (file_exists($templateBase . $templateName)) {
            include $templateBase . $templateName;
        }

        return (string)ob_get_clean();
    }
}
