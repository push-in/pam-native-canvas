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

```php
use Pam\Native\Canvas\Canvas;
use Pam\Native\Canvas\CanvasView;

$scene = (new Canvas())
    ->clear('#10131aff')
    ->fillRect(24, 24, 240, 120, '#7557ffff')
    ->circle(144, 84, 32, '#ffffffff')
    ->text('PAM', 96, 164, 28, '#ffffffff')
    ->scene();

return CanvasView::make($scene);
```

Scenes are immutable, capped at 10,000 commands, and use integer-backed command/event kinds.
Platform support: Android API 26+, iOS 15+, PHP 8.5+, PAM Native 0.8.x.

- [PAM introduction](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Report an issue](https://github.com/push-in/pam-native-canvas/issues)
