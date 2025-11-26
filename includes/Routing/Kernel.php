<?php
declare(strict_types=1);

namespace ARM\Routing;

final class Kernel
{
    private static bool $booted = false;

    /**
     * Boot every module once in the same order the WordPress plugin used.
     */
    public static function bootModules(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        $bootSequence = [
            ['ARM\\Install\\Activator', 'maybe_upgrade'],
            'ARM\\Admin\\Dashboard',
            'ARM\\Admin\\Menu',
            'ARM\\Admin\\Assets',
            'ARM\\Admin\\Customers',
            'ARM\\Admin\\Settings',
            'ARM\\Admin\\Services',
            'ARM\\Admin\\Income',
            'ARM\\Admin\\Expenses',
            'ARM\\Admin\\Purchases',
            'ARM\\Admin\\FinancialReports',
            'ARM\\Admin\\Vehicles',
            'ARM\\Admin\\Inventory',
            'ARM\\Admin\\InventoryAlerts',
            'ARM\\Admin\\WarrantyClaims',
            'ARM\\Admin\\Reminders',
            'ARM\\Customer\\WarrantyClaims',
            'ARM\\Appointments\\Admin',
            'ARM\\Appointments\\Admin_Availability',
            'ARM\\Inspections\\Admin',
            'ARM\\Public\\Assets',
            'ARM\\Public\\Shortcode_Form',
            'ARM\\Public\\Ajax_Submit',
            'ARM\\Public\\Customer_Dashboard',
            'ARM\\Public\\CustomerExport',
            'ARM\\Appointments\\Frontend',
            'ARM\\Appointments\\Ajax',
            'ARM\\Estimates\\Controller',
            'ARM\\Estimates\\PublicView',
            'ARM\\Estimates\\Ajax',
            'ARM\\Appointments\\Controller',
            'ARM\\Appointments\\Hooks_Make',
            'ARM\\Invoices\\Controller',
            'ARM\\Invoices\\PublicView',
            'ARM\\Links\\Shortlinks',
            'ARM\\Bundles\\Controller',
            'ARM\\Bundles\\Ajax',
            'ARM\\Integrations\\Payments_Stripe',
            'ARM\\Integrations\\Payments_PayPal',
            'ARM\\Integrations\\PartsTech',
            'ARM\\Integrations\\Zoho',
            'ARM\\Integrations\\Appointments_Make',
            'ARM\\PDF\\Generator',
            'ARM\\Audit\\Logger',
            'ARM\\TimeLogs\\Controller',
            'ARM\\Reminders\\Scheduler',
            'ARM\\Inspections\\Reports',
            'ARM\\Inspections\\PublicView',
            'ARM\\Credit\\Controller',
            'ARM\\Credit\\Frontend',
        ];

        foreach ($bootSequence as $entry) {
            if (is_array($entry)) {
                [$class, $method] = $entry;
                if (class_exists($class) && method_exists($class, $method)) {
                    $class::$method();
                }
                continue;
            }

            if (class_exists($entry) && method_exists($entry, 'boot')) {
                $entry::boot();
            }
        }
    }

    public static function dispatch(array $params = []): array
    {
        self::bootModules();

        return [
            'message' => 'ARM front controller initialized',
            'path' => $params['path'] ?? '/',
        ];
    }

    /**
     * Normalize controller responses to strings or JSON.
     */
    public static function renderResponse(mixed $response): void
    {
        if (is_array($response) || is_object($response)) {
            header('Content-Type: application/json');
            echo json_encode($response);
            return;
        }

        if (is_string($response)) {
            echo $response;
        }
    }
}
