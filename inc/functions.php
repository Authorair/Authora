<?php

defined('ABSPATH') || exit;

/**
* Generate OTP code using Cryptographic Security Generator (CSPRNG)
*/
function authora_generate_code( $digits = 5 ){
    $min = 10 ** ($digits - 1);
    $max = (10 ** $digits) - 1;
    return random_int( $min, $max );
}

/**
* Register the code in the database (the code is stored in hashed form)
*/
function authora_register_code( $mobile, $code, $token, $expired_at ){

    global $wpdb;

    $hashed_code = wp_hash_password( $code );

    $data = [
        'mobile'         => $mobile,
        'code'          => $hashed_code,
        'token'         => $token,
        'expired_at'    => $expired_at,
        'created_at'    => current_time('mysql'),
        'updated_at'    => current_time('mysql'),
    ];

    $inserted = $wpdb->insert(
        $wpdb->authora_login,
        $data,
        [ '%s', '%s', '%s', '%s', '%s', '%s' ]
    );

    if( ! $inserted ){
        return new WP_Error( 'error_insertion', 'خطا در ثبت اطلاعات' );
    }

    return $wpdb->insert_id;
}

/**
* Mobile number cleaning and standardization
*/
function sanitize_mobile( $mobile ){
    $western    = array('0','1','2','3','4','5','6','7','8','9');
    $persian    = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic     = ['٠',  '١',  '٢', '٣','٤', '٥', '٦','٧','٨','٩' ];
    
    $mobile = str_replace( $persian, $western, $mobile );
    $mobile = str_replace( $arabic, $western, $mobile );

    if( strpos( $mobile, '.' ) === 0 ) $mobile = '0' . substr( $mobile, 1 );
    if( strpos( $mobile, '0098' ) === 0 ) $mobile = substr( $mobile, 4 );
    if( strlen( $mobile ) == 13 && strpos( $mobile, '098' ) === 0 ) $mobile = substr( $mobile, 3 );
    if( strlen( $mobile ) == 13 && strpos( $mobile, '+98' ) === 0 ) $mobile = substr( $mobile, 3 );
    if( strlen( $mobile ) == 14 && strpos( $mobile, '+98 ' ) === 0 ) $mobile = substr( $mobile, 4 );
    if( strlen( $mobile ) == 12 && strpos( $mobile, '98' ) === 0 ) $mobile = substr( $mobile, 2 );
    
    if( strpos( $mobile, '0' ) !== 0 ) $mobile = '0' . $mobile;

    if( ! ctype_digit( $mobile ) || strlen( $mobile ) != 11 ){
        return '';
    }

    return $mobile;
}

function getUserByMobile( $mobile ){
    $users = get_users([
        'meta_key'      => 'mobile',
        'meta_value'    => $mobile
    ]);
    return empty( $users ) ? false : $users[0];
}

function getOrMakeUser( $mobile ){
    $user = getUserByMobile( $mobile );

    if( ! $user ){
        $password = wp_generate_password( 15 );
        $user_id = wp_create_user( 'u_' . wp_generate_password( 6, false, false), $password );

        if( ! is_wp_error( $user_id ) ){
            $user = new WP_User( $user_id );
            update_user_meta( $user_id, 'mobile', $mobile );
        }else{
            $user = $user_id;
        }
    }

    return $user;
}

function authoraDrivers( $mobile, $code ){
    $selected_driver = get_option('authora_sms_driver', 'smsir');

    switch ($selected_driver) {
        case 'shahvar':
            $driver = new AuthoraShahvarSMS(
                get_option('authora_shahvar_api_key'),
                get_option('authora_shahvar_sender_number'),
                get_option('authora_shahvar_pattern_code')
            );
            break;
        case 'farazsms':
            $driver = new AuthoraFarazSMS(
                get_option('authora_farazsms_api_key'),
                get_option('authora_farazsms_pattern_code'),
                get_option('authora_farazsms_sender_number')
            );
            break;
        case 'smsir':
        default:
            $driver = new AuthoraSmsIrDriver(
                get_option('authora_smsir_api_key'),
                get_option('authora_smsir_template_id')
            );
            break;
    }

    $manager = AuthoraSmsManager::getInstance();
    $manager->setDriver($driver);
    return $manager->sendVerifyCode($mobile, $code);
}

function authora_get_login_page_url() {
    return home_url('/login/');
}

function authora_is_login_page() {
    return is_page('login');
}

function authora_is_woocommerce_login() {
    return function_exists('is_account_page') && is_account_page() && !is_user_logged_in();
}
