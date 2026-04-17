<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS;

/**
 * Thin wrapper around WHMCS's logTransaction() helper so we can:
 *   - Unit-test our code without pulling in WHMCS
 *   - Guarantee secrets never land in the gateway log
 *   - Short-circuit debug-level entries when debugLogging=off
 */
final class Logger
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Always-logged event (payment confirmed, refund issued, webhook rejected).
     *
     * @param array<string,mixed>|string $data
     */
    public function info(string $event, $data, string $status = 'Info'): void
    {
        $this->write($event, $data, $status);
    }

    /** @param array<string,mixed>|string $data */
    public function error(string $event, $data): void
    {
        $this->write($event, $data, 'Error');
    }

    /**
     * Only written when debugLogging=on — raw request/response bodies etc.
     *
     * @param array<string,mixed>|string $data
     */
    public function debug(string $event, $data): void
    {
        if (!$this->config->debugLogging()) {
            return;
        }
        $this->write($event, $data, 'Debug');
    }

    /** @param array<string,mixed>|string $data */
    private function write(string $event, $data, string $status): void
    {
        $scrubbed = is_array($data) ? self::scrub($data) : $data;

        // logTransaction() is a WHMCS global. Skip the call when running
        // outside WHMCS (tests) so we don't have to stub it.
        if (function_exists('logTransaction')) {
            logTransaction($this->config->gatewayName(), [
                'event' => $event,
                'data'  => $scrubbed,
            ], $status);
        }
    }

    /**
     * Redact keys that would leak secrets if a merchant opens the Gateway Log.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function scrub(array $data): array
    {
        $redactKeys = [
            'api_key', 'apikey', 'secret', 'secretKey', 'testSecretKey',
            'webhookSecret', 'signing_secret', 'authorization',
            'x-paybridge-signature', 'X-PayBridge-Signature',
        ];

        $out = [];
        foreach ($data as $k => $v) {
            if (in_array((string) $k, $redactKeys, true)) {
                $out[$k] = '[REDACTED]';
                continue;
            }
            $out[$k] = is_array($v) ? self::scrub($v) : $v;
        }
        return $out;
    }
}
