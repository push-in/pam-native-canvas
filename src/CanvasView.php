<?php
declare(strict_types=1);
namespace Pam\Native\Canvas;
use Closure;use Pam\Native\Element;use Pam\Native\Internal\Wire;use Pam\Native\Renderable;use Pam\Native\UI\CustomView;
final class CanvasView implements Renderable
{
    private ?Closure$handler=null;private function __construct(private readonly CanvasScene$scene,private readonly int$revision){}public static function make(CanvasScene$scene,int$revision=1):self{return new self($scene,max(0,$revision));}
    /** @param Closure(CanvasEventKind,float,float):void $handler */public function onPointer(Closure$handler):self{$copy=clone$this;$copy->handler=$handler;return$copy;}
    public function toElement():Element{$view=CustomView::make('canvas.view',['displayList'=>$this->scene->toJson(),'revision'=>$this->revision]);$handler=$this->handler;return$handler===null?$view:$view->onNativeEvent(static function(string$payload)use($handler):void{$v=Wire::decodeMap($payload);$kind=CanvasEventKind::tryFrom((int)($v['event']??4))??CanvasEventKind::PointerCancel;$handler($kind,(float)($v['x']??0),(float)($v['y']??0));});}
}
