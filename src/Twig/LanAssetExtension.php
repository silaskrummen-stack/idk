<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * lan_asset('display.css') → /assets/lan-night/display.css?v=<Änderungszeit>
 * So holt sich der Fernseher nach jeder Änderung die neue Datei statt einer alten aus dem Cache.
 */
class LanAssetExtension extends AbstractExtension
{
    public function __construct(#[Autowire('%kernel.project_dir%/public/assets/lan-night')] private readonly string $dir)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('lan_asset', $this->url(...))];
    }

    public function url(string $file): string
    {
        $path = $this->dir . '/' . $file;

        return '/assets/lan-night/' . $file . (is_file($path) ? '?v=' . filemtime($path) : '');
    }
}
