<?php

declare(strict_types=1);

namespace App\Support;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TemplateEngine
{
    public static function renderString(string $template, array $context = []): string
    {
        $twig = new Environment(new ArrayLoader(['inline' => $template]), [
            'autoescape' => false,
        ]);

        return $twig->render('inline', $context);
    }
}
