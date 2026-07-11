<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use app\components\RedisStore;
use app\models\Setting;
use Throwable;
use Yii;
use yii\helpers\Json;

/**
 * Single source of truth for the DS OAuth token, shared across every store instance on the host.
 *
 * A single AliExpress DS app key means all deployments share one token; if each refreshed it
 * independently they would invalidate each other's (rotating) refresh_token. So the primary store is
 * Redis — key "<ns>:ds:token", a JSON blob {access, refresh, expires_at} — and exactly one instance
 * (the {@see acquireRefreshLock()} winner) ever refreshes.
 *
 * The local {@see Setting} table is a warm mirror: every read copies the fresh token back into it, so
 * if Redis disappears each app can coast on the last-known access token (the client refreshes ~10 min
 * early, so it stays valid) instead of failing imports. While Redis is down nobody refreshes — that's
 * the safe choice, since a blind fallback refresh is exactly the rotation race we're avoiding.
 */
final class DsTokenStore
{
    private const REDIS_KEY = 'ds:token';
    private const REFRESH_LOCK = 'ds:refresh:lock';
    private const LOCK_TTL = 60;

    private readonly RedisStore $redis;

    /** In-process memo so one accessToken() call doesn't hit Redis + DB several times. Cleared on persist(). */
    private ?array $cache = null;

    public function __construct(?RedisStore $redis = null)
    {
        $this->redis = $redis ?? RedisStore::fromParams();
    }

    public function accessToken(): string
    {
        return $this->read()['access'];
    }

    public function refreshToken(): string
    {
        return $this->read()['refresh'];
    }

    public function expiresAt(): int
    {
        return $this->read()['expires_at'];
    }

    public function isConnected(): bool
    {
        return $this->accessToken() !== '';
    }

    /**
     * Whether THIS process should perform the token refresh.
     *
     *  - Redis not configured ⇒ true (legacy single-app path: refresh directly).
     *  - Redis configured ⇒ only the lock winner gets true. Peers, and every instance while Redis is
     *    down, get false and coast on the still-valid token rather than racing to refresh.
     */
    public function acquireRefreshLock(): bool
    {
        if (!$this->redis->isConfigured()) {
            return true;
        }

        return $this->redis->acquireLock(self::REFRESH_LOCK, bin2hex(random_bytes(8)), self::LOCK_TTL);
    }

    /**
     * Persist a freshly obtained token to the shared store (and the local mirror). A missing/blank
     * refresh_token in the response means AE didn't rotate it — keep the last known one.
     */
    public function persist(string $access, ?string $refresh, int $expiresAt): void
    {
        if ($refresh === null || trim($refresh) === '') {
            $refresh = $this->refreshToken();
        }

        $this->redis->set(self::REDIS_KEY, Json::encode([
            'access'     => $access,
            'refresh'    => $refresh,
            'expires_at' => $expiresAt,
        ]));
        $this->mirrorToSetting($access, $refresh, $expiresAt);

        $this->cache = ['access' => $access, 'refresh' => $refresh, 'expires_at' => $expiresAt];
    }

    /**
     * @return array{access:string, refresh:string, expires_at:int}
     */
    private function read(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $raw = $this->redis->get(self::REDIS_KEY);
        if ($raw !== null && $raw !== '') {
            try {
                $data = Json::decode($raw);
                if (is_array($data) && trim((string)($data['access'] ?? '')) !== '') {
                    $token = [
                        'access'     => (string)$data['access'],
                        'refresh'    => (string)($data['refresh'] ?? ''),
                        'expires_at' => (int)($data['expires_at'] ?? 0),
                    ];
                    // Keep the local mirror warm so we can coast if Redis later disappears.
                    $this->mirrorToSetting($token['access'], $token['refresh'], $token['expires_at']);

                    return $this->cache = $token;
                }
            } catch (Throwable $e) {
                Yii::warning('Malformed DS token in Redis: ' . $e->getMessage(), __METHOD__);
            }
        }

        // Fallback: local Setting (Redis empty, unconfigured, or unavailable).
        return $this->cache = [
            'access'     => trim((string)Setting::get(Setting::DS_ACCESS_TOKEN, '')),
            'refresh'    => trim((string)Setting::get(Setting::DS_REFRESH_TOKEN, '')),
            'expires_at' => (int)Setting::get(Setting::DS_TOKEN_EXPIRES_AT, '0'),
        ];
    }

    /** Write the token into this app's own DB only when a field actually changed (avoids needless writes). */
    private function mirrorToSetting(string $access, string $refresh, int $expiresAt): void
    {
        if ($access !== '' && $access !== trim((string)Setting::get(Setting::DS_ACCESS_TOKEN, ''))) {
            Setting::set(Setting::DS_ACCESS_TOKEN, $access);
        }
        if ($refresh !== '' && $refresh !== trim((string)Setting::get(Setting::DS_REFRESH_TOKEN, ''))) {
            Setting::set(Setting::DS_REFRESH_TOKEN, $refresh);
        }
        if ($expiresAt > 0 && (string)$expiresAt !== (string)Setting::get(Setting::DS_TOKEN_EXPIRES_AT, '0')) {
            Setting::set(Setting::DS_TOKEN_EXPIRES_AT, (string)$expiresAt);
        }
    }
}
