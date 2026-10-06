<?php

declare(strict_types=1);

namespace Pam\Native\Canvas;

/**
 * Wire kinds of the display list. Values are sequential and stable: kinds
 * 1–12 are the 0.1.0 contract, 13–25 were added in 0.2.0.
 */
enum CanvasCommandKind: int
{
    case Save = 1;
    case Restore = 2;
    case Translate = 3;
    case Rotate = 4;
    case Scale = 5;
    case ClipRect = 6;
    case Clear = 7;
    case FillRect = 8;
    case StrokeRect = 9;
    case Circle = 10;
    case Line = 11;
    case Text = 12;
    case RoundRect = 13;
    case StrokeRoundRect = 14;
    case Arc = 15;
    case Sector = 16;
    case Polyline = 17;
    case Polygon = 18;
    case Path = 19;
    case GradientRect = 20;
    case GradientPath = 21;
    case Alpha = 22;
    case Label = 23;
    case Shadow = 24;
    case DashedLine = 25;
}
