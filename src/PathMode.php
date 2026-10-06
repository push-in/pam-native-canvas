<?php

declare(strict_types=1);

namespace Pam\Native\Canvas;

/**
 * Whether a path is filled or stroked.
 */
enum PathMode: int
{
    case Fill = 1;
    case Stroke = 2;
}
