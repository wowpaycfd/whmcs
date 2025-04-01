# 🌟 WowPay WHMCS Payment Gateway Module

![WHMCS](https://img.shields.io/badge/WHMCS-Compatible-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/License-MIT-green)

A secure and feature-rich payment gateway module for WHMCS that integrates with WowPay's payment processing system, including automatic database setup and webhook handling.

## 🚀 Features

- 💳 Supports all major payment methods via WowPay
- 🔐 Secure HMAC-SHA256 webhook verification
- 📊 Automatic transaction tracking
- ⚡ Real-time payment status updates
- 🔄 Automatic database table creation
- 📝 Comprehensive logging
- 🛡️ Fraud prevention measures

## 📦 Installation

### 1️⃣ Clone or Download the Module

```bash
git clone https://github.com/wowpaycfd/whmcs.git
```

### 2️⃣ Copy Files to WHMCS

```bash
cp -r whmcs-wowpay-gateway/modules/gateways/* /path/to/whmcs/modules/gateways/
```

### 3️⃣ Set Proper Permissions

```bash
chmod 644 /path/to/whmcs/modules/gateways/wowpay.php
chmod 644 /path/to/whmcs/modules/gateways/callback/wowpay_callback.php
```

## 🔧 Configuration

1. Log in to your **WHMCS Admin Area**
2. Navigate to:
   ```
   Setup → Payments → Payment Gateways
   ```
3. Activate the **WowPay** gateway
4. Enter your credentials:
   - 🔑 **App ID**: Your WowPay application ID
   - 🔒 **App Secret**: Your WowPay application secret
   - 🛡️ **Webhook Secret**: Your webhook verification secret
   - 🌐 **Base URL**: `https://api.wowpay.example` (or your custom URL)

5. Click **Save Changes**

## 🌐 Webhook Setup

1. In your **WowPay Merchant Dashboard**:
2. Navigate to:
   ```
   Settings → Webhooks
   ```
3. Add a new webhook with:
   - **URL**: `https://yourdomain.com/modules/gateways/callback/wowpay_callback.php`
   - **Secret**: Same as configured in WHMCS
   - **Events**: Select all payment events

## 🧪 Testing

### Test Mode
1. Enable test mode in WowPay dashboard
2. Use test credentials:
   - Card: `4111 1111 1111 1111`
   - Expiry: Any future date
   - CVV: `123`

### Transaction Flow
1. Create a test invoice in WHMCS
2. Select WowPay as payment method
3. Complete payment on WowPay's checkout page
4. Verify webhook updates invoice status

## 🐛 Troubleshooting

### Common Issues
| Issue | Solution |
|-------|----------|
| Webhook not working | Verify secret matches in WHMCS and WowPay dashboard |
| Database table missing | Re-save gateway settings to trigger table creation |
| Signature errors | Check server time synchronization (NTP) |

### Viewing Logs
1. WHMCS Admin → Utilities → Logs → Gateway Log
2. Search for "WowPay" entries

## 📜 Database Schema

The module automatically creates this table:

```sql
CREATE TABLE `mod_wowpay` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoiceid` INT NOT NULL,
    `transactionid` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `status` VARCHAR(20) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `invoice_index` (`invoiceid`),
    UNIQUE `transaction_index` (`transactionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📧 Support

For support, please contact:
- 📧 Email: support@wowpay.cfd
- 🌐 Website: [https://wowpay.cfd/support](https://wowpay.cfd/support)

---

**Happy Processing!** 💰🚀
