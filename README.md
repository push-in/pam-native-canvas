# PAM Native Canvas

## Start here

```bash
curl --proto '=https' --proto-redir '=https' --tlsv1.2 \
    --connect-timeout 15 --max-time 60 --max-filesize 1048576 -fsSL \
    https://github.com/push-in/pam/releases/latest/download/install.sh | sh
pam init my-app --template native
cd my-app
pam composer require pushinbr/pam-native-canvas
pam doctor --fix
```

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
