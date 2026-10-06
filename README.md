<!-- pam:product-page:start -->
<div align="center">

# PAM Native Canvas

**Retained-mode 2D graphics built for native frame budgets.**

Compose drawing surfaces, paths, paints, and interactions without shipping a WebView or replaying an entire PHP scene every frame.

[![Latest version](https://img.shields.io/packagist/v/pushinbr/pam-native-canvas?style=flat-square&label=stable)](https://packagist.org/packages/pushinbr/pam-native-canvas)
[![CI](https://img.shields.io/github/actions/workflow/status/push-in/pam-native-canvas/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/push-in/pam-native-canvas/actions)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?style=flat-square&logo=php&logoColor=white)
![Android](https://img.shields.io/badge/Android-API%2026%2B-3DDC84?style=flat-square&logo=android&logoColor=white)
![iOS](https://img.shields.io/badge/iOS-15%2B-000000?style=flat-square&logo=apple&logoColor=white)

**[Documentation](https://push-in.github.io/pam-docs/native/overview/) · [Quick start](#quick-start) · [What you can build](#what-you-can-build) · [PAM ecosystem](https://push-in.github.io/pam-docs/ecosystem/) · [Issues](https://github.com/push-in/pam-native-canvas/issues)**

</div>

---

## Why PAM Native Canvas

Compose drawing surfaces, paths, paints, and interactions without shipping a WebView or replaying an entire PHP scene every frame. The public API is strictly typed for PHP 8.5; expensive or frame-sensitive work stays in Rust or the platform SDK instead of crossing the application boundary every frame.

| | |
| --- | --- |
| **Best for** | A focused capability you can add to any PAM Native application |
| **Native path** | Android Canvas · Core Graphics |
| **Application model** | Composer package + generated native integration |
| **Design rule** | Independent module; no feed, vertical, or application template bundled |

## What you can build

- Charts, signatures, and annotation tools
- Custom controls and data visualization
- Lightweight games and interactive diagrams

## Quick start

Already have a PAM Native project? Add only this capability:

```bash
pam composer require pushinbr/pam-native-canvas
pam doctor --fix
```

New to PAM? Follow the **[five-minute PAM Native setup](https://push-in.github.io/pam-docs/native/overview/)** once, then return here. Your application stays a normal Composer project with a committed lockfile.
<!-- pam:product-page:end -->

## See it in action

This is a horizontal retained-mode 2D drawing primitive, not a UI framework, feed, application
template, GPU shader engine, or 3D engine. PHP builds a bounded display list; Android Canvas and
Core Graphics render it without calling PHP for each frame.

A line chart with a gradient area, a 2dp curve, a dashed baseline and aligned labels:

```php
use Pam\Native\Canvas\Canvas;
use Pam\Native\Canvas\CanvasView;
use Pam\Native\Canvas\FontWeight;
use Pam\Native\Canvas\Path;
use Pam\Native\Canvas\PathMode;
use Pam\Native\Canvas\TextAlign;

$line = Path::start(0, 120)
    ->curveTo(40, 120, 60, 40, 100, 40)
    ->curveTo(140, 40, 160, 90, 200, 90)
    ->curveTo(240, 90, 260, 20, 300, 20);

$area = $line->lineTo(300, 160)->lineTo(0, 160)->close();

$scene = (new Canvas())
    ->dashedLine(0, 160, 300, 160, '#2c3947', 1, 4, 4)
    ->save()
        ->alpha(0.35)
        ->gradientPath($area, 0, 20, 0, 160, '#19c5ff', '#19c5ff00')
    ->restore()
    ->path($line, '#19c5ff', 2, PathMode::Stroke)
    ->circle(300, 20, 4, '#19c5ff')
    ->label('R$ 1.234', 300, 10, 12, '#f4f7fa', TextAlign::Right, FontWeight::SemiBold)
    ->label('Seg', 0, 178, 11, '#a0adbb')
    ->label('Sex', 300, 178, 11, '#a0adbb', TextAlign::Right)
    ->scene();

return CanvasView::make($scene, revision: 1)->dp();
```

### Commands

| Method | Draws |
| --- | --- |
| `save()` / `restore()` / `translate()` / `rotate()` / `scale()` / `clipRect()` | Canvas state |
| `alpha($opacity)` / `shadow($color, $blur, $dx, $dy)` | Opacity and shadow for the following commands, until `restore()` |
| `clear()` / `fillRect()` / `roundRect()` / `circle()` / `sector()` / `polygon()` | Fills |
| `gradientRect()` / `gradientPath()` | Linear-gradient fills (points are absolute) |
| `strokeRect()` / `strokeRoundRect()` / `line()` / `dashedLine()` / `arc()` / `polyline()` | Strokes |
| `path(Path $path, $color, $lineWidth, PathMode $mode)` | Fill or stroke a `Path` (`M L C Q Z`, absolute coordinates) |
| `text()` / `label($text, $x, $y, $size, $color, TextAlign, FontWeight)` | Text; `y` is the baseline |

Angles are degrees, 0° at 12 o'clock, clockwise positive. Colors are CSS hex: `#rgb`, `#rrggbb`
or `#rrggbbaa`; anything else throws `InvalidArgumentException`.

### Units and sizing

`CanvasView::make($scene)->dp()` (the default) draws in density-independent pixels: Android scales
the canvas by the display density and iOS already draws in points. `->px()` draws in raw device
pixels. Pointer events (`onPointer`) arrive in the units the scene uses.

The view takes its size from layout like any element (`class`/`style` width and height); give it
an explicit width and height and draw inside that box.

```php
CanvasView::make($scene, revision: $tick)
    ->dp()
    ->onPointer(fn (CanvasEventKind $kind, float $x, float $y) => $this->select($x));
```

Bump `revision` whenever the scene changes so the native view redraws.

Scenes are immutable, capped at 10,000 commands, and use integer-backed command/event kinds.
Platform support: Android API 26+, iOS 15+, PHP 8.5+, PAM Native 0.8–1.x.

### Reference and examples

- [Command reference](docs/commands.md): every kind, its arguments, the enums, `Path`, limits and
  pointer events.
- [`examples/ProgressRing.php`](examples/ProgressRing.php): arcs with round caps and a centered label.
- Ready-made charts (line, area, bar, donut, sparkline, progress ring) live in
  [`pushinbr/pam-native-charts`](https://github.com/push-in/pam-native-charts), which builds on this
  plugin.

### Contributing

```bash
composer install
php tests/run.php            # PHP contracts (wire snapshots, validation, enums)
vendor/bin/phpstan analyse   # level max
vendor/bin/pam-native-plugin validate pam-native.plugin.json
```

Keep Android and iOS renderers in parity: a new command kind is appended to `CanvasCommandKind`,
`pam-native.idl.json`, both renderers and `docs/commands.md`, with a snapshot test in `tests/run.php`.

- [PAM introduction](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Report an issue](https://github.com/push-in/pam-native-canvas/issues)
