# Command reference

Every `Canvas` method appends one command to an immutable display list. The list is sent to the
native view as JSON (`[{"k": <kind>, "a": [...]}]`) together with a `revision`; Android Canvas and
Core Graphics replay it on the UI thread without calling PHP per frame.

Units are dp when the view is created with `CanvasView::make($scene)->dp()` (the default) and raw
pixels with `->px()`. Angles are degrees, `0°` at 12 o'clock, clockwise positive. Colors are CSS hex
(`#rgb`, `#rrggbb`, `#rrggbbaa`); invalid colors throw `InvalidArgumentException` in PHP.

| Kind | Method | Arguments | Notes |
| ---: | --- | --- | --- |
| 1 | `save()` | — | Pushes the canvas state (transform, clip, alpha, shadow). |
| 2 | `restore()` | — | Pops the state. Scenes must balance `save`/`restore`. |
| 3 | `translate($x, $y)` | floats | |
| 4 | `rotate($degrees)` | float | Around the current origin. |
| 5 | `scale($x, $y)` | floats | |
| 6 | `clipRect($x, $y, $width, $height)` | floats | Intersects the clip with a rectangle. |
| 7 | `clear($color)` | color | Fills the whole view. Defaults to transparent. |
| 8 | `fillRect($x, $y, $w, $h, $color)` | | |
| 9 | `strokeRect($x, $y, $w, $h, $color, $lineWidth = 1)` | | |
| 10 | `circle($cx, $cy, $radius, $color)` | | Filled. |
| 11 | `line($x1, $y1, $x2, $y2, $color, $lineWidth = 1)` | | |
| 12 | `text($text, $x, $y, $size, $color)` | | `y` is the baseline. Left aligned, regular weight. |
| 13 | `roundRect($x, $y, $w, $h, $radius, $color)` | | Filled rounded rectangle. |
| 14 | `strokeRoundRect($x, $y, $w, $h, $radius, $color, $lineWidth = 1)` | | |
| 15 | `arc($cx, $cy, $radius, $startDeg, $sweepDeg, $color, $lineWidth = 1, LineCap $cap = Butt)` | | Stroked arc (progress rings). |
| 16 | `sector($cx, $cy, $outerRadius, $innerRadius, $startDeg, $sweepDeg, $color)` | | Filled ring segment; `innerRadius = 0` draws a pie slice. |
| 17 | `polyline(array $points, $color, $lineWidth = 1, LineCap $cap = Round, LineJoin $join = Round)` | `[[x, y], ...]` (2–512 points) | Open stroked polyline. |
| 18 | `polygon(array $points, $color)` | `[[x, y], ...]` | Closed filled polygon. |
| 19 | `path(Path $path, $color, $lineWidth = 1, PathMode $mode = Fill)` | | `Path` builder with `M L C Q Z`, absolute coordinates, ≤ 4096 chars. |
| 20 | `gradientRect($x, $y, $w, $h, $radius, $x0, $y0, $x1, $y1, $colorStart, $colorEnd)` | | Linear gradient fill; gradient points are absolute. |
| 21 | `gradientPath(Path $path, $x0, $y0, $x1, $y1, $colorStart, $colorEnd)` | | Linear-gradient-filled path (area charts). |
| 22 | `alpha($opacity)` | 0..1 | Applies to the following commands until `restore()`. Must be inside `save()`. |
| 23 | `label($text, $x, $y, $size, $color, TextAlign $align = Left, FontWeight $weight = Regular)` | | Aligned, weighted text; `y` is the baseline. |
| 24 | `shadow($color, $blur, $dx, $dy)` | | Shadow for the following fills until `restore()`. Must be inside `save()`. |
| 25 | `dashedLine($x1, $y1, $x2, $y2, $color, $lineWidth, $dash, $gap)` | | Grid lines and baselines. |

## Enums

| Enum | Cases |
| --- | --- |
| `LineCap` | `Butt = 1`, `Round = 2` |
| `LineJoin` | `Miter = 1`, `Round = 2`, `Bevel = 3` |
| `PathMode` | `Fill = 1`, `Stroke = 2` (round caps and joins) |
| `TextAlign` | `Left = 1`, `Center = 2`, `Right = 3` |
| `FontWeight` | `Regular = 1`, `Medium = 2`, `SemiBold = 3`, `Bold = 4` |
| `CanvasEventKind` | `PointerDown = 1`, `PointerMove = 2`, `PointerUp = 3`, `PointerCancel = 4` |

## `Path`

```php
use Pam\Native\Canvas\Path;

$path = Path::start(0, 40)          // M
    ->lineTo(40, 10)                // L
    ->quadTo(60, 0, 80, 10)         // Q (control, end)
    ->curveTo(100, 20, 120, 50, 160, 40) // C (control 1, control 2, end)
    ->close();                      // Z

$path->spec();          // "M0 40 L40 10 Q60 0 80 10 C100 20 120 50 160 40 Z"
Path::fromSpec('M0 0 L10 10');      // validated parser for stored specs
```

Paths are immutable: every call returns a new `Path`, so a line path can be extended into its area
path without touching the original (see the README example).

## Limits

- 10,000 commands per scene, 16 arguments per command, 4,096 characters per string.
- Numbers must be finite; `save`/`restore` must balance; `alpha`/`shadow` require an open `save`.
- Scenes are validated when built (`$canvas->scene()`), never on the device.

## Pointer events

```php
CanvasView::make($scene, revision: $this->tick)
    ->dp()
    ->onPointer(function (CanvasEventKind $kind, float $x, float $y): void {
        if ($kind === CanvasEventKind::PointerDown || $kind === CanvasEventKind::PointerMove) {
            $this->highlight = $this->nearestIndex($x);
            $this->tick++;
        }
    });
```

Coordinates arrive in the same units as the scene. Re-render with a new `revision` to redraw.

## Platform parity

Android (`android/src/main/kotlin/dev/pam/canvas/CanvasViewFactory.kt`) and iOS
(`ios/Sources/CanvasViewFactory.swift`) implement every kind with the same argument order and
angle convention. The PHP test suite snapshots the JSON of a representative scene so the wire
format cannot drift silently.
