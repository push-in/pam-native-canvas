<?php

declare(strict_types=1);

namespace Pam\Native\Canvas;

use InvalidArgumentException;
use JsonException;

/**
 * An immutable, validated display list ready to be sent to the native view.
 */
final readonly class CanvasScene
{
    public const int MAX_COMMANDS = 10_000;

    /** @var list<CanvasCommand> */
    public array $commands;

    /**
     * @param array<array-key, mixed> $commands
     *
     * @throws InvalidArgumentException when the list is too long, contains
     *                                  foreign values or has unbalanced state
     */
    public function __construct(array $commands)
    {
        if (count($commands) > self::MAX_COMMANDS) {
            throw new InvalidArgumentException('Canvas scenes are limited to 10,000 commands.');
        }

        $normalized = [];
        $depth = 0;

        foreach ($commands as $command) {
            if (!$command instanceof CanvasCommand) {
                throw new InvalidArgumentException('Canvas scenes require CanvasCommand instances.');
            }

            match ($command->kind) {
                CanvasCommandKind::Save => $depth++,
                CanvasCommandKind::Restore => --$depth < 0
                    ? throw new InvalidArgumentException('Canvas restore has no matching save.')
                    : null,
                CanvasCommandKind::Alpha,
                CanvasCommandKind::Shadow => $depth === 0
                    ? throw new InvalidArgumentException('Canvas alpha and shadow must be used between save and restore.')
                    : null,
                default => null,
            };

            $normalized[] = $command;
        }

        if ($depth !== 0) {
            throw new InvalidArgumentException('Canvas save and restore commands must be balanced.');
        }

        $this->commands = $normalized;
    }

    /**
     * Encodes the display list as `[{"k":kind,"a":[...]}, ...]`.
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        $entries = array_map(
            static fn (CanvasCommand $command): array => $command->toArray(),
            $this->commands,
        );

        return json_encode(
            $entries,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
