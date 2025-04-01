<?php
// File: modules/gateways/wowpay.php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function wowpay_config() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'WowPay Payment Gateway'
        ],
        'base_url' => [
            'FriendlyName' => 'Base URL',
            'Type' => 'text',
            'Size' => '50',
            'Default' => 'https://wowpay.cfd',
            'Description' => 'WowPay API base URL'
        ],
        'appid' => [
            'FriendlyName' => 'App ID',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Your WowPay Application ID'
        ],
        'app_secret' => [
            'FriendlyName' => 'App Secret',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'Your WowPay Application Secret'
        ],
        'webhook_secret' => [
            'FriendlyName' => 'Webhook Secret',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'Webhook verification secret'
        ]
    ];
}

function wowpay_activate() {
    // Create database table when module is activated
    createWowPayTable();
    return [
        'status' => 'success',
        'description' => 'WowPay gateway activated successfully'
    ];
}

function wowpay_deactivate() {
    // Optional: Decide whether to keep or remove the table on deactivation
    return [
        'status' => 'success',
        'description' => 'WowPay gateway deactivated'
    ];
}

function createWowPayTable() {
    $query = "CREATE TABLE IF NOT EXISTS `mod_wowpay` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoiceid` INT NOT NULL,
        `transactionid` VARCHAR(255) NOT NULL,
        `created_at` DATETIME NOT NULL,
        INDEX `invoice_index` (`invoiceid`),
        UNIQUE `transaction_index` (`transactionid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    try {
        full_query($query);
    } catch (Exception $e) {
        logActivity("WowPay Module: Failed to create table - " . $e->getMessage());
    }
}

function wowpay_save($params) {
    // This hook runs after saving gateway configuration
    createWowPayTable();
    logActivity("WowPay Module: Configuration saved - table verified/created");
}

function wowpay_link($params) {
    // First ensure table exists (in case activation hook didn't run)
    createWowPayTable();
    
    // Gateway configuration parameters
    $baseUrl = rtrim($params['base_url'], '/');
    $appId = $params['appid'];
    $appSecret = $params['app_secret'];
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $callbackUrl = $systemUrl . '/modules/gateways/callback/wowpay_callback.php';
    
    // Generate unique order ID
    $orderId = 'WHMCS-' . $invoiceId . '-' . time();
    
    // Create signature
    $signature = md5($appId . $appSecret);
    
    // API endpoint
    $apiUrl = $baseUrl . '/api/payment';

    // Create payment request
    $postData = [
        'appid' => $appId,
        'app_secret' => $appSecret,
        'orderid' => $orderId,
        'amount' => number_format($amount, 2, '.', ''),
        'time' => time(),
        'sign' => $signature
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Server: True'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        logTransaction('WowPay', ['error' => 'API connection failed'], 'Error');
        return 'Error connecting to payment gateway: HTTP ' . $httpCode;
    }

    $responseData = json_decode($response, true);
    
    if ($responseData['status'] !== 'success') {
        logTransaction('WowPay', $responseData, 'Error');
        return 'Error processing payment: ' . ($responseData['message'] ?? 'Unknown error');
    }

    // Store transaction ID in local database
    insert_query('mod_wowpay', [
        'invoiceid' => $invoiceId,
        'transactionid' => $responseData['payment_id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Return payment form
    return '<form action="' . $responseData['url'] . '" method="GET">
        <input type="submit" value="Pay with WowPay" class="btn btn-primary"/>
    </form>';
}

// File: modules/gateways/callback/wowpay_callback.php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

function verifyWebhookSignature($secret) {
    $headers = getallheaders();
    $signature = $headers['X-WowPay-Signature'] ?? '';
    $timestamp = $headers['X-WowPay-Timestamp'] ?? '';
    $payload = file_get_contents('php://input');
    
    if (empty($signature)) {
        http_response_code(400);
        die('Missing signature header');
    }
    
    if (abs(time() - (int)$timestamp) > 300) {
        http_response_code(401);
        die('Expired request');
    }
    
    $expectedSignature = hash_hmac('sha256', $timestamp . $payload, $secret);
    
    if (!hash_equals($expectedSignature, $signature)) {
        http_response_code(403);
        die('Invalid signature');
    }
    
    return json_decode($payload, true);
}

try {
    $gatewayModule = 'wowpay';
    $gatewayParams = getGatewayVariables($gatewayModule);
    
    if (!$gatewayParams['type']) {
        http_response_code(404);
        die('Gateway not activated');
    }
    
    $webhookData = verifyWebhookSignature($gatewayParams['webhook_secret']);
    
    // Validate required fields
    $requiredFields = ['payment_id', 'status', 'amount', 'orderid'];
    foreach ($requiredFields as $field) {
        if (!isset($webhookData[$field])) {
            http_response_code(400);
            die("Missing required field: $field");
        }
    }
    
    // Get transaction from database
    $transaction = select_query('mod_wowpay', '*', [
        'transactionid' => $webhookData['payment_id']
    ]);
    
    if (!$transaction || !$transaction->num_rows) {
        http_response_code(404);
        die('Transaction not found');
    }
    
    $transactionData = mysql_fetch_assoc($transaction);
    $invoiceId = $transactionData['invoiceid'];
    
    // Check invoice exists
    $invoice = select_query('tblinvoices', '*', ['id' => $invoiceId]);
    
    if (!$invoice || !$invoice->num_rows) {
        http_response_code(404);
        die('Invoice not found');
    }
    
    // Process payment status
    $status = strtolower($webhookData['status']);
    switch ($status) {
        case 'success':
            addInvoicePayment(
                $invoiceId,
                $webhookData['payment_id'],
                $webhookData['amount'],
                '',
                'wowpay'
            );
            logTransaction('WowPay', $webhookData, 'Successful');
            break;
            
        case 'failed':
            update_query('tblinvoices', [
                'status' => 'Unpaid',
                'notes' => 'Payment failed: ' . ($webhookData['message'] ?? '')
            ], ['id' => $invoiceId]);
            logTransaction('WowPay', $webhookData, 'Failed');
            break;
            
        case 'pending':
           
