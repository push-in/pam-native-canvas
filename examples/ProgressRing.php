<?php

declare(strict_types=1);

// A 56dp progress ring with a centered label, usable as a component's render output.

use Pam\Native\Canvas\Canvas;
use Pam\Native\Canvas\CanvasView;
use Pam\Native\Canvas\FontWeight;
use Pam\Native\Canvas\LineCap;
use Pam\Native\Canvas\TextAlign;

$size = 56;
$progress = 0.42;
$center = $size / 2;
$radius = $center - 4;

$scene = (new Canvas())
    ->arc($center, $center, $radius, 0, 360, '#2c3947', 6)
    ->arc($center, $center, $radius, 0, 360 * $progress, '#19c5ff', 6, LineCap::Round)
    ->label('42%', $center, $center + 4, 12, '#f4f7fa', TextAlign::Center, FontWeight::SemiBold)
    ->scene();

return CanvasView::make($scene, revision: 1)->dp();
