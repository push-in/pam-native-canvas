<?php
declare(strict_types=1);
use Pam\Native\Canvas\Canvas;use Pam\Native\Canvas\CanvasCommandKind;use Pam\Native\Canvas\CanvasEventKind;use Pam\Native\Canvas\CanvasView;use Pam\Native\UI\CustomView;
require dirname(__DIR__).'/vendor/autoload.php';
function expect(bool$c,string$m):void{if(!$c)throw new RuntimeException($m);}
expect(array_column(CanvasCommandKind::cases(),'value')===range(1,12),'Command values changed.');expect(array_column(CanvasEventKind::cases(),'value')===range(1,4),'Event values changed.');
$canvas=(new Canvas())->clear('#ffffffff')->save()->translate(20,30)->fillRect(0,0,120,80,'#ff3366ff')->circle(60,40,20,'#ffffffff')->text('PAM',20,70,24,'#ffffffff')->restore();
expect(count($canvas->scene()->commands)===7,'Display list command count changed.');expect(CanvasView::make($canvas->scene())->toElement()::class===CustomView::class,'Canvas is not a native custom view.');
try{(new Canvas())->text(str_repeat('x',4097),0,0,12,'#fff');throw new RuntimeException('Oversized text accepted.');}catch(InvalidArgumentException){}
echo "PAM Native Canvas contracts passed.\n";
