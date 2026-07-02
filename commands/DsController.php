<?php

declare(strict_types=1);

namespace app\commands;

use app\components\aliexpress\AliExpressDsClient;
use app\models\Setting;
use Throwable;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Dropshipping API token maintenance.
 *
 * The client already auto-refreshes the short-lived access_token on demand (right before an import),
 * but that only fires while the worker is actually importing. This command refreshes the token
 * proactively from cron so the (rotating) refresh_token never lapses during an idle stretch —
 * keeping the whole chain alive without a manual OAuth re-authorization.
 *
 * Cron, e.g.: `0 * /6 * * * php yii ds/refresh-token`.
 */
final class DsController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly AliExpressDsClient $client = new AliExpressDsClient(),
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Force a refresh of the Dropshipping access token using the stored refresh_token, so the token
     * chain stays alive even when no imports have run recently. No-op-safe to run on a schedule.
     */
    public function actionRefreshToken(): int
    {
        if (!$this->client->isConnected()) {
            $this->stderr("Dropshipping API is not connected — nothing to refresh.\n");
            $this->stderr("Authorize it once via the OAuth flow (see cron.txt → \"Ponowna autoryzacja\").\n");

            return ExitCode::CONFIG;
        }

        try {
            $this->client->refreshAccessToken();
        } catch (Throwable $e) {
            $this->stderr("Refresh failed: {$e->getMessage()}\n");
            $this->stderr("If the refresh_token itself expired, re-authorize once via the OAuth flow "
                . "(see cron.txt → \"Ponowna autoryzacja Dropshipping API\").\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $expiresAt = (int)Setting::get(Setting::DS_TOKEN_EXPIRES_AT, '0');
        $when = $expiresAt > 0 ? date('Y-m-d H:i:s', $expiresAt) : 'unknown';
        $this->stdout("OK — Dropshipping token refreshed. Access token now valid until {$when}.\n");

        return ExitCode::OK;
    }
}
