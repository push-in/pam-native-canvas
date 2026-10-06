<?php

declare(strict_types=1);

use Pam\Native\Canvas\Canvas;
use Pam\Native\Canvas\CanvasCommandKind;
use Pam\Native\Canvas\CanvasEventKind;
use Pam\Native\Canvas\CanvasView;
use Pam\Native\Canvas\Color;
use Pam\Native\Canvas\FontWeight;
use Pam\Native\Canvas\LineCap;
use Pam\Native\Canvas\LineJoin;
use Pam\Native\Canvas\Path;
use Pam\Native\Canvas\PathMode;
use Pam\Native\Canvas\TextAlign;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\PropKey;
use Pam\Native\UI\CustomView;

require dirname(__DIR__) . '/vendor/autoload.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param Closure(): mixed $action
 */
function expectRejected(Closure $action, string $message): void
{
    try {
        $action();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

/**
 * @return array{k: int, a: list<float|int|string>}
 */
function lastCommand(Canvas $canvas): array
{
    return commandAt($canvas, count($canvas->scene()->commands) - 1);
}

/**
 * @return array{k: int, a: list<float|int|string>}
 */
function commandAt(Canvas $canvas, int $index): array
{
    return $canvas->scene()->commands[$index]->toArray();
}

// -- enums stay sequential ---------------------------------------------------

expect(array_column(CanvasCommandKind::cases(), 'value') === range(1, 25), 'Command values changed.');
expect(array_column(CanvasEventKind::cases(), 'value') === range(1, 4), 'Event values changed.');
expect(array_column(LineCap::cases(), 'value') === range(1, 2), 'LineCap values changed.');
expect(array_column(LineJoin::cases(), 'value') === range(1, 3), 'LineJoin values changed.');
expect(array_column(PathMode::cases(), 'value') === range(1, 2), 'PathMode values changed.');
expect(array_column(TextAlign::cases(), 'value') === range(1, 3), 'TextAlign values changed.');
expect(array_column(FontWeight::cases(), 'value') === range(1, 4), 'FontWeight values changed.');

// -- 0.1.0 scene stays the same ----------------------------------------------

$legacy = (new Canvas())
    ->clear('#ffffffff')
    ->save()
    ->translate(20, 30)
    ->fillRect(0, 0, 120, 80, '#ff3366ff')
    ->circle(60, 40, 20, '#ffffffff')
    ->text('PAM', 20, 70, 24, '#ffffffff')
    ->restore();

expect(count($legacy->scene()->commands) === 7, 'Display list command count changed.');
expect(
    $legacy->scene()->toJson() === '[{"k":7,"a":["#ffffffff"]},{"k":1,"a":[]},{"k":3,"a":[20,30]},'
        . '{"k":8,"a":[0,0,120,80,"#ff3366ff"]},{"k":10,"a":[60,40,20,"#ffffffff"]},'
        . '{"k":12,"a":["PAM",20,70,24,"#ffffffff"]},{"k":2,"a":[]}]',
    'Legacy scene JSON changed.',
);
expect(CanvasView::make($legacy->scene())->toElement()::class === CustomView::class, 'Canvas is not a native custom view.');

// -- new commands produce the right kind and arguments ----------------------

$blank = new Canvas();

expect(lastCommand($blank->roundRect(1, 2, 3, 4, 5, '#abc')) === ['k' => 13, 'a' => [1.0, 2.0, 3.0, 4.0, 5.0, '#abc']], 'roundRect');
expect(lastCommand($blank->strokeRoundRect(1, 2, 3, 4, 5, '#abcdef', 2)) === ['k' => 14, 'a' => [1.0, 2.0, 3.0, 4.0, 5.0, '#abcdef', 2.0]], 'strokeRoundRect');
expect(lastCommand($blank->arc(10, 10, 8, 0, 270, '#fff', 3, LineCap::Round)) === ['k' => 15, 'a' => [10.0, 10.0, 8.0, 0.0, 270.0, '#fff', 3.0, 2]], 'arc');
expect(lastCommand($blank->sector(10, 10, 8, 4, 90, 45, '#fff')) === ['k' => 16, 'a' => [10.0, 10.0, 8.0, 4.0, 90.0, 45.0, '#fff']], 'sector');
expect(lastCommand($blank->polyline([[0, 0], [10.5, 2], [20, 0.25]], '#fff', 2, LineCap::Butt, LineJoin::Bevel)) === ['k' => 17, 'a' => ['0,0 10.5,2 20,0.25', '#fff', 2.0, 1, 3]], 'polyline');
expect(lastCommand($blank->polygon([[0, 0], [10, 0], [5, 5]], '#fff')) === ['k' => 18, 'a' => ['0,0 10,0 5,5', '#fff']], 'polygon');
expect(lastCommand($blank->path(Path::start(0, 10)->lineTo(20, 5), '#fff', 2, PathMode::Stroke)) === ['k' => 19, 'a' => ['M0 10 L20 5', '#fff', 2.0, 2]], 'path');
expect(lastCommand($blank->gradientRect(0, 0, 10, 20, 4, 0, 0, 0, 20, '#ff0000', '#ff000000')) === ['k' => 20, 'a' => [0.0, 0.0, 10.0, 20.0, 4.0, 0.0, 0.0, 0.0, 20.0, '#ff0000', '#ff000000']], 'gradientRect');
expect(lastCommand($blank->gradientPath(Path::start(0, 0)->lineTo(1, 1)->close(), 0, 0, 0, 1, '#f00', '#0f0')) === ['k' => 21, 'a' => ['M0 0 L1 1 Z', 0.0, 0.0, 0.0, 1.0, '#f00', '#0f0']], 'gradientPath');
expect(commandAt($blank->save()->alpha(0.5)->restore(), 1) === ['k' => 22, 'a' => [0.5]], 'alpha');
expect(lastCommand($blank->label('R$ 10', 5, 6, 12, '#fff', TextAlign::Right, FontWeight::Bold)) === ['k' => 23, 'a' => ['R$ 10', 5.0, 6.0, 12.0, '#fff', 3, 4]], 'label');
expect(commandAt($blank->save()->shadow('#00000080', 6, 0, 2)->restore(), 1) === ['k' => 24, 'a' => ['#00000080', 6.0, 0.0, 2.0]], 'shadow');
expect(lastCommand($blank->dashedLine(0, 1, 2, 3, '#fff', 1, 4, 2)) === ['k' => 25, 'a' => [0.0, 1.0, 2.0, 3.0, '#fff', 1.0, 4.0, 2.0]], 'dashedLine');

// -- scene JSON snapshot ------------------------------------------------------

$chart = (new Canvas())
    ->save()
    ->alpha(0.35)
    ->gradientPath(Path::start(0, 40)->lineTo(0, 10)->curveTo(10, 0, 20, 0, 30, 10)->lineTo(30, 40)->close(), 0, 0, 0, 40, '#19c5ff', '#19c5ff00')
    ->restore()
    ->path(Path::start(0, 10)->curveTo(10, 0, 20, 0, 30, 10), '#19c5ff', 2, PathMode::Stroke)
    ->label('Seg', 0, 52, 11, '#a0adbb', TextAlign::Center, FontWeight::Medium)
    ->scene();

expect(
    $chart->toJson() === '[{"k":1,"a":[]},{"k":22,"a":[0.35]},'
        . '{"k":21,"a":["M0 40 L0 10 C10 0 20 0 30 10 L30 40 Z",0,0,0,40,"#19c5ff","#19c5ff00"]},'
        . '{"k":2,"a":[]},{"k":19,"a":["M0 10 C10 0 20 0 30 10","#19c5ff",2,2]},'
        . '{"k":23,"a":["Seg",0,52,11,"#a0adbb",2,2]}]',
    'Chart scene JSON changed.',
);

// -- colors --------------------------------------------------------------------

foreach (['#abc', '#AABBCC', '#aabbccdd', '#00000000'] as $valid) {
    expect(Color::isValid($valid) && (string) Color::from($valid) === $valid, "Color {$valid} rejected.");
}

foreach (['abc', '#abcd', '#ggg', 'red', '#aabbccddee', '', '#aabbcc '] as $invalid) {
    expect(!Color::isValid($invalid), "Color {$invalid} accepted.");
    expectRejected(static fn () => Color::assert($invalid), "Color::assert accepted {$invalid}.");
}

expectRejected(static fn () => (new Canvas())->fillRect(0, 0, 1, 1, 'red'), 'fillRect accepted a named color.');
expectRejected(static fn () => (new Canvas())->clear('white'), 'clear accepted a named color.');

// -- path specs ----------------------------------------------------------------

expect(Path::start(0, 10)->lineTo(20, 5)->curveTo(30, 0, 40, 0, 50, 10)->close()->spec() === 'M0 10 L20 5 C30 0 40 0 50 10 Z', 'Path spec encoding changed.');
expect(Path::start(1.23456, -0.5)->quadTo(0, 0, 2, 2)->spec() === 'M1.235 -0.5 Q0 0 2 2', 'Path number formatting changed.');
expect(Path::fromSpec('M0 0 L10 10Z')->spec() === 'M0 0 L10 10Z', 'Path::fromSpec changed the spec.');
expect(Path::assert('M0 0 L1e2 1.5 Q1 1 2 2 C1 2 3 4 5 6 Z') !== '', 'Valid spec rejected.');

foreach (['', 'L0 0', 'M0', 'M0 0 A1 1', 'M0 0 L1', 'M0 0 Lx y', 'M0 0 l1 1', 'M0 0 L1 1 2', 'M0,0', str_repeat('M0 0 ', 1000)] as $bad) {
    expectRejected(static fn () => Path::assert($bad), "Path spec '" . substr($bad, 0, 20) . "' accepted.");
}

expectRejected(static fn () => Path::start(NAN, 0), 'Path accepted NAN.');
expectRejected(static fn () => Path::start(INF, 0), 'Path accepted INF.');

// -- points ---------------------------------------------------------------------

expect(Canvas::encodePoints([[0, 0], [1.5, -2]]) === '0,0 1.5,-2', 'Point encoding changed.');
expectRejected(static fn () => (new Canvas())->polyline([[0, 0]], '#fff'), 'Polyline accepted one point.');
expectRejected(static fn () => (new Canvas())->polygon([[0, 0], [1, 1]], '#fff'), 'Polygon accepted two points.');
expectRejected(static fn () => Canvas::encodePoints(array_fill(0, 513, [0, 0])), 'Points accepted more than 512 entries.');

// -- bounds and balance -------------------------------------------------------

expectRejected(static fn () => (new Canvas())->text(str_repeat('x', 4097), 0, 0, 12, '#fff'), 'Oversized text accepted.');
expectRejected(static fn () => (new Canvas())->restore()->scene(), 'Unbalanced restore accepted.');
expectRejected(static fn () => (new Canvas())->save()->scene(), 'Unbalanced save accepted.');
expectRejected(static fn () => (new Canvas())->alpha(0.5)->scene(), 'Alpha outside save/restore accepted.');
expectRejected(static fn () => (new Canvas())->shadow('#000', 1)->scene(), 'Shadow outside save/restore accepted.');
expectRejected(static fn () => (new Canvas())->alpha(1.5), 'Alpha above 1 accepted.');
expectRejected(static fn () => (new Canvas())->dashedLine(0, 0, 1, 1, '#fff', 1, 0, 1), 'Zero dash accepted.');

$big = new Canvas();
for ($i = 0; $i < 10_000; $i++) {
    $big = $big->fillRect(0, 0, 1, 1, '#fff');
}
expect(count($big->scene()->commands) === 10_000, 'Command cap moved.');
expectRejected(static fn () => $big->fillRect(0, 0, 1, 1, '#fff')->scene(), 'Scene accepted more than 10,000 commands.');

// -- view host properties ----------------------------------------------------

/**
 * @return array<string, string|int|float|bool>
 */
function hostProperties(CanvasView $view): array
{
    $binary = $view->toElement()->properties()[PropKey::HostProperties->value] ?? null;
    expect($binary instanceof BinaryValue, 'Host properties missing.');

    return Wire::decodeMap($binary->bytes);
}

$scene = (new Canvas())->fillRect(0, 0, 1, 1, '#fff')->scene();
$properties = hostProperties(CanvasView::make($scene, 7));
expect($properties['revision'] === 7 && $properties['density'] === 1, 'Default view is not dp.');
expect($properties['displayList'] === $scene->toJson(), 'Display list host property changed.');
expect(hostProperties(CanvasView::make($scene)->px())['density'] === 0, 'px() did not clear density.');
expect(hostProperties(CanvasView::make($scene)->px()->dp())['density'] === 1, 'dp() did not restore density.');
expect(hostProperties(CanvasView::make($scene, -3))['revision'] === 0, 'Negative revision not clamped.');

$received = null;
$view = CanvasView::make($scene)->onPointer(static function (CanvasEventKind $kind, float $x, float $y) use (&$received): void {
    $received = [$kind, $x, $y];
});
$element = $view->toElement();
$handlers = $element->events();
expect(count($handlers) === 1, 'Pointer handler not attached.');
$handler = reset($handlers);
expect($handler instanceof Closure, 'Pointer handler is not a closure.');
$handler(Wire::map(['event' => 3, 'x' => 12.5, 'y' => 4.0]));
expect($received === [CanvasEventKind::PointerUp, 12.5, 4.0], 'Pointer event not decoded.');

echo "PAM Native Canvas contracts passed.\n";
