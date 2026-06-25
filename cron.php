cat > cron.php << 'EOF'
#!/usr/bin/php
<?php
require_once 'config.php';

$pdo = getDB();
if (!$pdo) {
    error_log("Cron: Database connection failed");
    exit(1);
}

$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'auto_drain_enabled'");
$enabled = $stmt->fetchColumn();

if ($enabled !== '1') {
    error_log("Cron: Auto drain disabled");
    exit(0);
}

$stmt = $pdo->query("SELECT * FROM wallets WHERE is_active = 1");
$wallets = $stmt->fetchAll();

$drained = 0;
foreach ($wallets as $wallet) {
    $drained++;
}

error_log("Cron: Checked $drained wallets at " . date('Y-m-d H:i:s'));
echo "Cron executed at " . date('Y-m-d H:i:s') . "\n";
?>
EOF

chmod +x cron.php
