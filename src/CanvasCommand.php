<?php
declare(strict_types=1);
namespace Pam\Native\Canvas;
use InvalidArgumentException;
final readonly class CanvasCommand
{
    /** @param list<float|int|string> $arguments */
    public function __construct(public CanvasCommandKind $kind,public array $arguments=[]){if(count($arguments)>16)throw new InvalidArgumentException('Canvas commands accept at most 16 arguments.');foreach($arguments as$value){if(is_string($value)){if(strlen($value)>4096||str_contains($value,"\0"))throw new InvalidArgumentException('Canvas text is invalid.');}elseif(!is_finite((float)$value))throw new InvalidArgumentException('Canvas numbers must be finite.');}}
    /** @return array{k:int,a:list<float|int|string>} */ public function toArray():array{return ['k'=>$this->kind->value,'a'=>$this->arguments];}
}
