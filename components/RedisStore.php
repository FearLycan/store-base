<?php

declare(strict_types=1);

namespace app\components;

use Predis\Client;
use Throwable;
use Yii;

/**
 * Thin, fail-soft wrapper over a shared Redis instance (predis, unix socket).
 *
 * Every deployment on the host points at the SAME socket + password + namespace, so they share one
 * keyspace — that's what lets a single AliExpress DS token (and later a product cache) be coordinated
 * across otherwise-autonomous store instances. See {@see \app\components\aliexpress\DsTokenStore}.
 *
 * Fail-soft by design: mydevil's Redis is a user-launched process that can die, so every method
 * swallows connection errors and returns null/false instead of throwing. Callers then transparently
 * fall back to per-app state, and the first failure short-circuits the rest of the process (no repeated
 * connect attempts on a dead socket).
 */
final class RedisStore
{
    private ?Client $client = null;
    private bool $failed = false;

    public function __construct(
        private readonly string $socket,
        private readonly ?string $password,
        private readonly string $namespace,
    ) {
    }

    /** Build from `redis.*` params. `redis.socket` empty ⇒ feature off (isConfigured() === false). */
    public static function fromParams(): self
    {
        $params = Yii::$app->params;
        $password = trim((string)($params['redis.password'] ?? ''));

        return new self(
            trim((string)($params['redis.socket'] ?? '')),
            $password !== '' ? $password : null,
            trim((string)($params['redis.namespace'] ?? 'snagloft')) ?: 'snagloft',
        );
    }

    /** Whether a socket is configured at all. False ⇒ callers use their legacy per-app path. */
    public function isConfigured(): bool
    {
        return $this->socket !== '';
    }

    public function get(string $key): ?string
    {
        $value = $this->run(fn (Client $c) => $c->get($this->key($key)));

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): bool
    {
        return (bool)$this->run(function (Client $c) use ($key, $value, $ttlSeconds): bool {
            if ($ttlSeconds !== null && $ttlSeconds > 0) {
                $c->set($this->key($key), $value, 'EX', $ttlSeconds);
            } else {
                $c->set($this->key($key), $value);
            }

            return true;
        });
    }

    /**
     * Atomic `SET key <token> NX EX ttl`. Returns true only when WE set it (i.e. won the lock); false
     * when someone else holds it OR Redis is unreachable — both cases mean "don't act", which is exactly
     * the safe behaviour for the single-refresher guard.
     */
    public function acquireLock(string $key, string $token, int $ttlSeconds): bool
    {
        return (bool)$this->run(
            fn (Client $c): bool => (string)$c->set($this->key($key), $token, 'EX', $ttlSeconds, 'NX') === 'OK',
        );
    }

    /** True when a live connection is available (used to distinguish "down" from "empty"). */
    public function isAvailable(): bool
    {
        return $this->client() !== null;
    }

    private function key(string $key): string
    {
        return $this->namespace . ':' . $key;
    }

    private function client(): ?Client
    {
        if ($this->failed || $this->socket === '') {
            return null;
        }
        if ($this->client === null) {
            try {
                $client = new Client([
                    'scheme'   => 'unix',
                    'path'     => $this->socket,
                    'password' => $this->password,
                ]);
                $client->connect();
                $this->client = $client;
            } catch (Throwable $e) {
                $this->markFailed($e);

                return null;
            }
        }

        return $this->client;
    }

    private function run(callable $op): mixed
    {
        $client = $this->client();
        if ($client === null) {
            return null;
        }
        try {
            return $op($client);
        } catch (Throwable $e) {
            $this->markFailed($e);

            return null;
        }
    }

    private function markFailed(Throwable $e): void
    {
        $this->failed = true;
        Yii::warning('Redis unavailable, falling back to local state: ' . $e->getMessage(), __METHOD__);
    }
}
