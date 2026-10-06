<?php

declare(strict_types=1);

namespace Pam\Native\Canvas;

use InvalidArgumentException;
use Pam\Native\Canvas\Internal\Numbers;

/**
 * Fluent, immutable builder of a display list. Every call returns a new
 * canvas; call {@see scene()} to obtain the validated {@see CanvasScene}.
 *
 * Angles are degrees, 0° at 12 o'clock, clockwise positive. Colors are CSS
 * hex (`#rgb`, `#rrggbb`, `#rrggbbaa`). Text `y` coordinates are baselines.
 */
final class Canvas
{
    public const int MAX_POINTS = 512;

    /** @var list<CanvasCommand> */
    private array $commands = [];

    // -- state ---------------------------------------------------------------

    public function save(): self
    {
        return $this->add(CanvasCommandKind::Save);
    }

    public function restore(): self
    {
        return $this->add(CanvasCommandKind::Restore);
    }

    public function translate(float $x, float $y): self
    {
        return $this->add(CanvasCommandKind::Translate, $x, $y);
    }

    public function rotate(float $degrees): self
    {
        return $this->add(CanvasCommandKind::Rotate, $degrees);
    }

    public function scale(float $x, float $y): self
    {
        return $this->add(CanvasCommandKind::Scale, $x, $y);
    }

    public function clipRect(float $x, float $y, float $width, float $height): self
    {
        return $this->add(CanvasCommandKind::ClipRect, $x, $y, $width, $height);
    }

    /**
     * Multiplies the opacity of every following command until the matching
     * restore. Must be used between {@see save()} and {@see restore()}.
     *
     * @param float $opacity 0 (transparent) to 1 (opaque)
     */
    public function alpha(float $opacity): self
    {
        if ($opacity < 0 || $opacity > 1) {
            throw new InvalidArgumentException('Canvas alpha must be between 0 and 1.');
        }

        return $this->add(CanvasCommandKind::Alpha, $opacity);
    }

    /**
     * Draws a shadow under every following fill until the matching restore.
     * Must be used between {@see save()} and {@see restore()}.
     */
    public function shadow(string $color, float $blur, float $dx = 0, float $dy = 0): self
    {
        return $this->add(CanvasCommandKind::Shadow, Color::assert($color), max(0, $blur), $dx, $dy);
    }

    // -- fills ----------------------------------------------------------------

    public function clear(string $color = '#00000000'): self
    {
        return $this->add(CanvasCommandKind::Clear, Color::assert($color));
    }

    public function fillRect(float $x, float $y, float $width, float $height, string $color): self
    {
        return $this->add(CanvasCommandKind::FillRect, $x, $y, $width, $height, Color::assert($color));
    }

    public function roundRect(float $x, float $y, float $width, float $height, float $radius, string $color): self
    {
        return $this->add(
            CanvasCommandKind::RoundRect,
            $x,
            $y,
            $width,
            $height,
            max(0, $radius),
            Color::assert($color),
        );
    }

    public function circle(float $x, float $y, float $radius, string $color): self
    {
        return $this->add(CanvasCommandKind::Circle, $x, $y, $radius, Color::assert($color));
    }

    /**
     * Filled ring segment (donut slice). `$innerRadius` 0 draws a pie slice.
     */
    public function sector(
        float $cx,
        float $cy,
        float $outerRadius,
        float $innerRadius,
        float $startDegrees,
        float $sweepDegrees,
        string $color,
    ): self {
        return $this->add(
            CanvasCommandKind::Sector,
            $cx,
            $cy,
            $outerRadius,
            max(0, $innerRadius),
            $startDegrees,
            $sweepDegrees,
            Color::assert($color),
        );
    }

    /**
     * Closed, filled polygon.
     *
     * @param list<array{0: float, 1: float}> $points at least three
     */
    public function polygon(array $points, string $color): self
    {
        return $this->add(CanvasCommandKind::Polygon, self::encodePoints($points, 3), Color::assert($color));
    }

    /**
     * Fills or strokes a {@see Path}.
     */
    public function path(Path $path, string $color, float $lineWidth = 1, PathMode $mode = PathMode::Fill): self
    {
        return $this->add(CanvasCommandKind::Path, $path->spec(), Color::assert($color), $lineWidth, $mode->value);
    }

    /**
     * Rounded rectangle filled with a linear gradient. Gradient points are
     * absolute, in the same units as the drawing.
     */
    public function gradientRect(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius,
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        string $colorStart,
        string $colorEnd,
    ): self {
        return $this->add(
            CanvasCommandKind::GradientRect,
            $x,
            $y,
            $width,
            $height,
            max(0, $radius),
            $x0,
            $y0,
            $x1,
            $y1,
            Color::assert($colorStart),
            Color::assert($colorEnd),
        );
    }

    /**
     * Path filled with a linear gradient (area charts).
     */
    public function gradientPath(
        Path $path,
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        string $colorStart,
        string $colorEnd,
    ): self {
        return $this->add(
            CanvasCommandKind::GradientPath,
            $path->spec(),
            $x0,
            $y0,
            $x1,
            $y1,
            Color::assert($colorStart),
            Color::assert($colorEnd),
        );
    }

    // -- strokes --------------------------------------------------------------

    public function strokeRect(
        float $x,
        float $y,
        float $width,
        float $height,
        string $color,
        float $lineWidth = 1,
    ): self {
        return $this->add(CanvasCommandKind::StrokeRect, $x, $y, $width, $height, Color::assert($color), $lineWidth);
    }

    public function strokeRoundRect(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius,
        string $color,
        float $lineWidth = 1,
    ): self {
        return $this->add(
            CanvasCommandKind::StrokeRoundRect,
            $x,
            $y,
            $width,
            $height,
            max(0, $radius),
            Color::assert($color),
            $lineWidth,
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $lineWidth = 1): self
    {
        return $this->add(CanvasCommandKind::Line, $x1, $y1, $x2, $y2, Color::assert($color), $lineWidth);
    }

    /**
     * Dashed straight line, for grids and baselines.
     */
    public function dashedLine(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        string $color,
        float $lineWidth = 1,
        float $dash = 4,
        float $gap = 4,
    ): self {
        if ($dash <= 0 || $gap <= 0) {
            throw new InvalidArgumentException('Canvas dash and gap must be positive.');
        }

        return $this->add(
            CanvasCommandKind::DashedLine,
            $x1,
            $y1,
            $x2,
            $y2,
            Color::assert($color),
            $lineWidth,
            $dash,
            $gap,
        );
    }

    /**
     * Stroked arc of a circle.
     */
    public function arc(
        float $cx,
        float $cy,
        float $radius,
        float $startDegrees,
        float $sweepDegrees,
        string $color,
        float $lineWidth = 1,
        LineCap $cap = LineCap::Butt,
    ): self {
        return $this->add(
            CanvasCommandKind::Arc,
            $cx,
            $cy,
            $radius,
            $startDegrees,
            $sweepDegrees,
            Color::assert($color),
            $lineWidth,
            $cap->value,
        );
    }

    /**
     * Open stroked polyline.
     *
     * @param list<array{0: float, 1: float}> $points at least two
     */
    public function polyline(
        array $points,
        string $color,
        float $lineWidth = 1,
        LineCap $cap = LineCap::Round,
        LineJoin $join = LineJoin::Round,
    ): self {
        return $this->add(
            CanvasCommandKind::Polyline,
            self::encodePoints($points, 2),
            Color::assert($color),
            $lineWidth,
            $cap->value,
            $join->value,
        );
    }

    // -- text -----------------------------------------------------------------

    /**
     * Left-aligned regular text; `$y` is the baseline.
     */
    public function text(string $text, float $x, float $y, float $size, string $color): self
    {
        return $this->add(CanvasCommandKind::Text, $text, $x, $y, $size, Color::assert($color));
    }

    /**
     * Aligned, weighted text; `$y` is the baseline.
     */
    public function label(
        string $text,
        float $x,
        float $y,
        float $size,
        string $color,
        TextAlign $align = TextAlign::Left,
        FontWeight $weight = FontWeight::Regular,
    ): self {
        return $this->add(
            CanvasCommandKind::Label,
            $text,
            $x,
            $y,
            $size,
            Color::assert($color),
            $align->value,
            $weight->value,
        );
    }

    // -- output ---------------------------------------------------------------

    /**
     * Validates and freezes the display list.
     */
    public function scene(): CanvasScene
    {
        return new CanvasScene($this->commands);
    }

    /**
     * Encodes points as `"x,y x,y …"`.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function encodePoints(array $points, int $minimum = 2): string
    {
        $count = count($points);

        if ($count < $minimum || $count > self::MAX_POINTS) {
            throw new InvalidArgumentException("Canvas point lists need {$minimum} to 512 points.");
        }

        $encoded = [];

        foreach ($points as $point) {
            $encoded[] = Numbers::format((float) $point[0]) . ',' . Numbers::format((float) $point[1]);
        }

        return implode(' ', $encoded);
    }

    private function add(CanvasCommandKind $kind, float|int|string ...$arguments): self
    {
        $copy = clone $this;
        $copy->commands[] = new CanvasCommand($kind, array_values($arguments));

        return $copy;
    }
}
