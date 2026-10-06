<?php

declare(strict_types=1);

namespace Pam\Native\Canvas;

use Closure;
use Pam\Native\Element;
use Pam\Native\Internal\Wire;
use Pam\Native\Renderable;
use Pam\Native\UI\CustomView;

/**
 * Renders a {@see CanvasScene} through the `canvas.view` native view.
 *
 * The view takes its size from layout like any element (`class`/`style`
 * width and height). Coordinates are density-independent pixels by default
 * ({@see dp()}); call {@see px()} to draw in raw device pixels. Pointer
 * events arrive in the same units the scene uses.
 */
final class CanvasView implements Renderable
{
    public const string NAME = 'canvas.view';

    /** @var Closure(CanvasEventKind, float, float): void|null */
    private ?Closure $handler = null;

    private bool $densityIndependent = true;

    private function __construct(
        private readonly CanvasScene $scene,
        private readonly int $revision,
    ) {
    }

    /**
     * @param int $revision bump it whenever the scene changes so the native
     *                      view redraws; negative values are clamped to 0
     */
    public static function make(CanvasScene $scene, int $revision = 1): self
    {
        return new self($scene, max(0, $revision));
    }

    /**
     * Coordinates are dp: the renderer scales by the display density and
     * reports pointer positions in dp. This is the default.
     */
    public function dp(): self
    {
        $copy = clone $this;
        $copy->densityIndependent = true;

        return $copy;
    }

    /**
     * Coordinates are raw device pixels (0.1.0 behaviour).
     */
    public function px(): self
    {
        $copy = clone $this;
        $copy->densityIndependent = false;

        return $copy;
    }

    /**
     * @param Closure(CanvasEventKind, float, float): void $handler
     */
    public function onPointer(Closure $handler): self
    {
        $copy = clone $this;
        $copy->handler = $handler;

        return $copy;
    }

    public function toElement(): Element
    {
        $view = CustomView::make(self::NAME, [
            'displayList' => $this->scene->toJson(),
            'revision' => $this->revision,
            'density' => $this->densityIndependent ? 1 : 0,
        ]);

        $handler = $this->handler;

        if ($handler === null) {
            return $view;
        }

        return $view->onNativeEvent(static function (string $payload) use ($handler): void {
            $values = Wire::decodeMap($payload);
            $kind = CanvasEventKind::tryFrom((int) ($values['event'] ?? 4)) ?? CanvasEventKind::PointerCancel;

            $handler($kind, (float) ($values['x'] ?? 0), (float) ($values['y'] ?? 0));
        });
    }
}
