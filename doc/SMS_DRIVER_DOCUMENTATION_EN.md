# Adding New SMS Driver to Authora - Developer Guide

This document provides a comprehensive guide for developers to add new SMS drivers to the Authora plugin.

## Driver Architecture Overview

SMS drivers are located in the `drivers/` folder and each driver must implement the `AuthoraSmsDriverInterface` interface.

### Folder Structure:
```
drivers/
├── SmsDriverInterface.php      # Main interface
├── SmsManager.php             # Driver manager
├── Smsname/                   # New driver folder
│   └── smsname.php            # Driver class
└── ...
```

## Steps to Add a New Driver

### Step 1: Create Driver Folder

First, create a folder with your SMS provider name in the `drivers/` path:

```bash
mkdir drivers/smsname
```

### Step 2: Create Driver Class

Create your PHP driver file with the following structure:

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

## Implementation Requirements

### 1. AuthoraSmsDriverInterface Interface

Each driver must implement the `sendVerifyCode($mobile, $code)` method:

```php
public function sendVerifyCode($mobile, $code);
```

### 2. Error Handling

The driver should return a `WP_Error` object in case of errors:

```php
if ($error_occurred) {
    return new WP_Error('error_code', 'Error message in English');
}
```

### 3. Success Response

In case of success, return an array with relevant information:

```php
return [
    'success' => true,
    'message' => 'OTP code sent successfully',
    'data' => $response_data // optional
];
```

## Complete Driver Example

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

## Important Notes

### 1. Security
- Always use `defined('ABSPATH') || exit;` at the beginning of files
- Use `sanitize_text_field()` to sanitize inputs
- Store API keys and sensitive information in WordPress options

### 2. Error Management
- Use `error_log()` to log errors
- Provide error messages in English
- Specify different errors with appropriate codes

### 3. Testing and Debugging
- Log API responses
- Set appropriate timeout (30 seconds)
- Validate JSON responses

### 4. Mobile Number Format
- Convert global numbers to international format (any country, e.g. +1)
- Remove unnecessary characters

## Using the Driver

After creating the driver, you can use it as follows:

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

## WordPress Settings

To store driver settings in WordPress:

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

## Adding Driver to Authora Settings Panel

To display the new driver in the Authora admin panel, you need to apply the following changes:

### Step 1: Add Settings to `admin/manager.php`

#### 1.1. Register new settings in `authora_register_sms_settings()` function:

```php
// smsname - add to the end of function
register_setting('authora_sms_settings', 'authora_smsname_api_key', 'sanitize_text_field');
register_setting('authora_sms_settings', 'authora_smsname_template_id', 'sanitize_text_field');
register_setting('authora_sms_settings', 'authora_smsname_sender_number', 'sanitize_text_field');
```

#### 1.2. Add save logic in the same function:

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

#### 1.3. Add settings variables in `authora_sms_settings_page()` function:

```php
// smsname - add to beginning of function
$smsname_api_key = get_option('authora_smsname_api_key');
$smsname_template_id = get_option('authora_smsname_template_id');
$smsname_sender_number = get_option('authora_smsname_sender_number');
```

#### 1.4. Add option to driver selection dropdown:

```php
<select name="authora_sms_driver" id="sms-driver-select">
    <option value="smsir" <?php selected($selected_driver, 'smsir'); ?>><?php esc_html_e('SMS.ir', 'authora-easy-login-with-mobile-number'); ?></option>
    <option value="farazsms" <?php selected($selected_driver, 'farazsms'); ?>><?php esc_html_e('Faraz SMS', 'authora-easy-login-with-mobile-number'); ?></option>
    <option value="shahvar" <?php selected($selected_driver, 'shahvar'); ?>><?php esc_html_e('Shahvar Payam', 'authora-easy-login-with-mobile-number'); ?></option>
    <!-- Add new option -->
    <option value="smsname" <?php selected($selected_driver, 'smsname'); ?>><?php esc_html_e('Your Company Name', 'authora-easy-login-with-mobile-number'); ?></option>
</select>
```

#### 1.5. Add settings form section:

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

### Step 2: Add Driver to Selection Logic

In the main plugin file where the driver is selected, you need to add a new case:

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

### Complete Example of Changes

#### File `admin/manager.php` - Added sections:

```php
// In authora_register_sms_settings() function
register_setting('authora_sms_settings', 'authora_smsname_api_key', 'sanitize_text_field');
register_setting('authora_sms_settings', 'authora_smsname_template_id', 'sanitize_text_field');
register_setting('authora_sms_settings', 'authora_smsname_sender_number', 'sanitize_text_field');

// In save section
if (isset($_POST['authora_smsname_api_key'])) {
    update_option('authora_smsname_api_key', sanitize_text_field($_POST['authora_smsname_api_key']));
}
if (isset($_POST['authora_smsname_template_id'])) {
    update_option('authora_smsname_template_id', sanitize_text_field($_POST['authora_smsname_template_id']));
}
if (isset($_POST['authora_smsname_sender_number'])) {
    update_option('authora_smsname_sender_number', sanitize_text_field($_POST['authora_smsname_sender_number']));
}

// In authora_sms_settings_page() function
$smsname_api_key = get_option('authora_smsname_api_key');
$smsname_template_id = get_option('authora_smsname_template_id');
$smsname_sender_number = get_option('authora_smsname_sender_number');
```

### Important Notes for Settings

1. **Consistent Naming**: Driver name in select must match folder name and settings ID
2. **Automatic CSS**: The `js/setting.js` file automatically shows/hides settings
3. **Validation**: Always use `sanitize_text_field()`
4. **Descriptions**: Add appropriate descriptions for each field
5. **Default**: Consider the default driver

## Testing and Debugging

### Manual Driver Testing

To test your new driver, you can use the following code:

```php
// Independent driver test
function test_your_sms_driver() {
    require_once(AUTHORA_PLUGIN_PATH . 'drivers/smsname/smsname.php');
    
    $driver = new AuthoraSmsNameDriver('your-api-key', 'template-id', 'sender-number');
    
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

### Common Issues Debugging

#### 1. API Connection Error
```php
// Check WordPress logs
tail -f /path/to/wordpress/wp-content/debug.log

// Or in driver code
error_log('API Request: ' . print_r($params, true));
error_log('API Response: ' . $body);
```

#### 2. Mobile Number Format Issue
```php
// Test number format
$mobile = '09123456789';
$formatted = $this->formatMobile($mobile);
error_log("Original: $mobile, Formatted: $formatted");
```

#### 3. JSON Error
```php
// Check JSON response
$result = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('JSON Error: ' . json_last_error_msg());
    error_log('Raw Response: ' . $body);
}
```

### Driver Completion Checklist

- [ ] Driver class implements `AuthoraSmsDriverInterface`
- [ ] `sendVerifyCode()` method works correctly
- [ ] Errors are returned as `WP_Error`
- [ ] Success response is in correct format
- [ ] Mobile number is formatted correctly
- [ ] Settings added to admin panel
- [ ] Driver option exists in dropdown
- [ ] Driver added to selection logic
- [ ] Manual testing completed

### Successful Log Example

```
[2024-01-01 12:00:00] SmsName API Request: {"api_key":"***","template_id":"123","mobile":"+989123456789","parameters":{"code":"12345"}}
[2024-01-01 12:00:01] SmsName API Response: {"status":"success","message_id":"msg_123456"}
[2024-01-01 12:00:01] SMS sent successfully to +989123456789
```

### Error Log Example

```
[2024-01-01 12:00:00] SmsName Connection Error: cURL error 28: Operation timed out
[2024-01-01 12:00:00] SmsName API Error: {"status":"error","message":"Invalid API key"}
[2024-01-01 12:00:00] SMS send failed for +989123456789: Invalid API key
```

## Summary

By following this guide, you can easily add a new SMS driver for any SMS provider to the Authora plugin. The main steps are:

1. Create driver class with interface implementation
2. Add settings to admin panel
3. Add driver selection logic
4. Test and debug

If you encounter issues, check WordPress logs and API responses.

## Existing Examples

For better understanding, you can get inspiration from existing drivers:

- `drivers/SMSIR/Smsir.php` - SMS.ir driver
- `drivers/FarazSMS/FarazSMS.php` - Faraz SMS driver
- `drivers/ShahvarSMS/` - Shahvar SMS driver

Each of these drivers provides different examples of implementing various APIs.