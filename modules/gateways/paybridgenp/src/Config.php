<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS;

/**
 * Small value object that reads WHMCS gateway params (persisted in
 * tblpaymentgateways) and normalises them for the rest of the module.
 *
 * WHMCS hands us the same $params array to _link, _refund, and through
 * getGatewayVariables() inside the callback — so we funnel everything
 * through here.
 */
final class Config
{
    /** @var array<string,mixed> */
    private $params;

    /** @param array<string,mixed> $params */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function isTestMode(): bool
    {
        return !empty($this->params['testMode']) && $this->params['testMode'] !== 'off';
    }

    /**
     * Return the API key for the active mode. Falls back to the live key if
     * testMode is on but no test key is configured — matches Stripe/other
     * WHMCS gateway conventions.
     */
    public function apiKey(): string
    {
        if ($this->isTestMode() && !empty($this->params['testSecretKey'])) {
            return (string) $this->params['testSecretKey'];
        }
        return (string) ($this->params['secretKey'] ?? '');
    }

    /** @return array{api_key:string, base_url?:string} */
    public function sdkConfig(): array
    {
        $config  = ['api_key' => $this->apiKey()];
        $baseUrl = trim((string) getenv('PAYBRIDGENP_API_BASE'));
        if ($baseUrl !== '') {
            $config['base_url'] = rtrim($baseUrl, '/');
        }
        return $config;
    }

    public function webhookSecret(): string
    {
        return (string) ($this->params['webhookSecret'] ?? '');
    }

    /**
     * `auto` = let the payer pick on the PayBridgeNP hosted page.
     * `esewa` / `khalti` / `fonepay` = force a single method upstream.
     */
    public function paymentMethod(): string
    {
        $method = (string) ($this->params['paymentMethod'] ?? 'auto');
        return in_array($method, ['auto', 'esewa', 'khalti', 'fonepay'], true) ? $method : 'auto';
    }

    public function debugLogging(): bool
    {
        return !empty($this->params['debugLogging']) && $this->params['debugLogging'] !== 'off';
    }

    public function gatewayName(): string
    {
        // Used for logTransaction() and the Gateway Log display name.
        // WHMCS sets $params['paymentmethod'] to the filename ("paybridgenp").
        return (string) ($this->params['paymentmethod'] ?? 'paybridgenp');
    }

    public function systemUrl(): string
    {
        // Always with a trailing slash, per WHMCS convention.
        $url = rtrim((string) ($this->params['systemurl'] ?? ''), '/');
        return $url === '' ? '' : $url . '/';
    }

    /**
     * Base URL for the return/webhook callback. Falls back to the WHMCS
     * System URL if no override is configured. Always ends with '/'.
     *
     * The override exists for two cases:
     *   - WHMCS is behind a load balancer / different public hostname than
     *     its internal SystemURL setting.
     *   - Local dev: SystemURL is http://localhost:8080 but PayBridgeNP needs
     *     a cloudflared / ngrok tunnel URL to reach us.
     */
    public function callbackBaseUrl(): string
    {
        $override = trim((string) ($this->params['publicUrl'] ?? ''));
        if ($override !== '') {
            return rtrim($override, '/') . '/';
        }
        return $this->systemUrl();
    }

    /** @return array<string,mixed> */
    public function raw(): array
    {
        return $this->params;
    }
}
