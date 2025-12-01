<?php

declare(strict_types=1);

/**
 * Centralized meter configuration.
 *
 * This file provides a single structured configuration array for all meters,
 * replacing the legacy variable-variable config_metN.php files.
 *
 * Migration guide:
 * - Old: $METNAME1, $TYPE1, ${'GRAPH_MET'.$i}, etc.
 * - New: $meters[0]['name'], $meters[0]['type'], $meters[0]['graph'], etc.
 *
 * @example Accessing meter config:
 *   require_once __DIR__ . '/meters.php';
 *   foreach ($meters as $index => $meter) {
 *       echo $meter['name'] . ': ' . $meter['unit'];
 *   }
 */

if (!defined('checkaccess')) {
    die('Direct access not permitted');
}

/**
 * @var array<int, array{
 *   name: string,
 *   type: string,
 *   prod: int,
 *   phase: int,
 *   skip_monitoring: bool,
 *   id: string,
 *   command: string,
 *   unit: string,
 *   precision: int,
 *   passo: int,
 *   color: string,
 *   price: float,
 *   live_id: string,
 *   live_command: string,
 *   live_unit: string,
 *   email: string,
 *   poa_key: string,
 *   pou_key: string,
 *   telegram_token: string,
 *   telegram_chat_id: string,
 *   warn_conso_daily: int,
 *   no_resp_monitoring: bool,
 *   graph: int,
 *   fill: bool
 * }>
 */
$meters = [
    // Example Meter 1: Electricity consumption
    [
        'name'             => 'Conso',
        'type'             => 'Elect',      // Elect, Gas, Water, Sensor
        'prod'             => 2,            // 1=producer, 2=consumer
        'phase'            => 1,            // Phase number
        'skip_monitoring'  => false,
        'id'               => 'elect',
        'command'          => 'houseenergy -energy',
        'unit'             => 'Wh',
        'precision'        => 0,
        'passo'            => 100000,       // Counter rollover value
        'color'            => '962629',     // Hex color without #
        'price'            => 0.23,         // Price per unit
        'live_id'          => 'elect',
        'live_command'     => 'houseenergy -power',
        'live_unit'        => 'W',
        'email'            => '',
        'poa_key'          => '',
        'pou_key'          => '',
        'telegram_token'   => '',
        'telegram_chat_id' => '',
        'warn_conso_daily' => 15000,        // Daily consumption warning threshold
        'no_resp_monitoring' => true,
        'graph'            => 1,            // Graph group number (0 = hidden)
        'fill'             => false,        // Fill area under chart
    ],

    // Example Meter 2: Solar production
    [
        'name'             => 'Solar',
        'type'             => 'Elect',
        'prod'             => 1,            // Producer
        'phase'            => 1,
        'skip_monitoring'  => false,
        'id'               => 'solar',
        'command'          => 'solarenergy -energy',
        'unit'             => 'Wh',
        'precision'        => 0,
        'passo'            => 100000,
        'color'            => 'F7BE81',
        'price'            => 0.08,
        'live_id'          => 'solar',
        'live_command'     => 'solarenergy -power',
        'live_unit'        => 'W',
        'email'            => '',
        'poa_key'          => '',
        'pou_key'          => '',
        'telegram_token'   => '',
        'telegram_chat_id' => '',
        'warn_conso_daily' => 0,
        'no_resp_monitoring' => true,
        'graph'            => 1,
        'fill'             => true,
    ],

    // Example Meter 3: Temperature sensor
    [
        'name'             => 'Temp',
        'type'             => 'Sensor',
        'prod'             => 2,
        'phase'            => 1,
        'skip_monitoring'  => false,
        'id'               => 'temp',
        'command'          => 'tempsensor -read',
        'unit'             => '°C',
        'precision'        => 1,
        'passo'            => 0,
        'color'            => '3366CC',
        'price'            => 0,
        'live_id'          => 'temp',
        'live_command'     => 'tempsensor -live',
        'live_unit'        => '°C',
        'email'            => '',
        'poa_key'          => '',
        'pou_key'          => '',
        'telegram_token'   => '',
        'telegram_chat_id' => '',
        'warn_conso_daily' => 0,
        'no_resp_monitoring' => true,
        'graph'            => 2,
        'fill'             => false,
    ],
];

/**
 * Helper function to get meter by index (1-based for backward compatibility).
 *
 * @param int $index 1-based meter index
 * @return array|null Meter configuration or null if not found
 */
function getMeter(int $index): ?array
{
    global $meters;
    $arrayIndex = $index - 1;
    return $meters[$arrayIndex] ?? null;
}

/**
 * Helper function to get total number of meters.
 *
 * @return int Number of configured meters
 */
function getMeterCount(): int
{
    global $meters;
    return count($meters);
}
