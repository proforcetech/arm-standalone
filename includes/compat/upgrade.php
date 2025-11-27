<?php
/**
 * Lightweight fallback for WordPress upgrade helpers.
 */

if (!function_exists('arm_require_upgrade_file')) {
    function arm_require_upgrade_file(): bool
    {
        if (function_exists('dbDelta')) {
            return true;
        }

        $upgradePath = rtrim(ABSPATH, '/\\') . '/wp-admin/includes/upgrade.php';

        if (file_exists($upgradePath)) {
            require_once $upgradePath;
        } else {
            error_log('ARM: WordPress upgrade.php not found; using compatibility dbDelta stub.');

            if (!function_exists('dbDelta')) {
                function dbDelta($queries, $execute = true)
                {
                    global $db;

                    if (!$db || !method_exists($db, 'query')) {
                        return [];
                    }

                    $statements = is_array($queries) ? $queries : [$queries];
                    $results    = [];

                    foreach ($statements as $statement) {
                        if (!is_string($statement)) {
                            continue;
                        }

                        $statement = trim($statement);
                        if ($statement === '') {
                            continue;
                        }

                        $results[$statement] = $execute ? $db->query($statement) : true;
                    }

                    return $results;
                }
            }
        }

        return function_exists('dbDelta');
    }
}
