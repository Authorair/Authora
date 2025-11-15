# راهنمای اضافه کردن درایور پیامکی جدید به Authora

این مستند راهنمای کاملی برای توسعه‌دهندگان جهت اضافه کردن درایور پیامکی جدید به پلاگین Authora ارائه می‌دهد.

## ساختار کلی درایورها

درایورهای پیامکی در پوشه `drivers/` قرار دارند و هر درایور باید اینترفیس `AuthoraSmsDriverInterface` را پیاده‌سازی کند.

### ساختار پوشه‌ها:
```
drivers/
├── SmsDriverInterface.php      # اینترفیس اصلی
├── SmsManager.php             # مدیر درایورها
├── Smsname/                   # پوشه درایور جدید
│   └── smsname.php            # کلاس درایور
└── ...
```

## مراحل اضافه کردن درایور جدید

### مرحله 1: ایجاد پوشه درایور

ابتدا پوشه‌ای با نام شرکت پیامکی خود در مسیر `drivers/` ایجاد کنید:

```bash
mkdir drivers/smsname
```

### مرحله 2: ایجاد کلاس درایور

فایل PHP درایور خود را با ساختار زیر ایجاد کنید:

```php
<?php

defined('ABSPATH') || exit;

require_once(__DIR__ . '/../SmsDriverInterface.php');

class AuthoraSmsNameDriver implements AuthoraSmsDriverInterface {
    
    protected $apiKey;
    protected $templateId;
    protected $senderNumber;
    protected $baseUrl;

    public function __construct($apiKey = null, $templateId = null, $senderNumber = null) {
        // Initial settings - you can use WordPress options
        $this->apiKey = $apiKey ?: get_option('authora_smsname_api_key');
        $this->templateId = $templateId ?: get_option('authora_smsname_template_id');
        $this->senderNumber = $senderNumber ?: get_option('authora_smsname_sender_number');
        $this->baseUrl = 'https://api.smsname.com/send'; // Company API URL
    }

    public function sendVerifyCode($mobile, $code) {
        // Implementation of verification code sending
        return $this->sendSms($mobile, $code);
    }

    private function sendSms($mobile, $code) {
        // SMS sending logic based on company API
        // ...
    }
}
```

## الزامات پیاده‌سازی

### 1. اینترفیس AuthoraSmsDriverInterface

هر درایور باید متد `sendVerifyCode($mobile, $code)` را پیاده‌سازی کند:

```php
public function sendVerifyCode($mobile, $code);
```

### 2. مدیریت خطاها

درایور باید در صورت بروز خطا، شیء `WP_Error` برگرداند:

```php
if ($error_occurred) {
    return new WP_Error('error_code', 'Error message in English');
}
```

### 3. پاسخ موفق

در صورت موفقیت، آرایه‌ای با اطلاعات مربوطه برگردانید:

```php
return [
    'success' => true,
    'message' => 'OTP code sent successfully',
    'data' => $response_data // optional
];
```

## نمونه کامل درایور

```php
<?php

defined('ABSPATH') || exit;

require_once(__DIR__ . '/../SmsDriverInterface.php');

class AuthoraSampleProviderDriver implements AuthoraSmsDriverInterface {
    
    protected $apiKey;
    protected $templateId;
    protected $senderNumber;
    protected $baseUrl = 'https://api.sampleprovider.com/v1/send';

    public function __construct($apiKey = null, $templateId = null, $senderNumber = null) {
        $this->apiKey = $apiKey ?: get_option('authora_sampleprovider_api_key');
        $this->templateId = $templateId ?: get_option('authora_sampleprovider_template_id');
        $this->senderNumber = $senderNumber ?: get_option('authora_sampleprovider_sender_number');
    }

    public function sendVerifyCode($mobile, $code) {
        // Input validation
        if (empty($this->apiKey) || empty($this->templateId)) {
            return new WP_Error('config_error', 'API configuration is incomplete');
        }

        // Format mobile number
        $mobile = $this->formatMobile($mobile);

        // Prepare parameters
        $params = [
            'api_key' => $this->apiKey,
            'template_id' => $this->templateId,
            'mobile' => $mobile,
            'parameters' => [
                'code' => $code,
                'domain' => sanitize_text_field($_SERVER['HTTP_HOST'] ?? '')
            ]
        ];

        // Send request
        $response = wp_remote_post($this->baseUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'body' => json_encode($params),
            'timeout' => 30
        ]);

        // Check connection errors
        if (is_wp_error($response)) {
            error_log('SampleProvider Connection Error: ' . $response->get_error_message());
            return new WP_Error('sms_connection_error', 'Connection problem with SMS server');
        }

        // Process response
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SampleProvider JSON Error: ' . json_last_error_msg());
            return new WP_Error('sms_json_error', 'Error processing server response');
        }

        // Check response status
        if (!isset($result['status']) || $result['status'] !== 'success') {
            $error_message = $result['message'] ?? 'Unknown error';
            error_log('SampleProvider API Error: ' . print_r($result, true));
            return new WP_Error('sms_send_error', 'SMS sending failed: ' . $error_message);
        }

        return [
            'success' => true,
            'message' => 'Verification code sent successfully',
            'message_id' => $result['message_id'] ?? null
        ];
    }

    private function formatMobile($mobile) {
        // Remove unnecessary characters
        $mobile = preg_replace('/[^0-9+]/', '', $mobile);
        
        // Convert Iranian format to international
        if (strpos($mobile, '0') === 0) {
            $mobile = '+98' . substr($mobile, 1);
        } elseif (strpos($mobile, '98') === 0) {
            $mobile = '+' . $mobile;
        } elseif (strpos($mobile, '+98') !== 0) {
            $mobile = '+98' . $mobile;
        }

        return $mobile;
    }
}
```

## نکات مهم

### 1. امنیت
- همیشه از `defined('ABSPATH') || exit;` در ابتدای فایل استفاده کنید
- از `sanitize_text_field()` برای پاکسازی ورودی‌ها استفاده کنید
- API Key و اطلاعات حساس را در WordPress options ذخیره کنید

### 2. مدیریت خطا
- از `error_log()` برای ثبت خطاها استفاده کنید
- پیام‌های خطا را به فارسی ارائه دهید
- خطاهای مختلف را با کدهای مناسب مشخص کنید

### 3. تست و دیباگ
- پاسخ‌های API را در لاگ ثبت کنید
- timeout مناسب (30 ثانیه) تنظیم کنید
- JSON response را اعتبارسنجی کنید

### 4. فرمت شماره موبایل
- شماره‌های ایرانی را به فرمت بین‌المللی (+98) تبدیل کنید
- کاراکترهای غیرضروری را حذف کنید

## استفاده از درایور

پس از ایجاد درایور، می‌توانید آن را به صورت زیر استفاده کنید:

```php
// Load driver
require_once(AUTHORA_PLUGIN_PATH . 'drivers/smsname/smsname.php');

// Create driver instance
$driver = new AuthoraSmsNameDriver($api_key, $template_id, $sender_number);

// Set driver in manager
$smsManager = AuthoraSmsManager::getInstance();
$smsManager->setDriver($driver);

// Send verification code
$result = $smsManager->sendVerifyCode($mobile, $code);

if (is_wp_error($result)) {
    // Handle error
    echo $result->get_error_message();
} else {
    // Success
    echo $result['message'];
}
```

## تنظیمات WordPress

برای ذخیره تنظیمات درایور در WordPress:

```php
// Save settings
update_option('authora_smsname_api_key', $api_key);
update_option('authora_smsname_template_id', $template_id);
update_option('authora_smsname_sender_number', $sender_number);

// Read settings
$api_key = get_option('authora_smsname_api_key');
$template_id = get_option('authora_smsname_template_id');
$sender_number = get_option('authora_smsname_sender_number');
```

## اضافه کردن درایور به پنل تنظیمات Authora

برای اینکه درایور جدید در پنل ادمین Authora نمایش داده شود، باید تغییرات زیر را اعمال کنید:

### مرحله 1: اضافه کردن تنظیمات به `admin/manager.php`

#### 1.1. ثبت تنظیمات جدید در تابع `authora_register_sms_settings()`:

```php
// smsname - add to the end of function
register_setting('authora_sms_settings', 'authora_smsname_api_key', 'sanitize_text_field');
register_setting('authora_sms_settings', 'authora_smsname_template_id', 'sanitize_text_field');
register_setting('authora_sms_settings', 'authora_smsname_sender_number', 'sanitize_text_field');
```

#### 1.2. اضافه کردن منطق ذخیره‌سازی در همان تابع:

```php
// smsname - add to save section
if (isset($_POST['authora_smsname_api_key'])) {
    update_option('authora_smsname_api_key', sanitize_text_field($_POST['authora_smsname_api_key']));
}
if (isset($_POST['authora_smsname_template_id'])) {
    update_option('authora_smsname_template_id', sanitize_text_field($_POST['authora_smsname_template_id']));
}
if (isset($_POST['authora_smsname_sender_number'])) {
    update_option('authora_smsname_sender_number', sanitize_text_field($_POST['authora_smsname_sender_number']));
}
```

#### 1.3. اضافه کردن متغیرهای تنظیمات در تابع `authora_sms_settings_page()`:

```php
// smsname - add to beginning of function
$smsname_api_key = get_option('authora_smsname_api_key');
$smsname_template_id = get_option('authora_smsname_template_id');
$smsname_sender_number = get_option('authora_smsname_sender_number');
```

#### 1.4. اضافه کردن گزینه به dropdown انتخاب درایور:

```php
<select name="authora_sms_driver" id="sms-driver-select">
    <option value="smsir" <?php selected($selected_driver, 'smsir'); ?>><?php esc_html_e('SMS.ir', 'authora-easy-login-with-mobile-number'); ?></option>
    <option value="farazsms" <?php selected($selected_driver, 'farazsms'); ?>><?php esc_html_e('Faraz SMS', 'authora-easy-login-with-mobile-number'); ?></option>
    <option value="shahvar" <?php selected($selected_driver, 'shahvar'); ?>><?php esc_html_e('Shahvar Payam', 'authora-easy-login-with-mobile-number'); ?></option>
    <!-- Add new option -->
    <option value="smsname" <?php selected($selected_driver, 'smsname'); ?>><?php esc_html_e('نام شرکت شما', 'authora-easy-login-with-mobile-number'); ?></option>
</select>
```

#### 1.5. اضافه کردن بخش تنظیمات فرم:

```php
<div id="smsname-settings" class="sms-settings" style="display: <?php echo $selected_driver === 'smsname' ? 'block' : 'none'; ?>">
    <h3><?php esc_html_e('Your Company Name Settings', 'authora-easy-login-with-mobile-number'); ?></h3>
    <table class="form-table">
        <tr>
            <th scope="row"><?php esc_html_e('API Key', 'authora-easy-login-with-mobile-number'); ?></th>
            <td>
                <input type="text" name="authora_smsname_api_key" value="<?php echo esc_attr($smsname_api_key); ?>" class="regular-text">
                <p class="description"><?php esc_html_e('API key received from company panel', 'authora-easy-login-with-mobile-number'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Template ID', 'authora-easy-login-with-mobile-number'); ?></th>
            <td>
                <input type="text" name="authora_smsname_template_id" value="<?php echo esc_attr($smsname_template_id); ?>" class="regular-text">
                <p class="description"><?php esc_html_e('Verification SMS template ID', 'authora-easy-login-with-mobile-number'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Sender Number', 'authora-easy-login-with-mobile-number'); ?></th>
            <td>
                <input type="text" name="authora_smsname_sender_number" value="<?php echo esc_attr($smsname_sender_number); ?>" class="regular-text">
                <p class="description"><?php esc_html_e('SMS sender number', 'authora-easy-login-with-mobile-number'); ?></p>
            </td>
        </tr>
    </table>
</div>
```

### مرحله 2: اضافه کردن درایور به منطق انتخاب

در فایل اصلی پلاگین که درایور انتخاب می‌شود، باید case جدید اضافه کنید:

```php
$selected_driver = get_option('authora_sms_driver', 'smsir');

switch ($selected_driver) {
    case 'smsir':
        require_once(AUTHORA_PLUGIN_PATH . 'drivers/SMSIR/Smsir.php');
        $driver = new AuthoraSmsIrDriver($smsir_api_key, $smsir_template_id);
        break;
    
    case 'farazsms':
        require_once(AUTHORA_PLUGIN_PATH . 'drivers/FarazSMS/FarazSMS.php');
        $driver = new AuthoraFarazSMS();
        break;
    
    case 'shahvar':
        require_once(AUTHORA_PLUGIN_PATH . 'drivers/ShahvarSMS/ShahvarSMS.php');
        $driver = new AuthoraShahvarSMS();
        break;
    
    // Add new case
    case 'smsname':
        require_once(AUTHORA_PLUGIN_PATH . 'drivers/smsname/smsname.php');
        $driver = new AuthoraSmsNameDriver();
        break;
    
    default:
        // Default driver
        require_once(AUTHORA_PLUGIN_PATH . 'drivers/SMSIR/Smsir.php');
        $driver = new AuthoraSmsIrDriver($smsir_api_key, $smsir_template_id);
        break;
}
```

### نمونه کامل تغییرات

#### فایل `admin/manager.php` - بخش‌های اضافه شده:

```php
// In authora_register_sms_settings() function
register_setting('authora_sms_settings', 'authora_smsname_api_key', 'sanitize_text_field');
register_setting('authora_sms_settings', 'authora_smsname_template_id', 'sanitize_text_field');
register_setting('authora_sms_settings', 'authora_smsname_sender_number', 'sanitize_text_field');

// In save section
if (isset($_POST['authora_smsname_api_key'])) {
    update_option('authora_smsname_api_key', sanitize_text_field($_POST['authora_smsname_api_key']));
}
if (isset($_POST['authora_yourprovider_template_id'])) {
    update_option('authora_yourprovider_template_id', sanitize_text_field($_POST['authora_yourprovider_template_id']));
}
if (isset($_POST['authora_yourprovider_sender_number'])) {
    update_option('authora_yourprovider_sender_number', sanitize_text_field($_POST['authora_yourprovider_sender_number']));
}

// In authora_sms_settings_page() function
$yourprovider_api_key = get_option('authora_yourprovider_api_key');
$yourprovider_template_id = get_option('authora_yourprovider_template_id');
$yourprovider_sender_number = get_option('authora_yourprovider_sender_number');
```

### نکات مهم برای تنظیمات

1. **نام‌گذاری یکسان**: نام درایور در select باید با نام پوشه و ID تنظیمات یکسان باشد
2. **CSS خودکار**: فایل `js/setting.js` به صورت خودکار تنظیمات را نمایش/مخفی می‌کند
3. **اعتبارسنجی**: همیشه از `sanitize_text_field()` استفاده کنید
4. **توضیحات**: برای هر فیلد توضیح مناسب اضافه کنید
5. **پیش‌فرض**: درایور پیش‌فرض را در نظر بگیرید

## مثال‌های موجود

برای درک بهتر، می‌توانید از درایورهای موجود الهام بگیرید:

- `drivers/SMSIR/Smsir.php` - درایور SMS.ir
- `drivers/FarazSMS/FarazSMS.php` - درایور فراز اس ام اس
- `drivers/ShahvarSMS/` - درایور شهوار اس ام اس

هر کدام از این درایورها نمونه‌های مختلفی از پیاده‌سازی API های مختلف ارائه می‌دهند.
## تست و ع
یب‌یابی درایور

### تست دستی درایور

برای تست درایور جدید، می‌توانید کد زیر را استفاده کنید:

```php
// Independent driver test
function test_your_sms_driver() {
    require_once(AUTHORA_PLUGIN_PATH . 'drivers/YourProvider/YourProvider.php');
    
    $driver = new AuthoraYourProviderDriver('your-api-key', 'template-id', 'sender-number');
    
    $result = $driver->sendVerifyCode('09123456789', '12345');
    
    if (is_wp_error($result)) {
        error_log('SMS Test Error: ' . $result->get_error_message());
        return false;
    } else {
        error_log('SMS Test Success: ' . print_r($result, true));
        return true;
    }
}

// Call test
add_action('wp_loaded', function() {
    if (isset($_GET['test_sms']) && current_user_can('manage_options')) {
        test_your_sms_driver();
    }
});
```

### عیب‌یابی مشکلات رایج

#### 1. خطای اتصال به API
```php
// Check WordPress logs
tail -f /path/to/wordpress/wp-content/debug.log

// Or in driver code
error_log('API Request: ' . print_r($params, true));
error_log('API Response: ' . $body);
```

#### 2. مشکل فرمت شماره موبایل
```php
// Test number format
$mobile = '09123456789';
$formatted = $this->formatMobile($mobile);
error_log("Original: $mobile, Formatted: $formatted");
```

#### 3. خطای JSON
```php
// Check JSON response
$result = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('JSON Error: ' . json_last_error_msg());
    error_log('Raw Response: ' . $body);
}
```

### چک‌لیست تکمیل درایور

- [ ] کلاس درایور `AuthoraSmsDriverInterface` را پیاده‌سازی می‌کند
- [ ] متد `sendVerifyCode()` به درستی کار می‌کند
- [ ] خطاها به صورت `WP_Error` برگردانده می‌شوند
- [ ] پاسخ موفق به فرمت صحیح است
- [ ] شماره موبایل به درستی فرمت می‌شود
- [ ] تنظیمات در پنل ادمین اضافه شده
- [ ] گزینه درایور در dropdown موجود است
- [ ] درایور در منطق انتخاب اضافه شده
- [ ] تست دستی انجام شده

### نمونه لاگ موفق

```
[2024-01-01 12:00:00] YourProvider API Request: {"api_key":"***","template_id":"123","mobile":"+989123456789","parameters":{"code":"12345"}}
[2024-01-01 12:00:01] YourProvider API Response: {"status":"success","message_id":"msg_123456"}
[2024-01-01 12:00:01] SMS sent successfully to +989123456789
```

### نمونه لاگ خطا

```
[2024-01-01 12:00:00] YourProvider Connection Error: cURL error 28: Operation timed out
[2024-01-01 12:00:00] YourProvider API Error: {"status":"error","message":"Invalid API key"}
[2024-01-01 12:00:00] SMS send failed for +989123456789: Invalid API key
```

## خلاصه

با دنبال کردن این راهنما، می‌توانید به راحتی درایور پیامکی جدید برای هر شرکت پیامکی به پلاگین Authora اضافه کنید. مراحل کلی عبارتند از:

1. ایجاد کلاس درایور با پیاده‌سازی اینترفیس
2. اضافه کردن تنظیمات به پنل ادمین
3. اضافه کردن منطق انتخاب درایور
4. تست و عیب‌یابی

در صورت بروز مشکل، لاگ‌های WordPress و پاسخ‌های API را بررسی کنید.

## مثال‌های موجود

برای درک بهتر، می‌توانید از درایورهای موجود الهام بگیرید:

- `drivers/SMSIR/Smsir.php` - SMS.ir driver
- `drivers/FarazSMS/FarazSMS.php` - Faraz SMS driver
- `drivers/ShahvarSMS/` - Shahvar SMS driver

