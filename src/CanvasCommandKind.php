<?php
declare(strict_types=1);
namespace Pam\Native\Canvas;
enum CanvasCommandKind:int {case Save=1;case Restore=2;case Translate=3;case Rotate=4;case Scale=5;case ClipRect=6;case Clear=7;case FillRect=8;case StrokeRect=9;case Circle=10;case Line=11;case Text=12;}
