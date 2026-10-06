<?php

declare(strict_types=1);

namespace Pam\Native\Canvas;

use Pam\Native\Plugin\PluginProvider;

/**
 * The canvas plugin has no PHP-side services; the native view is registered
 * from the plugin manifest.
 */
final class CanvasPluginProvider implements PluginProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
