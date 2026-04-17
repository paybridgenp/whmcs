<?php

declare(strict_types=1);

namespace PayBridgeNP\WHMCS;

/**
 * Maps the WHMCS $params array passed into _link() into the body our php-sdk
 * sends to POST /v1/checkout. Pulled out so it is unit-testable without
 * bringing up the gateway or the HTTP client.
 */
final class CheckoutParams
{
    /**
     * @param array<string,mixed> $params  Raw WHMCS gateway params
     * @param Config              $config  Normalised config accessor
     *
     * @return array{
     *   amount: int,
     *   currency: string,
     *   return_url: string,
     *   cancel_url: string,
     *   provider?: string,
     *   metadata: array<string,mixed>
     * }
     */
    public static function build(array $params, Config $config): array
    {
        $invoiceId = (int) ($params['invoiceid'] ?? 0);
        $callback  = rtrim($config->callbackBaseUrl(), '/') . '/modules/gateways/callback/paybridgenp.php';

        // WHMCS supplies the amount pre-converted to the gateway's configured
        // convertto currency when multi-currency is enabled; we treat whatever
        // we're given as NPR (enforced by the API when it receives currency=NPR).
        $body = [
            'amount'     => Amount::toPaisa($params['amount'] ?? 0),
            'currency'   => strtoupper((string) ($params['currency'] ?? 'NPR')),
            'return_url' => self::addQuery($callback, [
                'invoiceid' => (string) $invoiceId,
            ]),
            'cancel_url' => self::addQuery($callback, [
                'invoiceid' => (string) $invoiceId,
                'cancelled' => '1',
            ]),
            'metadata'   => [
                'invoiceid'      => (string) $invoiceId,
                'source'         => 'whmcs',
                'whmcs_version'  => (string) ($params['whmcsVersion'] ?? ''),
                'client_id'      => (string) ($params['clientdetails']['userid'] ?? ''),
                'client_email'   => (string) ($params['clientdetails']['email'] ?? ''),
            ],
        ];

        $method = $config->paymentMethod();
        if ($method !== 'auto') {
            $body['provider'] = $method;
        }

        return $body;
    }

    /**
     * URL-safe query-string append. Preserves any existing query component.
     *
     * @param array<string,string> $extra
     */
    private static function addQuery(string $url, array $extra): string
    {
        if ($extra === []) {
            return $url;
        }
        $glue = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $glue . http_build_query($extra);
    }
}
