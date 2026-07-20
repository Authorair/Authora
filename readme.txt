=== Authora : Easy login with mobile number ===
Contributors: authora
Tags: otp, mobile login, SMS login, OTP login, WordPress authentication, login with phone number, SMS verification, WooCommerce login, SMS gateway, Twilio, sms.ir, 
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.7.9
License: GPL v2.0 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

## Authora ##

Authora is a lightweight and developer-friendly WordPress plugin that enables users to log in using just their mobile number — no passwords, no emails, no hassle.

It provides a modern, secure, and user-friendly passwordless login experience for WordPress websites, using OTP (One-Time Password) verification via SMS.

## Features: ##

- Passwordless Login

- Login with mobile number only

- Automatic sending of verification code (OTP) via SMS

- Automatic registration on first login (if no account)

- Compatible with popular WordPress themes and plugins

## Why Authora? ##

In today's world, users are tired of lengthy registration and login forms. Authora provides a fast and enjoyable experience, especially for WooCommerce stores, membership sites, and websites that want to make the login process simpler and more secure.

## Installation ##

- Upload the plugin to your WordPress `/wp-content/plugins/` directory.

- Activate the plugin via the WordPress admin panel.

- Configure your SMS gateway settings under Settings → Authora.

- Use the `[authora-login]` shortcode wherever you want the login form to appear.
```
<?php echo do_shortcode("[authora-login]"); ?>
```

- Use Pages :
```
<?php echo do_shortcode("[authora-login show_modal="false"]"); ?>
or
[authora-login show_modal="false"]
```


## Frequently Asked Questions ##

### What does the Authora plugin do? ###

Authora allows users to quickly log in or register on your WordPress site using their mobile number. Users can access your site without a password, simply by receiving a verification code via SMS.

### Is Authora compatible with WooCommerce? ###

Yes, Authora is fully compatible with WooCommerce. You can enable mobile login for the WooCommerce login form as well.

### Which SMS providers are supported by the plugin? ###

Currently, Authora supports SMS.ir, Faraz SMS, and ShahvarPayam as SMS providers. We will be adding international SMS providers soon.

### Can I customize the SMS message content? ###

Yes, you can customize the SMS message and template from the plugin settings and your SMS provider's panel.

### Is Authora free to use? ###

Yes, Authora is completely free and you can use it without any charges.

### Do I need any additional plugins to use Authora? ###

No, Authora works independently and does not require any additional plugins. You only need to enter your SMS provider details.

### Does Authora support both English and Persian languages? ###

Yes, the plugin is fully multilingual and supports both English and Persian.

### What should users do if they don't receive the SMS code? ###

If a user does not receive the SMS, they can use the "Resend" option. Also, make sure the SMS provider settings are entered correctly.

### Can I change the appearance of the login form? ###

Yes, you can customize the appearance by editing the plugin's CSS files or adding your own custom CSS.

## External services ##

This plugin connects to external SMS service providers to send verification codes. The following data is sent to these services:

- Mobile number: The user's mobile phone number for sending SMS verification codes.
- Verification code: A randomly generated numeric code for authentication.
- API credentials: Your configured API keys and settings for the selected SMS provider.

The plugin supports the following SMS providers:

### API Endpoints: ###

SMS.ir
- https://api.sms.ir/v1/send/verify

### IPPanel Infrastructure ###
- https://api2.ippanel.com/api/v1/sms/pattern/normal/send
- Faraz SMS
- Shahvar Payam

All communications with external services are done securely over HTTPS. The plugin does not store or transmit any personal data beyond what is necessary for SMS delivery.

### How can I get support? ###

For support, you can visit the plugin's GitHub page:

https://github.com/Rayiumir/Authora

## Screenshots ##

1. Screenshot-1
2. Screenshot-2
3. Screenshot-3

## Changelog ##

### 1.7.9 ###

- Fix Style Modal Close SVG

### 1.7.8 ###

- Increase code column from varchar(20) to varchar(255) to fit wp_hash_password output
- Add automatic DB upgrade for existing tables
- Fix nonce action mismatch in enqueue.php
- Add missing nonce to verify form and AJAX request"
- Replace insecure rand() with cryptographically secure random_int() for OTP generation
- Hash OTP codes using wp_hash_password() and verify with wp_check_password()
- Prevent secure token exposure by removing it from the DOM and using JS closure storage
- Add nonce verification to authora_verify to mitigate CSRF attacks
- Add database index on mobile column to improve lookup performance and prevent full table scans
- Remove legacy PHP session-based login system and obsolete login files
- Fix JavaScript scope issues and improve AJAX error handling

### 1.7.7 ###

**Security release — please update as soon as possible.**

This release fixes an unauthenticated authentication bypass (account takeover) in the mobile OTP login flow.

* **Critical: OTP leak in login response.** The `authora_login` AJAX action previously returned the one-time code (`code`) and a verification nonce (`_wpnonce`) directly in its unauthenticated JSON response. An attacker who knew a registered mobile number could request a code, read the code and nonce from the response body, and replay them to `authora_verify` to obtain a logged-in session as that user — without ever receiving the SMS. Targeting an administrator's mobile resulted in full site takeover. The response no longer includes the code or any verification nonce.
* **Critical: predictable verification nonce.** `authora_verify` trusted a nonce derived from the mobile number (`wp_create_nonce( 'verify' . $mobile )`) rather than real server-side state, so the leaked nonce validated for anyone. Verification is now bound to an opaque, cryptographically random server-side token that is issued only to the requester and stored server-side.
* **Hardening: rate limiting.** A per-mobile rate limit (60 seconds) was added to code requests to slow down enumeration and abuse.
* **Hardening: brute-force protection.** The code is now invalidated after 5 failed verification attempts, and the token/code are cleared on a successful login to prevent reuse/replay.
* **Fix:** Corrected a latent bug where a database insert failure during code registration was silently swallowed instead of returning an error.

**Upgrade note:** This version adds two columns (`token`, `attempts`) to the `authora_login` table. They are created automatically via `dbDelta` on plugin (re)activation. No other configuration is required.


### 1.7.6 ###
* Security: OTP code and verification nonce are no longer returned in the login response (previously an unauthenticated attacker could read the code and nonce and replay them to log in as any user, including admins).
* Security: verification is now bound to an opaque server-side token returned only to the real requester, instead of a predictable nonce derived from the mobile number.
* Security: added a per-mobile rate limit on code requests and invalidation of the code after 5 failed verification attempts.
* Security: the verification token and OTP are cleared after a successful login to prevent reuse.
