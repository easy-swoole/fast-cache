<?php

declare(strict_types=1);

namespace EasySwoole\FastCache\Storage;


/** @internal */
final class ValueStore
{
    private array $values = [];
    private ExpirationHeap $expirations;

    public function __construct()
    {
        $this->expirations = new ExpirationHeap();
    }

    public function set(string $key, mixed $value, ?int $ttl): bool
    {
        $this->values[$key] = $value;
        if ($ttl === null) {
            $this->expirations->remove($key);
        } else {
            $this->expirations->set($key, hrtime(true) / 1_000_000_000 + $ttl);
        }

        return true;
    }

    public function get(string $key): mixed
    {
        $this->expireValue($key);

        return $this->values[$key] ?? null;
    }

    public function del(string $key): bool
    {
        $this->expireValue($key);
        $exists = array_key_exists($key, $this->values);
        unset($this->values[$key]);
        $this->expirations->remove($key);

        return $exists;
    }

    private function expireValue(string $key): void
    {
        $expiresAt = $this->expirations->get($key);
        if ($expiresAt !== null && $expiresAt <= hrtime(true) / 1_000_000_000) {
            $this->expirations->remove($key);
            unset($this->values[$key]);
        }
    }

    public function purgeExpired(int $limit): int
    {
        $now = hrtime(true) / 1_000_000_000;
        for ($count = 0; $count < $limit; ++$count) {
            $key = $this->expirations->popExpired($now);
            if ($key === null) {
                break;
            }

            unset($this->values[$key]);
        }

        return $count;
    }
}
