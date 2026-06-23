<?php

declare(strict_types=1);

namespace App\Mail;

class MailTemplate
{
    /**
     * Render a mail template file to an HTML string.
     *
     * @param string $template  Template name (without .php) from App/Mail/Templates/
     * @param array  $vars      Variables to extract into the template scope
     * @return string           Rendered HTML
     */
    public static function render(string $template, array $vars = []): string
    {
        $file = __DIR__ . '/Templates/' . basename($template) . '.php';

        if (!file_exists($file)) {
            return '';
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
