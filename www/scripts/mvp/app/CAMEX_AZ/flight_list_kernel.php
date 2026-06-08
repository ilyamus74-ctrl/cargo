<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Forwarder\Http\CamexSessionClient;
use App\Forwarder\Services\FlightListService;

/** @return list<array<string, mixed>> */
function camex_az_flight_list_parse_rows(string $html, string $pagePath = '/cadmin/usa/index.php?do=flight', int $limit = 0): array
{
    return FlightListService::parseRows($html, $pagePath, $limit);
}

function camex_az_flight_list_normalize_page_path(string $path): string
{
    return FlightListService::normalizePagePath($path);
}

function camex_az_flight_list_save_debug_html(string $debugDir, string $fileName, string $html): string
{
    return FlightListService::saveDebugHtml($debugDir, $fileName, $html);
}

function camex_az_flight_list_looks_like_login_page(string $html): bool
{
    return FlightListService::looksLikeLoginPage($html);
}

/**
 * @param array{repo_root:string,session_client:CamexSessionClient,connector_id:int,target_table:string,page_path:string,debug_dir?:string,dry_run?:bool,limit?:int} $params
 * @return array<string, mixed>
 */
function camex_az_sync_flight_list_kernel(array $params): array
{
    $repoRoot = trim((string)($params['repo_root'] ?? ''));
    $sessionClient = $params['session_client'] ?? null;
    if (!($sessionClient instanceof CamexSessionClient)) {
        return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'sync prerequisites are missing (CamexSessionClient)'];
    }

    $service = new FlightListService($repoRoot, $sessionClient);
    return $service->sync([
        'connector_id' => (int)($params['connector_id'] ?? 0),
        'target_table' => (string)($params['target_table'] ?? 'connector_camex_az_operation_flight_list'),
        'page_path' => (string)($params['page_path'] ?? '/cadmin/usa/index.php?do=flight'),
        'debug_dir' => (string)($params['debug_dir'] ?? ''),
        'dry_run' => !empty($params['dry_run']),
        'limit' => (int)($params['limit'] ?? 0),
    ]);
}
