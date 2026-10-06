# Changelog

## 0.2.0 - 2026-10-05

- Add display-list kinds 13–25: `RoundRect`, `StrokeRoundRect`, `Arc`, `Sector`,
  `Polyline`, `Polygon`, `Path`, `GradientRect`, `GradientPath`, `Alpha`, `Label`,
  `Shadow` and `DashedLine`. Kinds 1–12 are unchanged.
- Add the `Path` builder, the `Color` validator and the `LineCap`, `LineJoin`,
  `PathMode`, `TextAlign` and `FontWeight` enums.
- Validate colors on every drawing call (`#rgb`, `#rrggbb`, `#rrggbbaa`) and
  require `alpha`/`shadow` to be used between `save` and `restore`.
- Draw in density-independent pixels by default (`CanvasView::dp()`); pointer
  events arrive in the same units. `CanvasView::px()` keeps the 0.1.0 behaviour.
- Fix Android parsing of `#rrggbbaa` colors (alpha was read as the red channel).
- Reformat the PHP sources to PSR-12 and raise PHPStan to level max.

## 0.1.0 - 2026-08-23

- Initial public release: bounded JSON display list rendered by Android Canvas
  and Core Graphics, pointer events, sequential integer command/event kinds.
