<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function wowpay_config() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'WowPay'
        ],
        'base_url' => [
            'FriendlyName' => 'API Base URL',
            'Type' => 'text',
            'Default' => 'https://wowpay.cfd',
            'Description' => 'The base URL for WowPay API'
        ],
        'appid' => [
            'FriendlyName' => 'App ID',
            'Type' => 'text',
            'Size' => '50'
        ],
        'app_secret' => [
            'FriendlyName' => 'App Secret',
            'Type' => 'password',
            'Size' => '50'
        ],
        'webhook_secret' => [
            'FriendlyName' => 'Webhook Secret',
            'Type' => 'password',
            'Size' => '50'
        ]
    ];
}

function wowpay_activate() {
    try {
        $query = "CREATE TABLE IF NOT EXISTS `mod_wowpay` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoiceid` INT NOT NULL,
            `transactionid` VARCHAR(255) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `status` VARCHAR(20) NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            INDEX `invoice_index` (`invoiceid`),
            UNIQUE `transaction_index` (`transactionid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        full_query($query);
        return ['status' => 'success', 'description' => 'WowPay gateway activated successfully'];
    } catch (Exception $e) {
        logActivity("WowPay Activation Failed: " . $e->getMessage());
        return ['status' => 'error', 'description' => 'Failed to activate WowPay gateway'];
    }
}

function wowpay_link($params) {
    // Verify table exists
    wowpay_activate();
    
    $baseUrl = rtrim($params['base_url'], '/');
    $appId = $params['appid'];
    $appSecret = $params['app_secret'];
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $systemUrl = $params['systemurl'];
    
    $orderId = 'WHMCS-' . $invoiceId . '-' . time();
    $callbackUrl = $systemUrl . '/modules/gateways/callback/wowpay_callback.php';
    $returnUrl = $params['returnurl'];
    $signature = md5(strtolower($appId . $appSecret));
    
    $postData = [
        'appid' => $appId,
        'app_secret' => $appSecret,
        'orderid' => $orderId,
        'amount' => number_format($amount, 2, '.', ''),
        'callback_url' => $callbackUrl,
        'return_url' => $returnUrl,
        'sign' => $signature
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/payment');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Server: True'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        logTransaction('WowPay', ['error' => "HTTP $httpCode", 'response' => $response], 'Error');
        return 'Error connecting to payment gateway';
    }

    $responseData = json_decode($response, true);
    if ($responseData['status'] !== 'success') {
        logTransaction('WowPay', $responseData, 'Error');
        return 'Payment error: ' . ($responseData['message'] ?? 'Unknown error');
    }

    insert_query('mod_wowpay', [
        'invoiceid' => $invoiceId,
        'transactionid' => $responseData['payment_id'],
        'amount' => $amount,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    return '<form action="' . $responseData['url'] . '" method="GET">
        <input type="submit" value="Pay with WowPay" class="btn btn-primary"/>
    </form>';
}