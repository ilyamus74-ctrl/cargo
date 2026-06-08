<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Forwarder\Config\ConnectorConfigRepository;

/** @return array<string, string> */
function camex_az_connector_cli_args(array $argv): array
{
    return ConnectorConfigRepository::cliArgs($argv);
}

function camex_az_connector_path(string $path): string
{
    return ConnectorConfigRepository::path($path);
}

/** @return array<string, mixed> */
function camex_az_connector_decode_json_object($json): array
{
    return ConnectorConfigRepository::decodeJsonObject($json);
}

/** @param array<string, mixed> $data */
function camex_az_connector_nested_value(array $data, array $path, $default = null)
{
    return ConnectorConfigRepository::nestedValue($data, $path, $default);
}

/** @return array<string, mixed> */
function camex_az_connector_scenario_overrides(array $scenario): array
{
    return ConnectorConfigRepository::scenarioOverrides($scenario);
}

/** @return array<string, mixed> */
function camex_az_connector_cli_overrides(array $args): array
{
    return ConnectorConfigRepository::cliOverrides($args);
}

/** @return array<string, mixed> */
function camex_az_connector_row_overrides(array $row): array
{
    return ConnectorConfigRepository::rowOverrides($row);
}

/** @return array{type: string, value: string} */
function camex_az_connector_find_lookup_arg(array $args): array
{
    return ConnectorConfigRepository::findLookupArg($args);
}

/** @return array<string, mixed>|null */
function camex_az_connector_load_row(array $args, string $repoRoot): ?array
{
    return ConnectorConfigRepository::loadRow($args, $repoRoot);
}

/** @return array<string, mixed> */
function camex_az_connector_diagnostics(?array $row): array
{
    return ConnectorConfigRepository::diagnostics($row);
}

/** @return array<string, mixed> */
function camex_az_connector_build_forwarder_overrides(?array $connectorRow, array $args): array
{
    return ConnectorConfigRepository::buildForwarderOverrides($connectorRow, $args);
}
