<?php

declare(strict_types=1);

use ARM\Migrations\SeederInterface;
use PDO;

return new class implements SeederInterface {
    public function getId(): string
    {
        return '20240701002000_seed_settings';
    }

    public function run(PDO $pdo, string $prefix): void
    {
        $optionsTable = sprintf('`%soptions`', $prefix);
        $check = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
        );
        $check->execute(['table' => $prefix . 'options']);
        if ($check->rowCount() === 0) {
            return;
        }

        $defaults = [
            'arm_re_terms_html'           => '<h3>Terms & Conditions</h3><p><strong>Please read:</strong> Estimates are based on provided information and initial inspection; final pricing may vary after diagnostics.</p>',
            'arm_re_notify_email'         => $_ENV['ARM_RE_NOTIFY_EMAIL'] ?? ($_ENV['ADMIN_EMAIL'] ?? ''),
            'arm_re_labor_rate'           => '125',
            'arm_re_tax_rate'             => '0',
            'arm_re_tax_apply'            => 'parts_labor',
            'arm_re_callout_default'      => '0',
            'arm_re_mileage_rate_default' => '0',
        ];

        foreach ($defaults as $name => $value) {
            $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM $optionsTable WHERE option_name = :name");
            $existsStmt->execute(['name' => $name]);
            if ((int) $existsStmt->fetchColumn() > 0) {
                continue;
            }

            $insert = $pdo->prepare("INSERT INTO $optionsTable (option_name, option_value, autoload) VALUES (:name, :value, 'yes')");
            $insert->execute(['name' => $name, 'value' => $value]);
        }
    }
};
