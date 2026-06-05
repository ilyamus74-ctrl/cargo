<?php
declare(strict_types=1);

if (!function_exists('warehouse_forwarder_extract_numeric_client_id')) {
    function warehouse_forwarder_extract_numeric_client_id(string $receiverAddress): string
    {
        $value = strtoupper(trim($receiverAddress));
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value)) {
            return $value;
        }

        if (preg_match('/^C([0-9]+)$/', $value, $m) === 1) {
            return $m[1];
        }

        if (preg_match('/^AS([0-9]+)$/', $value, $m) === 1) {
            return $m[1];
        }

        return '';
    }
}

if (!function_exists('warehouse_forwarder_connector_key')) {
    function warehouse_forwarder_connector_key(string $forwarderName, string $countryCode): string
    {
        $f = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($forwarderName)) ?? '');
        $c = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($countryCode)) ?? '');
        return trim($f . '_' . $c, '_');
    }
}
