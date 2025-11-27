<?php
/**
 * Installer / Activator
 *
 * Creates core tables, seeds defaults, and invokes module installers.
 */
namespace ARM\Install;

use ARM\Database\Config;
use ARM\Database\ConnectionFactory;
use ARM\Migrations\MigrationRunner;
use ARM\Migrations\SeederRunner;

if (!defined('ABSPATH')) exit;

final class Activator {

    public static function activate() {
        global $db;

        if (!function_exists('arm_require_upgrade_file')) {
            require_once __DIR__ . '/../compat/upgrade.php';
        }

        if (!arm_require_upgrade_file()) {
            return;
        }

        if (!defined('ARM_RE_PATH')) {
            define('ARM_RE_PATH', plugin_dir_path(dirname(__FILE__, 2)));
        }

        self::require_modules();
        self::runMigrationsAndSeeds();

        if (class_exists('\\ARM\\Appointments\\Installer')) {
            \ARM\Appointments\Installer::maybe_upgrade_legacy_schema();
            \ARM\Appointments\Installer::install_tables();
        }
        if (class_exists('\\ARM\\Estimates\\Controller')) {
            \ARM\Estimates\Controller::install_tables();
        }
        if (class_exists('\\ARM\\Audit\\Logger')) {
            \ARM\Audit\Logger::install_tables();
        }
        if (class_exists('\\ARM\\TimeLogs\\Controller')) {
            \ARM\TimeLogs\Controller::install_tables();
        }
        if (class_exists('\\ARM\\PDF\\Controller')) {
            \ARM\PDF\Controller::install_tables();
        }
        if (class_exists('\\ARM\\Invoices\\Controller')) {
            \ARM\Invoices\Controller::install_tables();
        }
        if (class_exists('\\ARM\\Bundles\\Controller')) {
            \ARM\Bundles\Controller::install_tables();
        }
        \ARM\Accounting\Transactions::install_tables();
        if (class_exists('\\ARM\\Integrations\\Payments_Stripe')) {
            \ARM\Integrations\Payments_Stripe::install_tables();
        }
        if (class_exists('\\ARM\\Integrations\\Payments_PayPal')) {
            \ARM\Integrations\Payments_PayPal::install_tables();
        }
        if (class_exists('\\ARM\\Links\\Shortlinks')) {
            \ARM\Links\Shortlinks::install_tables();
            \ARM\Links\Shortlinks::add_rewrite_rules();
            flush_rewrite_rules();
        }
        if (class_exists('\\ARM\\Credit\\Installer')) {
            \ARM\Credit\Installer::install_tables();
        }

        if (defined('ARM_RE_VERSION')) {
            update_option('arm_re_version', ARM_RE_VERSION);
        }

    }

    public static function maybe_upgrade(): void
    {
        // Prevent running multiple times in the same request
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        if (!function_exists('get_option')) {
            return;
        }

        if (!defined('ARM_RE_PATH')) {
            define('ARM_RE_PATH', plugin_dir_path(dirname(__FILE__, 2)));
        }

        try {
            $installed_version = get_option('arm_re_version');
            if ($installed_version && defined('ARM_RE_VERSION') && version_compare($installed_version, ARM_RE_VERSION, '>=')) {
                return;
            }
        } catch (\Throwable $e) {
            // Database not available or error reading version - skip upgrade check
            error_log('ARM upgrade check failed: ' . $e->getMessage());
            return;
        }

        try {
            self::require_modules();
            self::runMigrationsAndSeeds();
        } catch (\Throwable $e) {
            // Log migration errors but don't crash the application
            error_log('ARM migration failed: ' . $e->getMessage());
            throw $e; // Re-throw so it's visible during initial setup
        }

        if (class_exists('\\ARM\\Appointments\\Installer')) {
            \ARM\Appointments\Installer::maybe_upgrade_legacy_schema();
            \ARM\Appointments\Installer::install_tables();
        }

        if (class_exists('\\ARM\\Estimates\\Controller')) {
            \ARM\Estimates\Controller::install_tables();
        }

        if (class_exists('\\ARM\\Inspections\\Installer')) {
            \ARM\Inspections\Installer::install_tables();
        }

        \ARM\Accounting\Transactions::install_tables();

        if (defined('ARM_RE_VERSION')) {
            update_option('arm_re_version', ARM_RE_VERSION);
        }
    }

    private static function runMigrationsAndSeeds(): void
    {
        $config = self::configFromEnvironment();
        $pdo    = ConnectionFactory::make($config);

        $migrationRunner = new MigrationRunner($pdo, $config->getPrefix(), $config->charsetCollate());
        $migrationRunner->runPending(ARM_RE_PATH . 'database/migrations');

        $seederRunner = new SeederRunner($pdo, $config->getPrefix(), $config->charsetCollate());
        $seederRunner->runPending(ARM_RE_PATH . 'database/seeders');
    }

    private static function configFromEnvironment(): Config
    {
        global $db;

        $env = $_ENV;

        if (defined('DB_HOST')) { $env['DB_HOST'] = DB_HOST; }
        if (defined('DB_PORT')) { $env['DB_PORT'] = DB_PORT; }
        if (defined('DB_NAME')) { $env['DB_NAME'] = DB_NAME; }
        if (defined('DB_USER')) { $env['DB_USER'] = DB_USER; }
        if (defined('DB_PASSWORD')) { $env['DB_PASSWORD'] = DB_PASSWORD; }
        if (defined('DB_CHARSET')) { $env['DB_CHARSET'] = DB_CHARSET; }
        if (defined('DB_COLLATE')) { $env['DB_COLLATE'] = DB_COLLATE; }

        if (isset($db->prefix)) {
            $env['DB_PREFIX'] = $db->prefix;
        }
        if (!empty($db->charset)) {
            $env['DB_CHARSET'] = $env['DB_CHARSET'] ?? $db->charset;
        }
        if (!empty($db->collate)) {
            $env['DB_COLLATE'] = $env['DB_COLLATE'] ?? $db->collate;
        }

        return Config::fromEnv($env);
    }

    private static function require_modules() {

        $map = [
            '\\ARM\\Appointments\\Installer' => 'includes/appointments/Installer.php',
            '\\ARM\\Estimates\\Controller' => 'includes/estimates/Controller.php',
            '\\ARM\\Invoices\\Controller'  => 'includes/invoices/Controller.php',
            '\\ARM\\Bundles\\Controller'   => 'includes/bundles/Controller.php',
            '\\ARM\\Audit\\Logger'     => 'includes/audit/Logger.php',
            '\\ARM\\TimeLogs\\Controller' => 'includes/timelogs/Controller.php',
            '\\ARM\\PDF\\Controller'       => 'includes/pdf/Controller.php',
            '\\ARM\\Integrations\\Payments_Stripe'  => 'includes/integrations/Payments_Stripe.php',
            '\\ARM\\Integrations\\Payments_PayPal'    => 'includes/integrations/Payments_PayPal.php',
            '\\ARM\\Links\\Shortlinks'      => 'includes/links/class-shortlinks.php',
            '\\ARM\\Inspections\\Installer'    => 'includes/inspections/Installer.php',
            '\\ARM\\Credit\\Installer'    => 'includes/credit/Installer.php',
        ];
        foreach ($map as $class => $rel) {
            if (!class_exists($class) && file_exists(ARM_RE_PATH . $rel)) {
                require_once ARM_RE_PATH . $rel;
            }
        }
    }

    public static function uninstall() {

    }
}
