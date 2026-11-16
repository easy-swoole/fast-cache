<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Storage;

/** @internal */
final class ExpirationHeap
{
    /** @var list<array{key: string, expiresAt: float}> */
    private array $entries = [];

    /** @var array<string, int> */
    private array $positions = [];

    public function set(string $key, float $expiresAt): void
    {
        $position = $this->positions[$key] ?? count($this->entries);
        $this->entries[$position] = ['key' => $key, 'expiresAt' => $expiresAt];
        $this->positions[$key] = $position;
        $this->repair($position);
    }

    public function get(string $key): ?float
    {
        $position = $this->positions[$key] ?? null;

        return $position === null ? null : $this->entries[$position]['expiresAt'];
    }

    public function remove(string $key): void
    {
        $position = $this->positions[$key] ?? null;
        if ($position === null) {
            return;
        }

        unset($this->positions[$key]);
        $last = array_pop($this->entries);
        if ($position < count($this->entries)) {
            $this->entries[$position] = $last;
            $this->positions[$last['key']] = $position;
            $this->repair($position);
        }
    }

    public function popExpired(float $now): ?string
    {
        $first = $this->entries[0] ?? null;
        if ($first === null || $first['expiresAt'] > $now) {
            return null;
        }

        $this->remove($first['key']);

        return $first['key'];
    }

    private function repair(int $position): void
    {
        while ($position > 0) {
            $parent = intdiv($position - 1, 2);
            if ($this->entries[$parent]['expiresAt'] <= $this->entries[$position]['expiresAt']) {
                break;
            }

            $this->swap($parent, $position);
            $position = $parent;
        }

        $size = count($this->entries);
        while (($child = $position * 2 + 1) < $size) {
            $right = $child + 1;
            if ($right < $size && $this->entries[$right]['expiresAt'] < $this->entries[$child]['expiresAt']) {
                $child = $right;
            }
            if ($this->entries[$position]['expiresAt'] <= $this->entries[$child]['expiresAt']) {
                break;
            }

            $this->swap($position, $child);
            $position = $child;
        }
    }

    private function swap(int $first, int $second): void
    {
        [$this->entries[$first], $this->entries[$second]] = [$this->entries[$second], $this->entries[$first]];
        $this->positions[$this->entries[$first]['key']] = $first;
        $this->positions[$this->entries[$second]['key']] = $second;
    }
}
