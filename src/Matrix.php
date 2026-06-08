<?php

declare(strict_types=1);

/**
 * The immutable module grid of an encoded QR symbol.
 *
 * A square grid of booleans where `true` is a dark module and `false` is light.
 * The grid excludes the quiet zone — the surrounding margin is a rendering
 * concern applied by renderers, not part of the symbol data.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode;

use OutOfRangeException;

final readonly class Matrix
{
    /**
     * @param list<list<bool>> $modules Row-major grid; every row has `$size` columns.
     */
    public function __construct(
        private array $modules,
        public int $size,
    ) {}

    /**
     * Whether the module at column `$x`, row `$y` is dark.
     *
     * @throws OutOfRangeException if the coordinate is outside the grid
     */
    public function isDark(int $x, int $y): bool
    {
        if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
            throw new OutOfRangeException("Coordinate ({$x}, {$y}) is outside the {$this->size}×{$this->size} matrix.");
        }

        return $this->modules[$y][$x];
    }

    /**
     * The grid as a row-major array of boolean rows.
     *
     * @return list<list<bool>>
     */
    public function toArray(): array
    {
        return $this->modules;
    }
}
