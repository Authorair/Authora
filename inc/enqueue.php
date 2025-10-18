<?php

defined('ABSPATH') || exit;

function authora_public_scripts(){

    if( is_user_logged_in() ){
        return;
    }

    wp_enqueue_style(
        'authora-style',
        AUTHORA_LOGIN_CSS . 'authora.css',
        [],
        defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION
    );

    wp_enqueue_style(
        'authora-spinner',
        AUTHORA_LOGIN_CSS . 'spinner.css',
        [],
        defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION
    );

    wp_enqueue_script(
        'authora-script',
        AUTHORA_LOGIN_JS . 'authora.js',
        ['jquery'],
        defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION,
        true
    );

    if (is_rtl()) {
        wp_enqueue_style(
            'authora-rtl',
            AUTHORA_LOGIN_CSS . 'modal-rtl.css',
            [],
            defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION,
        );
    }

    wp_localize_script( 'authora-script', 'authora', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('authora_login'),

        'i18n' => array(
                    'sending' => __('Sending...', 'authora-easy-login-with-mobile-number'),
        'resend' => __('Resend', 'authora-easy-login-with-mobile-number'),
        'error_occurred' => __('An error occurred. Please try again.', 'authora-easy-login-with-mobile-number'),
        'connection_error' => __('Could not connect to server.', 'authora-easy-login-with-mobile-number')
        )
    ]);

    // Enqueue login form scripts and styles if on login page or WooCommerce login
    if (authora_is_login_page() || authora_is_woocommerce_login()) {
        wp_enqueue_style(
            'authora-login-form-style',
            AUTHORA_LOGIN_CSS . 'login-form.css',
            [],
            defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION
        );

        wp_enqueue_script(
            'authora-login-form-script',
            AUTHORA_LOGIN_JS . 'login-form.js',
            ['jquery'],
            defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION,
            true
        );

        wp_localize_script( 'authora-login-form-script', 'authora_login_form', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_send_code' => wp_create_nonce('authora_send_code'),
            'nonce_verify_code' => wp_create_nonce('authora_verify_code'),
        ]);
    }

    // Enqueue login page styles if on the custom login page
    if (authora_is_login_page()) {
        wp_enqueue_style(
            'authora-login-page-style',
            AUTHORA_LOGIN_CSS . 'login-page.css',
            [],
            defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION
        );
    }

}
add_action( 'wp_enqueue_scripts', 'authora_public_scripts' );

function authora_admin_scripts($hook){
    // Only load on plugin settings page
    if (strpos($hook, 'authora') === false) {
        return;
    }

    wp_enqueue_style(
        'authora-setting-style',
        AUTHORA_LOGIN_CSS . 'setting.css',
        [],
        defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION
    );

    wp_enqueue_script(
        'authora-setting-script',
        AUTHORA_LOGIN_JS . 'setting.js',
        ['jquery'],
        defined('WP_DEBUG') && WP_DEBUG ? time() : AUTHORA_LOGIN_VERSION,
        true
    );

}
add_action( 'admin_enqueue_scripts', 'authora_admin_scripts' );