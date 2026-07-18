<?php

defined('ABSPATH') || exit;

function authora_login() {
    global $wpdb;
    $result = [
        'message'   => __('An error occurred', 'authora-easy-login-with-mobile-number')
    ];

    if(
        ! isset( $_REQUEST['mobile'] ) ||
        ! isset( $_REQUEST['_wpnonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'authora-login' )
    ){
        wp_send_json_error( $result, 401 );
    }

    $mobile = sanitize_mobile( $_REQUEST['mobile'] );

    if( ! $mobile ){
        $result['message']  = __('Invalid mobile number', 'authora-easy-login-with-mobile-number');
        wp_send_json_error( $result, 401 );
    }

    $table_name = $wpdb->authora_login;

    // Per-mobile rate limit: block new code requests for a short window.
    $rate_limit      = 60;
    $last_request_at = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT created_at FROM {$table_name} WHERE mobile = %s ORDER BY created_at DESC LIMIT 1",
            $mobile
        )
    );

    if( $last_request_at && ( current_time('timestamp') - strtotime( $last_request_at ) ) < $rate_limit ){
        $result['message']  = sprintf(
            __('Please wait %d seconds before requesting a new code', 'authora-easy-login-with-mobile-number'),
            $rate_limit - ( current_time('timestamp') - strtotime( $last_request_at ) )
        );
        wp_send_json_error( $result, 429 );
    }

    $digit          = 5;
    $expire         = 200;
    $code           = authora_generate_code( $digit );

    // Server-side token binds this login request to the verify step.
    // It is a random secret never derived from the mobile number.
    $token          = wp_generate_password( 32, false, false );

    $expired_at     = date( 'Y-m-d H:i:s', current_time('timestamp') + $expire );

    $inserted       = authora_register_code( $mobile, $code, $token, $expired_at );

    if( is_wp_error( $inserted ) ){
        $result['message'] = $inserted->get_error_message();
        wp_send_json_error( $result, 503 );
    }

    $sent_sms = authoraDrivers( $mobile, $code );

    if( is_wp_error( $sent_sms ) ){
        $result['message'] = $sent_sms->get_error_message();
        wp_send_json_error( $result, 400 );
    }

    $wpdb->update(
        $wpdb->authora_login,
        [
            'price' => $sent_sms->cost,
            'message_id' => $sent_sms->messageId,
        ],
        [
            'ID' => $inserted,
        ]
    );

    // Do NOT leak the code or a verify nonce. Only return the opaque server token,
    // which must be presented back on verify so the request is bound to this session.
    $result['message']  = sprintf(__('Enter the %d-digit code sent to %s', 'authora-easy-login-with-mobile-number'), $digit, $mobile);
    $result['duration'] = $expire;
    $result['mobile']   = $mobile;
    $result['token']    = $token;

    wp_send_json_success( $result, 200 );

}
add_action('wp_ajax_nopriv_authora_login', 'authora_login');

function authora_verify(){

    $result = [
        'message'   => __('An error occurred', 'authora-easy-login-with-mobile-number')
    ];

    if(
        ! isset( $_REQUEST['mobile'] ) ||
        ! isset( $_REQUEST['code'] ) ||
        ! isset( $_REQUEST['token'] ) ||
        ! is_string( $_REQUEST['token'] ) ||
        ! preg_match( '/^[a-zA-Z0-9]+$/', $_REQUEST['token'] )
    ){
        wp_send_json_error( $result, 401 );
    }

    $mobile  = sanitize_mobile( $_REQUEST['mobile'] );

    if( !$mobile ){
        $result['message']  = __('Invalid phone number', 'authora-easy-login-with-mobile-number');
        wp_send_json_error( $result, 401 );
    }

    $code   = sanitize_text_field( $_REQUEST['code'] );
    $token  = sanitize_text_field( $_REQUEST['token'] );

    global $wpdb;
    $table_name = $wpdb->authora_login;
    $verify = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE mobile = %s ORDER BY created_at DESC",
            $mobile
        )
    );

    if( !$verify ){
        $result['message']  = __('Your verification request was not found', 'authora-easy-login-with-mobile-number');
        wp_send_json_error( $result, 401 );
    }

    // The token binds this verify call to the login request that minted it.
    // A leaked or guessed token must not validate; compare in constant time.
    $token_valid = function_exists( 'hash_equals' )
        ? hash_equals( (string) $verify->token, $token )
        : ( (string) $verify->token === $token );

    if( ! $token_valid ){
        $result['message']  = __('Invalid verification session, please request a new code', 'authora-easy-login-with-mobile-number');
        wp_send_json_error( $result, 401 );
    }

    // Invalidated (expired attempts or consumed) codes must not be reused.
    if( (int) $verify->attempts >= 5 ){
        $result['message']  = __('Too many failed attempts, please request a new code', 'authora-easy-login-with-mobile-number');
        wp_send_json_error( $result, 401 );
    }

    if( current_time('timestamp') >= strtotime( $verify->expired_at ) ){
        $result['message']  = __('Code has expired, please try again', 'authora-easy-login-with-mobile-number');
        wp_send_json_error( $result, 401 );
    }

    if( $verify->code != $code ){
        $wpdb->update(
            $table_name,
            [ 'attempts' => (int) $verify->attempts + 1 ],
            [ 'ID' => $verify->ID ]
        );
        $result['message']  = __('Incorrect verification code, please try again', 'authora-easy-login-with-mobile-number');
        wp_send_json_error( $result, 401 );
    }

    $exists = getUserByMobile( $mobile );
    $user   = getOrMakeUser( $mobile );

    if( is_wp_error( $user ) ){
        $result['message']  = $user->get_error_message();
        wp_send_json_error( $result, 401 );
    }

    wp_clear_auth_cookie();
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID );

    // Login

    $data = [
        'user_id'   => $user->ID,
        'status'    => $exists ? 'login' : 'register',
        'updated_at'    => current_time('mysql'),
    ];

    $data['token']    = '';
    $data['code']     = '';
    $data['attempts'] = 5;

    $wpdb->update(
        $wpdb->authora_login,
        $data,[
            'ID' => $verify->ID
        ]
    );

    $result['message'] = __('Login successful', 'authora-easy-login-with-mobile-number');
    wp_send_json_success( $result, 200 );
    
}
add_action( 'wp_ajax_nopriv_authora_verify', 'authora_verify' );