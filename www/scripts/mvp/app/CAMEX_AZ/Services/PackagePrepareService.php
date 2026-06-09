<?php

declare(strict_types=1);

namespace App\Forwarder\Services;

use App\Forwarder\Http\CamexSessionClient;
use App\Forwarder\Logging\ForwarderLogger;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

final class PackagePrepareService
{
    public const DEFAULT_PAGE_PATH = '/cadmin/usa/index.php?do=newaddpre';

    public function __construct(
        private CamexSessionClient $client,
        private ForwarderLogger $logger
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function prepare(array $options): array
    {
        $tracking = trim((string)($options['tracking'] ?? ''));
        $debugDir = trim((string)($options['debug_dir'] ?? ''));
        $requestedMode = strtolower(trim((string)($options['prepare_mode'] ?? 'auto')));
        $warnings = [];

        if ($tracking === '') {
            return [
                'status' => 'error',
                'stage' => 'validation',
                'message' => 'Missing required --tracking.',
                'tracking' => $tracking,
            ];
        }

        if (!in_array($requestedMode, ['auto', 'client', 'pretrack'], true)) {
            return [
                'status' => 'error',
                'stage' => 'validation',
                'message' => 'Invalid --prepare-mode. Expected auto, client, or pretrack.',
                'tracking' => $tracking,
                'prepare_mode' => $requestedMode,
            ];
        }

        $clientIdInput = self::extractClientIdInput($options);
        $prepareMode = $requestedMode === 'auto'
            ? ($clientIdInput !== '' ? 'client' : 'pretrack')
            : $requestedMode;

        if ($prepareMode === 'client' && $clientIdInput === '') {
            return [
                'status' => 'error',
                'stage' => 'validation',
                'message' => 'Client prepare mode requires --client-id or a numeric id in --receiver-address.',
                'tracking' => $tracking,
                'prepare_mode' => $prepareMode,
            ];
        }

        if ((string)($options['dry_run'] ?? '1') !== '1') {
            $warnings[] = 'submit_not_implemented';
        }

        $pagePath = $prepareMode === 'client'
            ? self::DEFAULT_PAGE_PATH . '&code=' . rawurlencode($clientIdInput)
            : self::normalizePath((string)($options['page_path'] ?? self::DEFAULT_PAGE_PATH), self::DEFAULT_PAGE_PATH);

        if ($prepareMode === 'client') {
            $response = $this->client->requestWithSession('GET', $pagePath);
            $debugFileName = '01_newaddpre_client_form.html';
            $fetchStage = 'client_form_fetch';
        } else {
            $response = $this->client->requestWithSession('POST', $pagePath, [
                'pretrack' => $tracking,
                'pretrack_flg' => '1',
            ], true);
            $debugFileName = '01_newaddpre_pretrack.html';
            $fetchStage = 'pretrack_fetch';
        }

        $httpStatus = (int)($response['status_code'] ?? 0);
        $body = (string)($response['body'] ?? '');
        $debugHtml = self::saveDebugHtml($debugDir, $debugFileName, $body);

        if ($httpStatus !== 200 || empty($response['ok'])) {
            return [
                'status' => 'error',
                'stage' => $fetchStage,
                'message' => (string)($response['error'] ?? 'CAMEX newaddpre request failed.'),
                'tracking' => $tracking,
                'prepare_mode' => $prepareMode,
                'client_id_input' => $clientIdInput,
                'page_path' => $pagePath,
                'http_status' => $httpStatus,
                'debug_html' => $debugHtml,
            ];
        }

        if (self::looksLikeLoginPage($body)) {
            return [
                'status' => 'error',
                'stage' => $fetchStage,
                'message' => 'CAMEX newaddpre response looks like login page.',
                'tracking' => $tracking,
                'prepare_mode' => $prepareMode,
                'client_id_input' => $clientIdInput,
                'page_path' => $pagePath,
                'http_status' => $httpStatus,
                'debug_html' => $debugHtml,
            ];
        }

        if (!self::hasExpectedNewaddpreMarkers($body, $prepareMode)) {
            return [
                'status' => 'error',
                'stage' => 'parse',
                'message' => 'CAMEX newaddpre response does not contain expected form, user, or orders markers.',
                'tracking' => $tracking,
                'prepare_mode' => $prepareMode,
                'client_id_input' => $clientIdInput,
                'page_path' => $pagePath,
                'http_status' => $httpStatus,
                'debug_html' => $debugHtml,
            ];
        }

        $parsed = self::parsePretrackHtml($body, $tracking, $pagePath);
        $payloadPreview = self::buildPayloadPreview($parsed, $options, $warnings);
        $selected = self::buildSelected($payloadPreview, $parsed, $options);
        $detected = self::detectState($parsed, $tracking, $body);
        $formFound = is_array($parsed['form'] ?? null) && !empty($parsed['form']['found']);
        $clientId = (string)($parsed['client']['client_id'] ?? '');
        $canSubmit = $detected['state'] === 'ready_to_add' && $formFound && $clientId !== '' && (string)($payloadPreview['track'] ?? '') !== '';

        $result = [
            'status' => 'ok',
            'connector' => 'CAMEX_AZ',
            'connector_id' => (int)($options['connector_id'] ?? 0),
            'mode' => 'dry_run',
            'prepare_mode' => $prepareMode,
            'tracking' => $tracking,
            'client_id_input' => $clientIdInput,
            'page_path' => $pagePath,
            'http_status' => $httpStatus,
            'detected_state' => $detected['state'],
            'can_submit' => $canSubmit,
            'existing_tracking_found' => (bool)$detected['existing_tracking_found'],
            'client' => $parsed['client'],
            'form' => $parsed['form'],
            'selected' => $selected,
            'counts' => [
                'flight_options' => count($parsed['flight_options']),
                'box_options' => count($parsed['box_options']),
                'package_type_options' => count($parsed['package_type_options']),
                'orders' => count($parsed['orders']),
            ],
            'warnings' => array_values(array_unique($warnings)),
            'payload_preview' => $payloadPreview,
            'orders' => $parsed['orders'],
            'debug_html' => $debugHtml,
        ];

        if ($detected['detected_status'] !== '') {
            $result['detected_status'] = $detected['detected_status'];
        }

        $this->logger->info('CAMEX package prepare dry-run completed', [
            'tracking' => $tracking,
            'prepare_mode' => $prepareMode,
            'state' => $result['detected_state'],
            'can_submit' => $result['can_submit'],
        ]);

        return $result;
    }

    /** @return array<string, mixed> */
    public static function parsePretrackHtml(string $html, string $tracking, string $pagePath = self::DEFAULT_PAGE_PATH): array
    {
        $dom = self::loadDom($html);
        $xpath = new DOMXPath($dom);
        $ordForm = self::findOrderForm($xpath);
        $form = ['found' => false, 'action' => '', 'method' => 'POST'];
        $defaults = [];
        $flightOptions = [];
        $boxOptions = [];
        $packageTypeOptions = [];

        if ($ordForm instanceof DOMElement) {
            $action = html_entity_decode(trim($ordForm->getAttribute('action')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $method = strtoupper(trim($ordForm->getAttribute('method')) ?: 'POST');
            $form = [
                'found' => true,
                'action' => self::normalizeAction($action, $pagePath),
                'method' => $method,
            ];
            $defaults = self::extractFormDefaults($ordForm, $xpath);
            $flightOptions = self::extractSelectOptions($ordForm, $xpath, 'reisi');
            $boxOptions = self::extractSelectOptions($ordForm, $xpath, 'dim_storage');
            $packageTypeOptions = self::extractSelectOptions($ordForm, $xpath, 'package_type_id');
        }

        $client = self::extractClient($html, $xpath, $defaults, $form['action'], $pagePath);

        return [
            'client' => $client,
            'form' => $form,
            'flight_options' => $flightOptions,
            'box_options' => $boxOptions,
            'package_type_options' => $packageTypeOptions,
            'form_defaults' => $defaults,
            'orders' => self::parseOrders($xpath, $tracking),
        ];
    }

    /** @return array<string, string> */
    private static function buildPayloadPreview(array $parsed, array $options, array &$warnings): array
    {
        $payload = [];
        foreach (($parsed['form_defaults'] ?? []) as $name => $value) {
            $payload[(string)$name] = is_array($value) ? implode(',', array_map('strval', $value)) : (string)$value;
        }

        $map = [
            'flight_no' => 'reisi',
            'box_id' => 'dim_storage',
            'weight' => 'p_wona',
            'length' => 'X',
            'width' => 'Y',
            'height' => 'Z',
            'tracking' => 'track',
            'invoice_price' => 'shen',
            'invoice_currency' => 'invoice_ccy',
            'shop' => 'shop',
            'item_count' => 'item_count',
            'package_type_id' => 'package_type_id',
            'comment' => 'comment',
        ];
        foreach ($map as $optionKey => $fieldName) {
            if (array_key_exists($optionKey, $options)) {
                $payload[$fieldName] = (string)$options[$optionKey];
            }
        }

        if ((string)($options['parfume'] ?? '') === '1') {
            $payload['storage'] = '1';
        }

        $boxCode = trim((string)($options['box_code'] ?? ''));
        if ($boxCode !== '') {
            $box = self::findOptionByText((array)($parsed['box_options'] ?? []), $boxCode);
            if ($box !== null) {
                $payload['dim_storage'] = (string)$box['value'];
            } else {
                $warnings[] = 'box_code_not_found';
            }
        }

        if (array_key_exists('flight_no', $options) && trim((string)$options['flight_no']) !== '' && self::findOptionByValueOrText((array)($parsed['flight_options'] ?? []), (string)$options['flight_no']) === null) {
            $warnings[] = 'flight_no_not_found';
        }
        if (array_key_exists('box_id', $options) && trim((string)$options['box_id']) !== '' && self::findOptionByValue((array)($parsed['box_options'] ?? []), (string)$options['box_id']) === null) {
            $warnings[] = 'box_id_not_found';
        }
        $packageTypeId = trim((string)($options['package_type_id'] ?? ''));
        if ($packageTypeId !== '' && $packageTypeId !== '0' && self::findOptionByValue((array)($parsed['package_type_options'] ?? []), $packageTypeId) === null) {
            $warnings[] = 'package_type_id_not_found';
        }
        if ((string)($payload['package_type_id'] ?? '') === '0' || (string)($payload['package_type_id'] ?? '') === '') {
            $warnings[] = 'package_type_id_not_selected';
        }

        foreach (['reisi', 'dim_storage', 'name', 'last_name', 'p_wona', 'X', 'Y', 'Z', 'track', 'invoice_ccy', 'package_type_id', 'code', 'pdecid', 'currency_invprice', 'useu', 'ucity'] as $required) {
            if (!array_key_exists($required, $payload)) {
                $payload[$required] = '';
            }
        }

        return $payload;
    }

    /** @return array<string, string> */
    private static function buildSelected(array $payload, array $parsed, array $options): array
    {
        $boxCode = '';
        if ((string)($payload['dim_storage'] ?? '') !== '') {
            $box = self::findOptionByValue((array)($parsed['box_options'] ?? []), (string)$payload['dim_storage']);
            $boxCode = $box !== null ? (string)$box['text'] : '';
        }

        return [
            'flight_no' => (string)($payload['reisi'] ?? ''),
            'box_id' => (string)($payload['dim_storage'] ?? ''),
            'box_code' => $boxCode,
            'package_type_id' => (string)($payload['package_type_id'] ?? ''),
            'invoice_currency' => (string)($payload['invoice_ccy'] ?? ''),
        ];
    }

    /** @return array{state: string, existing_tracking_found: bool, detected_status: string} */
    private static function detectState(array $parsed, string $tracking, string $html): array
    {
        foreach ((array)($parsed['orders'] ?? []) as $order) {
            if (!self::sameTracking((string)($order['tracking'] ?? ''), $tracking)) {
                continue;
            }

            $line = mb_strtolower(
                ((string)($order['package_status'] ?? $order['status'] ?? '')) . ' '
                . ((string)($order['flight'] ?? '')) . ' '
                . ((string)($order['action_text'] ?? $order['action'] ?? '')),
                'UTF-8'
            );
            if (str_contains($line, 'declared') || str_contains($line, 'declaration') || str_contains($line, 'box 100') || str_contains($line, 'box100')) {
                return ['state' => 'declared_or_auto_box', 'existing_tracking_found' => true, 'detected_status' => self::cleanText($line)];
            }

            return ['state' => 'already_registered', 'existing_tracking_found' => true, 'detected_status' => (string)($order['package_status'] ?? $order['status'] ?? '')];
        }

        if (preg_match('/already\s+(registered|added)|exist(s|ing)?\s+track|tracking\s+already/i', $html) === 1) {
            return ['state' => 'already_registered', 'existing_tracking_found' => true, 'detected_status' => ''];
        }

        $formFound = !empty($parsed['form']['found']);
        $clientId = (string)($parsed['client']['client_id'] ?? '');
        if ($formFound && $clientId !== '') {
            return ['state' => 'ready_to_add', 'existing_tracking_found' => false, 'detected_status' => ''];
        }
        if (!$formFound || $clientId === '') {
            return ['state' => 'not_declared_or_client_missing', 'existing_tracking_found' => false, 'detected_status' => ''];
        }

        return ['state' => 'parse_error', 'existing_tracking_found' => false, 'detected_status' => ''];
    }

    private static function extractClientIdInput(array $options): string
    {
        $clientId = trim((string)($options['client_id'] ?? ''));
        if ($clientId !== '' && preg_match('/^[0-9]+$/', $clientId) === 1) {
            return $clientId;
        }

        $receiverAddress = trim((string)($options['receiver_address'] ?? ''));
        if ($receiverAddress !== '' && preg_match('/([0-9]+)/', $receiverAddress, $m) === 1) {
            return (string)$m[1];
        }

        return '';
    }

    private static function loadDom(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $dom;
    }

    private static function findOrderForm(DOMXPath $xpath): ?DOMElement
    {
        $queries = [
            '//form[@id="ord_form"]',
            '//form[@name="ord_form"]',
            '//form[.//input[@name="track"] and .//input[@name="code"]]',
            '//form[contains(@action, "function=track")]',
        ];
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes instanceof DOMNodeList && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement) {
                return $nodes->item(0);
            }
        }
        return null;
    }

    /** @return array<string, string> */
    private static function extractFormDefaults(DOMElement $form, DOMXPath $xpath): array
    {
        $defaults = [];
        $inputs = $xpath->query('.//input|.//textarea', $form);
        if ($inputs instanceof DOMNodeList) {
            foreach ($inputs as $input) {
                if (!$input instanceof DOMElement) {
                    continue;
                }
                $name = trim($input->getAttribute('name'));
                if ($name === '') {
                    continue;
                }
                $type = strtolower(trim($input->getAttribute('type')) ?: 'text');
                if (in_array($type, ['submit', 'button', 'image', 'reset'], true)) {
                    continue;
                }
                if (($type === 'checkbox' || $type === 'radio') && !$input->hasAttribute('checked')) {
                    continue;
                }
                $defaults[$name] = html_entity_decode($input->getAttribute('value'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        $selects = $xpath->query('.//select', $form);
        if ($selects instanceof DOMNodeList) {
            foreach ($selects as $select) {
                if (!$select instanceof DOMElement) {
                    continue;
                }
                $name = trim($select->getAttribute('name'));
                if ($name === '') {
                    continue;
                }
                $options = self::extractOptionsFromSelect($select, $xpath);
                $selected = null;
                foreach ($options as $option) {
                    if (!empty($option['selected'])) {
                        $selected = (string)$option['value'];
                        break;
                    }
                }
                if ($selected === null && $options !== []) {
                    $selected = (string)$options[0]['value'];
                }
                $defaults[$name] = (string)($selected ?? '');
            }
        }

        return $defaults;
    }

    /** @return list<array{value: string, text: string, selected: bool}> */
    private static function extractSelectOptions(DOMElement $form, DOMXPath $xpath, string $name): array
    {
        $quoted = self::xpathLiteral($name);
        $nodes = $xpath->query('.//select[@name=' . $quoted . ']', $form);
        if (!$nodes instanceof DOMNodeList || $nodes->length === 0 || !$nodes->item(0) instanceof DOMElement) {
            return [];
        }

        return self::extractOptionsFromSelect($nodes->item(0), $xpath);
    }

    /** @return list<array{value: string, text: string, selected: bool}> */
    private static function extractOptionsFromSelect(DOMElement $select, DOMXPath $xpath): array
    {
        $result = [];
        $options = $xpath->query('.//option', $select);
        if (!$options instanceof DOMNodeList) {
            return $result;
        }
        foreach ($options as $option) {
            if (!$option instanceof DOMElement) {
                continue;
            }
            $value = $option->hasAttribute('value') ? $option->getAttribute('value') : ($option->textContent ?? '');
            $result[] = [
                'value' => html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'text' => self::cleanText($option->textContent ?? ''),
                'selected' => $option->hasAttribute('selected'),
            ];
        }

        return $result;
    }

    /** @return array<string, string> */
    private static function extractClient(string $html, DOMXPath $xpath, array $defaults, string $formAction, string $pagePath): array
    {
        $userText = '';
        $nodes = $xpath->query('//*[@id="user_info"]');
        if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
            $userText = self::cleanText($nodes->item(0)?->textContent ?? '');
        }
        if ($userText === '') {
            $plain = self::cleanText(strip_tags(preg_replace('/<\s*br\s*\/?\s*>/iu', ' ', $html) ?? $html));
            if (preg_match('/User\s+Detailes\s*:\s*(.*?)(?:Orders|<form|$)/isu', $plain, $m) === 1) {
                $userText = self::cleanText((string)$m[1]);
            }
        }

        $clientId = '';
        if (preg_match('/\bid\s*=\s*([0-9]+)/iu', $userText, $m) === 1) {
            $clientId = (string)$m[1];
        } elseif ((string)($defaults['code'] ?? '') !== '') {
            $clientId = (string)$defaults['code'];
        } elseif (preg_match('/[?&]code=([0-9]+)/i', $formAction, $m) === 1) {
            $clientId = (string)$m[1];
        } elseif (preg_match('/[?&]code=([0-9]+)/i', $pagePath, $m) === 1) {
            $clientId = (string)$m[1];
        }

        $roomId = '';
        if (preg_match('/RoomID\s*:\s*([^\s(]+)/iu', $userText, $m) === 1) {
            $roomId = (string)$m[1];
        }

        $clientName = '';
        if (preg_match('/Name\s*:\s*(.*?)\s*Tariff\s*:/isu', $userText, $m) === 1) {
            $clientName = self::cleanText((string)$m[1]);
        }
        if ($clientName === '') {
            $clientName = self::cleanText(((string)($defaults['name'] ?? '')) . ' ' . ((string)($defaults['last_name'] ?? '')));
        }

        $tariff = '';
        if (preg_match('/Tariff\s*:\s*([0-9]+(?:[.,][0-9]+)?)/iu', $userText, $m) === 1) {
            $tariff = str_replace(',', '.', (string)$m[1]);
        }

        return [
            'client_id' => $clientId,
            'room_id' => $roomId,
            'client_name' => $clientName,
            'tariff' => $tariff,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function parseOrders(DOMXPath $xpath, string $tracking): array
    {
        return array_merge(
            self::parseOrdersFromColumnRes($xpath, $tracking),
            self::parseOrdersFromTables($xpath, $tracking)
        );
    }

    /** @return list<array<string, mixed>> */
    private static function parseOrdersFromColumnRes(DOMXPath $xpath, string $tracking): array
    {
        $orders = [];
        $headers = $xpath->query('//h3[contains(translate(normalize-space(.), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "orders")]');
        if (!$headers instanceof DOMNodeList) {
            return $orders;
        }

        foreach ($headers as $header) {
            if (!$header instanceof DOMElement) {
                continue;
            }
            $lists = $xpath->query('following-sibling::ul[contains(concat(" ", normalize-space(@class), " "), " column_res ")][1]', $header);
            if (!$lists instanceof DOMNodeList || $lists->length === 0 || !$lists->item(0) instanceof DOMElement) {
                continue;
            }
            $rows = $xpath->query('./li[position()>1]', $lists->item(0));
            if (!$rows instanceof DOMNodeList) {
                continue;
            }
            foreach ($rows as $row) {
                if (!$row instanceof DOMElement) {
                    continue;
                }
                $cells = [];
                $cellNodes = $xpath->query('./span', $row);
                if (!$cellNodes instanceof DOMNodeList || $cellNodes->length === 0) {
                    continue;
                }
                foreach ($cellNodes as $cell) {
                    $cells[] = self::cleanText($cell->textContent ?? '');
                }
                if (count($cells) < 2) {
                    continue;
                }
                $order = [
                    'number' => (string)($cells[0] ?? ''),
                    'tracking' => (string)($cells[1] ?? ''),
                    'package_status' => (string)($cells[2] ?? ''),
                    'status' => (string)($cells[2] ?? ''),
                    'weight' => (string)($cells[3] ?? ''),
                    'price' => (string)($cells[4] ?? ''),
                    'decl_date' => (string)($cells[5] ?? ''),
                    'flight' => (string)($cells[6] ?? ''),
                    'date' => (string)($cells[7] ?? ''),
                    'action' => (string)($cells[8] ?? ''),
                    'action_text' => (string)($cells[8] ?? ''),
                ];
                $order['existing_tracking_found'] = self::sameTracking($order['tracking'], $tracking);
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /** @return list<array<string, mixed>> */
    private static function parseOrdersFromTables(DOMXPath $xpath, string $tracking): array
    {
        $orders = [];
        $tables = $xpath->query('//table');
        if (!$tables instanceof DOMNodeList) {
            return $orders;
        }
        foreach ($tables as $table) {
            if (!$table instanceof DOMElement) {
                continue;
            }
            $headers = [];
            $headerNodes = $xpath->query('.//tr[1]/*[self::th or self::td]', $table);
            if ($headerNodes instanceof DOMNodeList) {
                foreach ($headerNodes as $node) {
                    $headers[] = mb_strtolower(self::cleanText($node->textContent ?? ''), 'UTF-8');
                }
            }
            $headerText = implode(' ', $headers);
            if (!str_contains($headerText, 'tracking') || (!str_contains($headerText, 'package status') && !str_contains($headerText, 'status'))) {
                continue;
            }

            $rows = $xpath->query('.//tr[position()>1]', $table);
            if (!$rows instanceof DOMNodeList) {
                continue;
            }
            foreach ($rows as $row) {
                if (!$row instanceof DOMElement) {
                    continue;
                }
                $cells = [];
                $cellNodes = $xpath->query('./*[self::td or self::th]', $row);
                if (!$cellNodes instanceof DOMNodeList || $cellNodes->length === 0) {
                    continue;
                }
                foreach ($cellNodes as $cell) {
                    $cells[] = self::cleanText($cell->textContent ?? '');
                }
                if (count($cells) < 2) {
                    continue;
                }
                $order = [
                    'number' => (string)($cells[0] ?? ''),
                    'tracking' => (string)($cells[1] ?? ''),
                    'package_status' => (string)($cells[2] ?? ''),
                    'status' => (string)($cells[2] ?? ''),
                    'weight' => (string)($cells[3] ?? ''),
                    'price' => (string)($cells[4] ?? ''),
                    'decl_date' => (string)($cells[5] ?? ''),
                    'flight' => (string)($cells[6] ?? ''),
                    'date' => (string)($cells[7] ?? ''),
                    'action' => (string)($cells[8] ?? ''),
                    'action_text' => (string)($cells[8] ?? ''),
                ];
                $order['existing_tracking_found'] = self::sameTracking($order['tracking'], $tracking);
                $orders[] = $order;
            }
        }

        return $orders;
    }

    private static function normalizeAction(string $action, string $pagePath): string
    {
        $action = trim($action);
        if ($action === '') {
            return self::normalizePath($pagePath, self::DEFAULT_PAGE_PATH);
        }
        if (preg_match('#^https?://#i', $action) === 1) {
            $path = (string)(parse_url($action, PHP_URL_PATH) ?: '/');
            $query = parse_url($action, PHP_URL_QUERY);
            return self::normalizePath($path . (is_string($query) && $query !== '' ? '?' . $query : ''), self::DEFAULT_PAGE_PATH);
        }
        $basePath = explode('?', self::normalizePath($pagePath, self::DEFAULT_PAGE_PATH), 2)[0];
        if ($action[0] === '?') {
            return $basePath . $action;
        }
        if ($action[0] === '/') {
            return self::normalizePath($action, self::DEFAULT_PAGE_PATH);
        }
        $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        return ($dir === '' ? '' : $dir) . '/' . $action;
    }

    private static function normalizePath(string $path, string $default): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = $default;
        }
        return $path[0] === '/' ? $path : '/' . $path;
    }

    private static function saveDebugHtml(string $debugDir, string $fileName, string $html): string
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

    private static function looksLikeLoginPage(string $html): bool
    {
        if ($html === '') {
            return false;
        }
        $hasPassword = preg_match('/<input\b[^>]*type\s*=\s*(?:"password"|\'password\'|password)\b/i', $html) === 1;
        return $hasPassword && (stripos($html, 'login.php') !== false || stripos($html, 'auth=do') !== false);
    }

    private static function hasExpectedNewaddpreMarkers(string $html, string $prepareMode): bool
    {
        if ($prepareMode === 'client') {
            return stripos($html, 'ord_form') !== false
                || stripos($html, 'User Detailes') !== false
                || stripos($html, 'show_add') !== false;
        }

        return stripos($html, 'action') !== false
            || stripos($html, 'User Detailes') !== false
            || stripos($html, 'show_add') !== false
            || stripos($html, 'Orders') !== false
            || stripos($html, 'ord_form') !== false;
    }

    /** @param list<array<string, mixed>> $options */
    private static function findOptionByText(array $options, string $text): ?array
    {
        foreach ($options as $option) {
            if (strcasecmp(self::cleanText((string)($option['text'] ?? '')), self::cleanText($text)) === 0) {
                return $option;
            }
        }
        return null;
    }

    /** @param list<array<string, mixed>> $options */
    private static function findOptionByValue(array $options, string $value): ?array
    {
        foreach ($options as $option) {
            if ((string)($option['value'] ?? '') === $value) {
                return $option;
            }
        }
        return null;
    }

    /** @param list<array<string, mixed>> $options */
    private static function findOptionByValueOrText(array $options, string $value): ?array
    {
        return self::findOptionByValue($options, $value) ?? self::findOptionByText($options, $value);
    }

    private static function sameTracking(string $left, string $right): bool
    {
        return mb_strtolower(trim($left), 'UTF-8') === mb_strtolower(trim($right), 'UTF-8');
    }

    private static function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00a0}/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private static function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }
        $parts = explode("'", $value);
        return "concat('" . implode("', \"'\", '", $parts) . "')";
    }
}