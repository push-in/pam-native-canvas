<?php
declare(strict_types=1);
namespace Pam\Native\Canvas;
use InvalidArgumentException;use JsonException;
final readonly class CanvasScene
{
    /** @var list<CanvasCommand> */ public array $commands;
    /** @param array<array-key,mixed> $commands */ public function __construct(array$commands){if(count($commands)>10_000)throw new InvalidArgumentException('Canvas scenes are limited to 10,000 commands.');$normalized=[];foreach($commands as$command){if(!$command instanceof CanvasCommand)throw new InvalidArgumentException('Canvas scenes require CanvasCommand instances.');$normalized[]=$command;}$this->commands=$normalized;}
    /** @throws JsonException */ public function toJson():string{return json_encode(array_map(static fn(CanvasCommand$c):array=>$c->toArray(),$this->commands),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
