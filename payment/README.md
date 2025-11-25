# Stripe Payment Integration - Quick Start

## 🚀 What You Need to Do

### 1. Get Stripe API Keys
1. Sign up at https://stripe.com
2. Go to Developers → API Keys
3. Copy your **test** keys

### 2. Configure the Backend
Edit `/payment/api/config.php`:
```php
// Line 13-14: Replace with your actual keys
define('STRIPE_TEST_SECRET_KEY', 'sk_test_YOUR_KEY_HERE');
define('STRIPE_TEST_PUBLISHABLE_KEY', 'pk_test_YOUR_KEY_HERE');

// Line 27-28: Update with your domain
define('SUCCESS_URL', 'https://yourdomain.com/payment/success.html?session_id={CHECKOUT_SESSION_ID}');
define('CANCEL_URL', 'https://yourdomain.com/payment/order.html?canceled=1');
```

### 3. Install Stripe PHP Library
On your server:
```bash
cd /path/to/website/payment/api
composer install
```

**No Composer?**
```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

### 4. Set File Permissions
```bash
chmod 666 payment/data/orders.json
chmod 755 payment/logs
```

### 5. Configure Webhook
1. In Stripe Dashboard: Developers → Webhooks → Add endpoint
2. URL: `https://yourdomain.com/payment/api/webhook.php`
3. Event: `checkout.session.completed`
4. Copy signing secret (starts with `whsec_...`)
5. Add to `config.php`:
```php
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_SECRET_HERE');
```

### 6. Test It!
1. Go to `/payment/order.html?id=ORD-20251125-001`
2. Click "💳 Pay with Card"
3. Use test card: `4242 4242 4242 4242`
4. Expiry: 12/34, CVC: 123
5. Complete payment
6. Check admin panel - should show as paid!

## 📁 Files Created
```
payment/
├── api/
│   ├── .htaccess (security)
│   ├── composer.json
│   ├── config.php ⚠️ ADD YOUR KEYS HERE
│   ├── create-checkout.php
│   ├── webhook.php
│   └── vendor/ (created by composer)
├── data/
│   └── orders.json (replaces orders.js)
├── logs/
│   └── webhook.log (created automatically)
├── order.html (updated)
└── success.html (new)
```

## ⚠️ Important Notes
- Start in **test mode** - use test keys
- Never commit API keys to Git
- Use HTTPS in production
- Complete Stripe account verification before going live

## 🐛 Troubleshooting
- **Webhook not working?** Check `/payment/logs/webhook.log`
- **Composer fails?** Try manual library installation
- **Orders not updating?** Check file permissions

## 📚 Full Documentation
See `walkthrough.md` for complete setup guide and troubleshooting.

## 🎯 Demo
Test order: `ORD-20251125-001`
