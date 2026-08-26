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
     *   customer?: array<string,mixed>,
     *   metadata: array<string,mixed>
     * }
     */
    public static function build(array $params, Config $config): array
    {
        $invoiceId = (int) ($params['invoiceid'] ?? 0);
        $callback  = rtrim($config->callbackBaseUrl(), '/') . '/modules/gateways/callback/paybridgenp.php';
        $client    = is_array($params['clientdetails'] ?? null) ? $params['clientdetails'] : [];

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
                'client_id'      => (string) ($client['userid'] ?? ''),
                'client_email'   => (string) ($client['email'] ?? ''),
            ],
        ];

        $nonEmpty = static function ($value): bool {
            return trim((string) $value) !== '';
        };
        $customer = array_filter([
            'name'  => trim((string) ($client['firstname'] ?? '') . ' ' . (string) ($client['lastname'] ?? '')),
            'email' => (string) ($client['email'] ?? ''),
            'phone' => (string) ($client['phonenumber'] ?? ''),
        ], $nonEmpty);
        $address = array_filter([
            'line1'      => (string) ($client['address1'] ?? ''),
            'line2'      => (string) ($client['address2'] ?? ''),
            'city'       => (string) ($client['city'] ?? ''),
            'state'      => (string) ($client['state'] ?? ''),
            'postalCode' => (string) ($client['postcode'] ?? ''),
            'country'    => (string) ($client['countrycode'] ?? $client['country'] ?? ''),
        ], $nonEmpty);
        if (!empty($address['line1']) && !empty($address['city'])) {
            $customer['address'] = $address;
        }
        if ($customer !== []) {
            $body['customer'] = $customer;
        }

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
