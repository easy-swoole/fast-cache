<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Storage;

use RuntimeException;

/** @internal */
final class HashStore
{
    /** @var array<string, array{id: string, fields: list<string>, values: array}> */
    private array $hashes = [];

    public function hset(string $key, string $field, mixed $value): bool
    {
        if (!isset($this->hashes[$key])) {
            $this->hashes[$key] = ['id' => bin2hex(random_bytes(16)), 'fields' => [], 'values' => []];
        }

        $hash = &$this->hashes[$key];
        if (!array_key_exists($field, $hash['values'])) {
            $hash['fields'][] = $field;
        }
        $hash['values'][$field] = $value;

        return true;
    }

    public function hget(string $key, string $field): mixed
    {
        return $this->hashes[$key]['values'][$field] ?? null;
    }

    public function hdel(string $key, string $field): bool
    {
        if (!isset($this->hashes[$key]) || !array_key_exists($field, $this->hashes[$key]['values'])) {
            return false;
        }

        $hash = &$this->hashes[$key];
        if (count($hash['fields']) === 1) {
            unset($this->hashes[$key]);

            return true;
        }

        $id = bin2hex(random_bytes(16));
        $position = array_search($field, $hash['fields'], true);
        array_splice($hash['fields'], $position, 1);
        unset($hash['values'][$field]);
        $hash['id'] = $id;

        return true;
    }

    public function hscan(string $key, ?string $cursor, int $count): array
    {
        $hash = $this->hashes[$key] ?? null;
        $offset = 0;
        $fieldCount = count($hash['fields'] ?? []);
        if ($cursor !== null) {
            if ($hash === null || !preg_match('/\A([a-f0-9]{32}):([1-9][0-9]*)\z/', $cursor, $parts)
                || $parts[1] !== $hash['id'] || (string) (int) $parts[2] !== $parts[2]
                || (int) $parts[2] >= $fieldCount
            ) {
                throw new RuntimeException('Invalid or expired FastCache hscan cursor');
            }
            $offset = (int) $parts[2];
        }

        if ($hash === null) {
            return ['cursor' => null, 'items' => []];
        }

        $items = [];
        $end = $offset + min($count, $fieldCount - $offset);
        for ($position = $offset; $position < $end; ++$position) {
            $field = $hash['fields'][$position];
            $items[] = ['field' => $field, 'value' => $hash['values'][$field]];
        }

        return [
            'cursor' => $end < $fieldCount ? $hash['id'] . ':' . $end : null,
            'items' => $items,
        ];
    }
}
