<?php

/**
 * JSON endpoint returning meters and locale tokens for client-side initialization.
 *
 * This endpoint provides the client-side JavaScript with all necessary
 * configuration data in a single request, including:
 * - Meter configurations (names, colors, units, etc.)
 * - Locale settings (currency, date format, separators)
 * - Application settings (poll interval, timezone)
 *
 * @package meterN
 */

declare(strict_types=1);

define('checkaccess', true);

// Load main configuration
require_once __DIR__ . '/../config/config_main.php';

// Set timezone and content type
date_default_timezone_set($DTZ);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Build meters array from individual config files
$metersConfig = [];

for ($i = 1; $i <= $NUMMETER; $i++) {
    $configFile = __DIR__ . "/../config/config_met{$i}.php";
    if (file_exists($configFile)) {
        include $configFile;

        // Get graph setting from layout if available
        $graphNum = 0;
        $fillMet = false;
        if (isset(${'GRAPH_MET' . $i})) {
            $graphNum = ${'GRAPH_MET' . $i};
        }
        if (isset(${'FILL_MET' . $i})) {
            $fillMet = ${'FILL_MET' . $i};
        }

        $metersConfig[] = [
            'index'     => $i,
            'name'      => ${'METNAME' . $i} ?? '',
            'type'      => ${'TYPE' . $i} ?? 'Elect',
            'prod'      => ${'PROD' . $i} ?? 2,
            'id'        => ${'ID' . $i} ?? '',
            'unit'      => ${'UNIT' . $i} ?? '',
            'liveUnit'  => ${'LIVEUNIT' . $i} ?? '',
            'color'     => ${'COLOR' . $i} ?? '000000',
            'precision' => ${'PRECI' . $i} ?? 0,
            'graph'     => $graphNum,
            'fill'      => $fillMet,
        ];
    }
}

// Build locale configuration
$locale = [
    'currency'      => $CURS ?? '€',
    'dateFormat'    => $DATEFORMAT ?? 'Y-m-d',
    'decimalPoint'  => $DPOINT ?? '.',
    'thousandsSep'  => $THSEP ?? ',',
    'timezone'      => $DTZ ?? 'UTC',
    'language'      => $LANG ?? 'English',
];

// Application settings
$settings = [
    'pollInterval' => 5000,  // 5 seconds in milliseconds
    'title'        => $TITLE ?? 'meterN',
    'subtitle'     => $SUBTITLE ?? '',
    'delay'        => ($DELAY ?? 20) * 1000,  // Convert to milliseconds
];

// Language tokens for client-side (load if language file exists)
$tokens = [];
$langFile = __DIR__ . '/../languages/' . ($LANG ?? 'English') . '.php';
if (file_exists($langFile)) {
    include $langFile;
    // Export common language variables if they exist
    $tokenVars = [
        'lgPOWER', 'lgENERGY', 'lgTODAY', 'lgYESTERDAY', 'lgLAST24H',
        'lgWEEK', 'lgMONTH', 'lgYEAR', 'lgTOTAL', 'lgNODATA',
        'lgLOADING', 'lgERROR', 'lgYESTERDAYTITLE'
    ];
    foreach ($tokenVars as $var) {
        if (isset($$var)) {
            $tokens[$var] = $$var;
        }
    }
}

// Build final response
$response = [
    'meters'   => $metersConfig,
    'locale'   => $locale,
    'settings' => $settings,
    'tokens'   => $tokens,
    'generated' => date('c'),
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
