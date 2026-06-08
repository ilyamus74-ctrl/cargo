<?php

declare(strict_types=1);

namespace App\Forwarder\Config;

use mysqli;
use RuntimeException;

final class ConnectorConfigRepository
{
    /** @return array<string, string> */
    public static function cliArgs(array $argv): array
    {
        $args = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (!is_string($arg) || strncmp($arg, '--', 2) !== 0) {
                continue;
            }
            $pair = explode('=', substr($arg, 2), 2);
            $key = trim((string)$pair[0]);
            if ($key !== '') {
                $args[$key] = (string)($pair[1] ?? '1');
            }
        }

        return $args;
    }

    public static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        return $path[0] === '/' ? $path : '/' . $path;
    }

    /** @return array<string, mixed> */
    public static function decodeJsonObject($json): array
    {
        $json = trim((string)$json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    public static function nestedValue(array $data, array $path, $default = null)
    {
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /** @return array<string, mixed> */
    public static function scenarioOverrides(array $scenario): array
    {
        $overrides = [];
        $mapping = [
            'login_path' => ['paths', 'login'],
            'login_post_path' => ['paths', 'login_post'],
            'dashboard_path' => ['paths', 'dashboard'],
            'session_ttl_seconds' => ['session', 'ttl_seconds'],
            'timeout' => ['timeout_seconds'],
        ];

        foreach ($mapping as $overrideKey => $path) {
            $value = self::nestedValue($scenario, $path);
            if ($value !== null && $value !== '') {
                $overrides[$overrideKey] = $value;
            }
        }

        return $overrides;
    }

    /** @return array<string, mixed> */
    public static function cliOverrides(array $args): array
    {
        $mapping = [
            'base-url' => 'base_url',
            'base_url' => 'base_url',
            'http-auth-type' => 'http_auth_type',
            'http_auth_type' => 'http_auth_type',
            'http-auth-login' => 'http_auth_login',
            'http_auth_login' => 'http_auth_login',
            'http-auth-password' => 'http_auth_password',
            'http_auth_password' => 'http_auth_password',
            'login' => 'web_login',
            'password' => 'web_password',
            'login-path' => 'login_path',
            'login_path' => 'login_path',
            'login-post-path' => 'login_post_path',
            'login_post_path' => 'login_post_path',
            'dashboard-path' => 'dashboard_path',
            'dashboard_path' => 'dashboard_path',
            'session-file' => 'session_file',
            'session_file' => 'session_file',
            'session-ttl-seconds' => 'session_ttl_seconds',
            'session_ttl_seconds' => 'session_ttl_seconds',
            'insecure' => 'insecure',
            'timeout' => 'timeout',
        ];

        $overrides = [];
        foreach ($mapping as $argKey => $overrideKey) {
            if (!array_key_exists($argKey, $args)) {
                continue;
            }
            $value = (string)$args[$argKey];
            if (in_array($overrideKey, ['login_path', 'login_post_path', 'dashboard_path'], true)) {
                $value = self::path($value);
            }
            $overrides[$overrideKey] = $value;
        }

        if (array_key_exists('http-auth-type', $args) || array_key_exists('http_auth_type', $args)) {
            $rawType = (string)($args['http-auth-type'] ?? $args['http_auth_type'] ?? 'none');
            $overrides['http_auth_enabled'] = strtolower(trim($rawType)) !== 'none' ? '1' : '0';
        }

        return $overrides;
    }

    /** @return array<string, mixed> */
    public static function rowOverrides(array $row): array
    {
        $mapping = [
            'base_url' => 'base_url',
            'auth_username' => 'web_login',
            'auth_password' => 'web_password',
            'ssl_ignore' => 'insecure',
            'http_auth_enabled' => 'http_auth_enabled',
            'http_auth_type' => 'http_auth_type',
            'http_auth_username' => 'http_auth_login',
            'http_auth_password' => 'http_auth_password',
        ];

        $overrides = [];
        foreach ($mapping as $column => $overrideKey) {
            if (array_key_exists($column, $row) && $row[$column] !== null && (string)$row[$column] !== '') {
                $overrides[$overrideKey] = $row[$column];
            }
        }

        return $overrides;
    }

    /** @return array{type: string, value: string} */
    public static function findLookupArg(array $args): array
    {
        foreach (['connector-id', 'connector_id'] as $key) {
            if (array_key_exists($key, $args) && trim((string)$args[$key]) !== '') {
                return ['type' => 'id', 'value' => trim((string)$args[$key])];
            }
        }
        foreach (['connector-name', 'connector_name'] as $key) {
            if (array_key_exists($key, $args) && trim((string)$args[$key]) !== '') {
                return ['type' => 'name', 'value' => trim((string)$args[$key])];
            }
        }
        foreach (['connector-key', 'connector_key'] as $key) {
            if (array_key_exists($key, $args) && trim((string)$args[$key]) !== '') {
                return ['type' => 'name', 'value' => trim((string)$args[$key])];
            }
        }

        return ['type' => '', 'value' => ''];
    }

    /** @return array<string, mixed>|null */
    public static function loadRow(array $args, string $repoRoot): ?array
    {
        $lookup = self::findLookupArg($args);
        if ($lookup['type'] === '') {
            return null;
        }

        require_once rtrim($repoRoot, '/') . '/configs/connectDB.php';
        $db = $GLOBALS['dbcnx'] ?? ($dbcnx ?? null);
        if (!($db instanceof mysqli)) {
            throw new RuntimeException('Database connection $dbcnx is not available.');
        }
        $GLOBALS['dbcnx'] = $db;

        $columns = 'id, name, countries, system_type, base_url, auth_type, auth_username, auth_password, http_auth_enabled, http_auth_type, http_auth_username, http_auth_password, ssl_ignore, scenario_json';
        if ($lookup['type'] === 'id') {
            $stmt = $db->prepare('SELECT ' . $columns . ' FROM connectors WHERE id = ? AND is_active = 1 LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('Prepare connector lookup failed: ' . $db->error);
            }
            $id = (int)$lookup['value'];
            $stmt->bind_param('i', $id);
        } else {
            $stmt = $db->prepare('SELECT ' . $columns . ' FROM connectors WHERE name = ? AND is_active = 1 ORDER BY id ASC LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('Prepare connector lookup failed: ' . $db->error);
            }
            $name = (string)$lookup['value'];
            $stmt->bind_param('s', $name);
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Execute connector lookup failed: ' . $error);
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('Active connector not found for --connector-' . $lookup['type'] . '=' . $lookup['value']);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    public static function diagnostics(?array $row): array
    {
        if ($row === null) {
            return ['source' => 'cli'];
        }

        return [
            'source' => 'db',
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'countries' => (string)($row['countries'] ?? ''),
            'system_type' => (string)($row['system_type'] ?? ''),
            'auth_type' => (string)($row['auth_type'] ?? ''),
            'base_url' => (string)($row['base_url'] ?? ''),
            'http_auth_enabled' => filter_var($row['http_auth_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'http_auth_type' => (string)($row['http_auth_type'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    public static function buildForwarderOverrides(?array $connectorRow, array $args): array
    {
        $scenario = self::decodeJsonObject($connectorRow['scenario_json'] ?? '');
        $overrides = array_merge(
            [
                'login_path' => '/cadmin/usa/login.php',
                'login_post_path' => '/cadmin/usa/login.php?auth=do',
                'dashboard_path' => '/cadmin/usa/index.php?do=index',
                'session_file' => '/tmp/camex_az_cookie.txt',
                'session_ttl_seconds' => 3600,
                'timeout' => 30,
                'insecure' => '0',
                'http_auth_enabled' => '0',
                'http_auth_type' => 'none',
            ],
            self::scenarioOverrides($scenario),
            $connectorRow !== null ? self::rowOverrides($connectorRow) : [],
            self::cliOverrides($args)
        );

        $overrides['base_url'] = rtrim((string)($overrides['base_url'] ?? ''), '/');
        foreach (['login_path', 'login_post_path', 'dashboard_path'] as $pathKey) {
            $overrides[$pathKey] = self::path((string)($overrides[$pathKey] ?? '/'));
        }
        $overrides['timeout'] = max(1, (int)($overrides['timeout'] ?? 30));
        $overrides['session_ttl_seconds'] = max(60, (int)($overrides['session_ttl_seconds'] ?? 3600));
        $overrides['http_auth_type'] = strtolower(trim((string)($overrides['http_auth_type'] ?? 'none')));
        if (!in_array($overrides['http_auth_type'], ['basic', 'digest', 'none'], true)) {
            throw new RuntimeException('Invalid HTTP auth type. Expected basic, digest, or none.');
        }
        if ($overrides['http_auth_type'] === 'none') {
            $overrides['http_auth_enabled'] = '0';
        }

        return $overrides;
    }
}
