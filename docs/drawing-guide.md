# Drawing commands

Moved out of the README; nothing was removed.

See also the full [command reference](commands.md).

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
