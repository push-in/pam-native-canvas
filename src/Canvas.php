<?php
declare(strict_types=1);
namespace Pam\Native\Canvas;
final class Canvas
{
    /** @var list<CanvasCommand> */ private array$commands=[];
    public function save():self{return $this->add(CanvasCommandKind::Save);}public function restore():self{return $this->add(CanvasCommandKind::Restore);}public function translate(float$x,float$y):self{return $this->add(CanvasCommandKind::Translate,$x,$y);}public function rotate(float$degrees):self{return $this->add(CanvasCommandKind::Rotate,$degrees);}public function scale(float$x,float$y):self{return $this->add(CanvasCommandKind::Scale,$x,$y);}public function clipRect(float$x,float$y,float$width,float$height):self{return $this->add(CanvasCommandKind::ClipRect,$x,$y,$width,$height);}public function clear(string$color='#00000000'):self{return $this->add(CanvasCommandKind::Clear,$color);}public function fillRect(float$x,float$y,float$width,float$height,string$color):self{return $this->add(CanvasCommandKind::FillRect,$x,$y,$width,$height,$color);}public function strokeRect(float$x,float$y,float$width,float$height,string$color,float$lineWidth=1):self{return $this->add(CanvasCommandKind::StrokeRect,$x,$y,$width,$height,$color,$lineWidth);}public function circle(float$x,float$y,float$radius,string$color):self{return $this->add(CanvasCommandKind::Circle,$x,$y,$radius,$color);}public function line(float$x1,float$y1,float$x2,float$y2,string$color,float$lineWidth=1):self{return $this->add(CanvasCommandKind::Line,$x1,$y1,$x2,$y2,$color,$lineWidth);}public function text(string$text,float$x,float$y,float$size,string$color):self{return $this->add(CanvasCommandKind::Text,$text,$x,$y,$size,$color);}
    public function scene():CanvasScene{return new CanvasScene($this->commands);}
    private function add(CanvasCommandKind$kind,float|int|string...$arguments):self{$copy=clone$this;$copy->commands[]=new CanvasCommand($kind,array_values($arguments));return$copy;}
}
