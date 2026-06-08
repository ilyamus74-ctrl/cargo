<?php

declare(strict_types=1);

namespace App\Forwarder\Services;

use App\Forwarder\Http\CamexSessionClient;
use mysqli;
use RuntimeException;

final class ClientLookupService
{
    public const CONNECTOR_KEY = 'camex_az';
    public const FORWARDER_NAME = 'CAMEX';
    public const COUNTRY_CODE = 'AZ';

    public function __construct(
        private string $repoRoot,
        private CamexSessionClient $sessionClient,
        private ?mysqli $db = null
    ) {
        $this->repoRoot = rtrim($this->repoRoot, '/');
    }

    public static function extractNumericClientId(string $clientId, string $receiverAddress = ''): string
    {
        $clientId = trim($clientId);
        if ($clientId !== '' && preg_match('/^(?:C|AS)?([0-9]+)$/iu', $clientId, $match) === 1) {
            return $match[1];
        }

        $receiverAddress = trim($receiverAddress);
        if ($receiverAddress !== '' && preg_match('/^(?:C|AS)?([0-9]+)$/iu', $receiverAddress, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    /**
     * @param array{connector_id?:int,client_id?:string,receiver_address?:string,write_cache?:bool,dry_run?:bool,debug_dir?:string} $params
     * @return array<string, mixed>
     */
    public function lookup(array $params): array
    {
        $clientId = self::extractNumericClientId((string)($params['client_id'] ?? ''), (string)($params['receiver_address'] ?? ''));
        if ($clientId === '') {
            return ['status' => 'no_client_id', 'message' => 'No numeric client id found'];
        }

        $connectorId = (int)($params['connector_id'] ?? 0);
        $writeCache = !empty($params['write_cache']);
        $dryRun = !empty($params['dry_run']);
        $debugDir = trim((string)($params['debug_dir'] ?? ''));
        $debugHtml = '';

        $path = '/cadmin/usa/view_user.php?userID=' . rawurlencode($clientId);
        $response = $this->sessionClient->requestWithSession('GET', $path, [], false);
        $httpStatus = (int)($response['status_code'] ?? 0);
        $body = (string)($response['body'] ?? '');
        $debugHtml = self::saveDebugHtml($debugDir, 'client_' . $clientId . '.html', $body);

        if (isset($response['auth']) && empty($response['auth']['ok'])) {
            return [
                'status' => 'error',
                'stage' => 'auth',
                'message' => (string)($response['error'] ?? $response['auth']['message'] ?? 'Authentication failed'),
                'client_id' => $clientId,
                'http_status' => $httpStatus,
                'debug_html' => $debugHtml,
            ];
        }

        if ($httpStatus !== 200 || empty($response['ok'])) {
            return [
                'status' => 'error',
                'stage' => 'http',
                'message' => (string)($response['error'] ?? 'CAMEX client detail request failed'),
                'client_id' => $clientId,
                'http_status' => $httpStatus,
                'debug_html' => $debugHtml,
            ];
        }

        if (self::looksLikeLoginPage($body)) {
            return [
                'status' => 'error',
                'stage' => 'auth',
                'message' => 'CAMEX client detail response looks like login page.',
                'client_id' => $clientId,
                'http_status' => $httpStatus,
                'debug_html' => $debugHtml,
            ];
        }

        if (stripos($body, 'user_info') === false && stripos($body, 'ID: C') === false) {
            return [
                'status' => 'not_found',
                'client_id' => $clientId,
                'http_status' => $httpStatus,
                'debug_html' => $debugHtml,
            ];
        }

        $parsed = self::parseClientDetailHtml($body);
        if ((string)($parsed['client_id'] ?? '') === '' || (string)($parsed['client_name'] ?? '') === '') {
            return [
                'status' => 'not_found',
                'client_id' => $clientId,
                'http_status' => $httpStatus,
                'debug_html' => $debugHtml,
            ];
        }

        $cache = ['write_cache' => $writeCache, 'action' => $writeCache ? 'disabled' : 'disabled'];
        if ($dryRun) {
            $cache = ['write_cache' => $writeCache, 'action' => 'dry_run'];
        } elseif ($writeCache) {
            $cache = ['write_cache' => true, 'action' => $this->upsertConnectorClient($parsed)];
        }

        return [
            'status' => 'ok',
            'connector' => 'CAMEX_AZ',
            'connector_id' => $connectorId,
            'connector_key' => self::CONNECTOR_KEY,
            'forwarder_name' => self::FORWARDER_NAME,
            'country_code' => self::COUNTRY_CODE,
            'source' => 'camex_view_user',
            'client_id' => (string)$parsed['client_id'],
            'client_name' => (string)$parsed['client_name'],
            'client_email' => (string)($parsed['client_email'] ?? ''),
            'client_address' => (string)($parsed['client_address'] ?? ''),
            'cache' => $cache,
            'debug_html' => $debugHtml,
        ];
    }

    /** @return array<string, mixed> */
    public static function parseClientDetailHtml(string $html): array
    {
        $text = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\R/u', $text) ?: [];
        $fields = [];

        foreach ($lines as $line) {
            $line = self::cleanText((string)$line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }
            foreach (['ID', 'სახელი', 'ელ-ფოსტა', 'მისამართი', 'მობილურის ნომერი'] as $label) {
                if (preg_match('/^' . preg_quote($label, '/') . '\s*:\s*(.*?)\s*$/u', $line, $match) === 1) {
                    $fields[$label] = self::cleanText((string)$match[1]);
                    break;
                }
            }
        }

        $rawId = (string)($fields['ID'] ?? '');
        $clientId = preg_match('/C?\s*([0-9]+)/iu', $rawId, $idMatch) === 1 ? (string)$idMatch[1] : '';
        $phoneMobile = (string)($fields['მობილურის ნომერი'] ?? '');

        return [
            'client_id' => $clientId,
            'client_name' => (string)($fields['სახელი'] ?? ''),
            'client_email' => (string)($fields['ელ-ფოსტა'] ?? ''),
            'client_address' => (string)($fields['მისამართი'] ?? ''),
            'phone_mobile' => $phoneMobile,
            'raw_json' => [
                'source' => 'camex_view_user',
                'user_id' => $clientId,
                'phone_mobile' => $phoneMobile,
            ],
        ];
    }

    public static function looksLikeLoginPage(string $html): bool
    {
        if ($html === '') {
            return false;
        }
        $hasPassword = preg_match('/<input\b[^>]*type\s*=\s*(?:"password"|\'password\'|password)\b/i', $html) === 1;
        return $hasPassword && (stripos($html, 'login.php') !== false || stripos($html, 'auth=do') !== false);
    }

    public static function saveDebugHtml(string $debugDir, string $fileName, string $html): string
    {
        if ($debugDir === '') {
            return '';
        }
        if (!is_dir($debugDir)) {
            @mkdir($debugDir, 0775, true);
        }
        if (!is_dir($debugDir)) {
            return '';
        }
        $path = rtrim($debugDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        @file_put_contents($path, $html);

        return $path;
    }

    /** @param array<string, mixed> $parsed */
    private function upsertConnectorClient(array $parsed): string
    {
        $db = $this->db ?? $this->loadDb();
        $db->set_charset('utf8mb4');
        $columns = $this->connectorClientColumns($db);

        foreach (['connector_key', 'forwarder_name', 'country_code', 'client_id', 'client_name'] as $required) {
            if (!isset($columns[$required])) {
                throw new RuntimeException('connector_clients column is missing: ' . $required);
            }
        }

        $clientId = (string)$parsed['client_id'];
        $existing = $this->findExistingClient($db, $clientId);
        $now = date('Y-m-d H:i:s');

        $values = [
            'connector_key' => self::CONNECTOR_KEY,
            'forwarder_name' => self::FORWARDER_NAME,
            'country_code' => self::COUNTRY_CODE,
            'client_id' => $clientId,
            'client_name' => (string)($parsed['client_name'] ?? ''),
            'client_email' => (string)($parsed['client_email'] ?? ''),
            'client_address' => (string)($parsed['client_address'] ?? ''),
        ];
        if (isset($columns['raw_json'])) {
            $values['raw_json'] = json_encode($parsed['raw_json'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        if ($existing !== null) {
            $sets = ['forwarder_name = ?'];
            $params = [self::FORWARDER_NAME];
            $types = 's';

            foreach (['client_name', 'client_email', 'client_address', 'raw_json'] as $column) {
                if (!isset($columns[$column])) {
                    continue;
                }
                $value = (string)($values[$column] ?? '');
                if ($value === '' && in_array($column, ['client_name', 'client_email', 'client_address'], true)) {
                    continue;
                }
                $sets[] = $column . ' = ?';
                $params[] = $value;
                $types .= 's';
            }
            if (isset($columns['last_seen_at'])) {
                $sets[] = 'last_seen_at = ?';
                $params[] = $now;
                $types .= 's';
            }
            if (isset($columns['updated_at'])) {
                $sets[] = 'updated_at = CURRENT_TIMESTAMP';
            }

            $params[] = (int)$existing['id'];
            $types .= 'i';
            $sql = 'UPDATE connector_clients SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Prepare connector_clients update failed: ' . $db->error);
            }
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new RuntimeException('Execute connector_clients update failed: ' . $error);
            }
            $affected = $stmt->affected_rows;
            $stmt->close();

            return $affected > 0 ? 'updated' : 'unchanged';
        }

        if (isset($columns['first_seen_at'])) {
            $values['first_seen_at'] = $now;
        }
        if (isset($columns['last_seen_at'])) {
            $values['last_seen_at'] = $now;
        }
        if (isset($columns['created_at'])) {
            $values['created_at'] = $now;
        }
        if (isset($columns['updated_at'])) {
            $values['updated_at'] = $now;
        }

        $insertColumns = array_values(array_filter(array_keys($values), static fn(string $column): bool => isset($columns[$column])));
        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
        $sql = 'INSERT INTO connector_clients (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare connector_clients insert failed: ' . $db->error);
        }
        $params = array_map(static fn(string $column): string => (string)$values[$column], $insertColumns);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Execute connector_clients insert failed: ' . $error);
        }
        $stmt->close();

        return 'inserted';
    }

    private function loadDb(): mysqli
    {
        global $dbcnx;
        $path = $this->repoRoot . '/configs/connectDB.php';
        if (!is_file($path)) {
            throw new RuntimeException('connectDB.php not found');
        }
        require_once $path;
        $db = $GLOBALS['dbcnx'] ?? ($dbcnx ?? null);
        if (!($db instanceof mysqli)) {
            throw new RuntimeException('Database connection $dbcnx is not available.');
        }
        $GLOBALS['dbcnx'] = $db;

        return $db;
    }

    /** @return array<string, bool> */
    private function connectorClientColumns(mysqli $db): array
    {
        $columns = [];
        $res = $db->query('SHOW COLUMNS FROM connector_clients');
        if (!$res) {
            throw new RuntimeException('SHOW COLUMNS connector_clients failed: ' . $db->error);
        }
        while ($row = $res->fetch_assoc()) {
            $columns[(string)$row['Field']] = true;
        }
        $res->free();

        return $columns;
    }

    /** @return array<string, mixed>|null */
    private function findExistingClient(mysqli $db, string $clientId): ?array
    {
        $stmt = $db->prepare('SELECT id FROM connector_clients WHERE connector_key = ? AND country_code = ? AND client_id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Prepare connector_clients existing lookup failed: ' . $db->error);
        }
        $connectorKey = self::CONNECTOR_KEY;
        $countryCode = self::COUNTRY_CODE;
        $stmt->bind_param('sss', $connectorKey, $countryCode, $clientId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Execute connector_clients existing lookup failed: ' . $error);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row : null;
    }

    private static function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }
}
