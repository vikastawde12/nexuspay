<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Invalid password';
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Admin Login</title>
    <style>
        body { background: #0a0f1e; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial; margin: 0; }
        .login-box { background: rgba(18,25,45,0.9); padding: 40px; border-radius: 20px; border: 1px solid rgba(59,130,246,0.3); max-width: 400px; width: 100%; }
        .login-box h1 { color: #fff; text-align: center; }
        .login-box input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 10px; border: 1px solid rgba(59,130,246,0.3); background: rgba(10,15,27,0.8); color: #fff; }
        .login-box button { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; }
        .error { color: #ef4444; text-align: center; }
    </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Admin Login</h1>
            <?php if (isset($error)) echo '<div class="error">' . $error . '</div>'; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Enter Password" required>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo = getDB();
if (!$pdo) die("Database connection failed!");

$walletCount = $pdo->query("SELECT COUNT(*) FROM wallets")->fetchColumn();
$txCount = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$recentTxs = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 10")->fetchAll();
$wallets = $pdo->query("SELECT * FROM wallets ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>NexusPay Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: #f1f5f9; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: rgba(18,25,45,0.7); border-radius: 20px; border: 1px solid rgba(59,130,246,0.2); margin-bottom: 30px; }
        .header h1 { font-size: 28px; }
        .header a { color: #ef4444; text-decoration: none; padding: 8px 20px; border: 1px solid #ef4444; border-radius: 10px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: rgba(18,25,45,0.7); padding: 20px; border-radius: 16px; border: 1px solid rgba(59,130,246,0.2); text-align: center; }
        .stat-card .number { font-size: 32px; font-weight: 700; color: #3b82f6; }
        .stat-card .label { font-size: 12px; color: #6c86a3; }
        .card { background: rgba(18,25,45,0.7); border-radius: 16px; border: 1px solid rgba(59,130,246,0.2); padding: 20px; margin-bottom: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(59,130,246,0.1); }
        th { color: #6c86a3; }
        .status-success { color: #22c55e; }
        .status-pending { color: #f59e0b; }
        .status-failed { color: #ef4444; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; }
        .badge-active { background: rgba(34,197,94,0.2); color: #22c55e; }
        .badge-inactive { background: rgba(239,68,68,0.2); color: #ef4444; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-crown"></i> NexusPay Admin</h1>
        <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <?php if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; } ?>

    <div class="stats">
        <div class="stat-card"><div class="number"><?php echo $walletCount; ?></div><div class="label">Total Wallets</div></div>
        <div class="stat-card"><div class="number"><?php echo $txCount; ?></div><div class="label">Transactions</div></div>
        <div class="stat-card"><div class="number"><?php echo date('H:i:s'); ?></div><div class="label">System Time</div></div>
    </div>

    <div class="card">
        <h2>Wallets</h2>
        <table>
            <thead><tr><th>Address</th><th>Victim</th><th>Balance</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($wallets as $wallet): ?>
                <tr>
                    <td><?php echo substr($wallet['wallet_address'], 0, 15) . '...'; ?></td>
                    <td><?php echo $wallet['victim_address'] ? substr($wallet['victim_address'], 0, 15) . '...' : '-'; ?></td>
                    <td><?php echo number_format($wallet['usdt_balance'] ?? 0, 2); ?></td>
                    <td><span class="badge <?php echo $wallet['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $wallet['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Recent Transactions</h2>
        <table>
            <thead><tr><th>Hash</th><th>Amount</th><th>From</th><th>To</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($recentTxs as $tx): ?>
                <tr>
                    <td><?php echo substr($tx['tx_hash'], 0, 20) . '...'; ?></td>
                    <td><?php echo number_format($tx['amount'], 2); ?> USDT</td>
                    <td><?php echo substr($tx['from_address'], 0, 12) . '...'; ?></td>
                    <td><?php echo substr($tx['to_address'], 0, 12) . '...'; ?></td>
                    <td><span class="status-<?php echo $tx['status']; ?>"><?php echo ucfirst($tx['status']); ?></span></td>
                    <td><?php echo date('H:i:s', strtotime($tx['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

