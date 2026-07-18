<?php

defined('ABSPATH') || exit;

function authora_generate_code( $digits = 5 ){
    $code = '';
    for( $i = 0; $i < $digits; $i++ ){
        $code.= rand( ! $i ? 1 : 0, 9 );
    }
    return $code;
}

function authora_register_code( $mobile, $code, $token, $expired_at ){

    global $wpdb;

    $data = [
        'mobile'         => $mobile,
        'code'          => $code,
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
        notificator_send_message( 'insert error for ' . $wpdb->authora_login . PHP_EOL . print_r( $data, true ) );
        return new WP_Error( 'error_insertion', 'خطا در ثبت داده' );
    }

    return $wpdb->insert_id;

}

function sanitize_mobile( $mobile ){

    /**
     * Convert all chars to en digits
     */
    $western    = array('0','1','2','3','4','5','6','7','8','9');
    $persian    = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic     = ['٠',  '١',  '٢', '٣','٤', '٥', '٦','٧','٨','٩' ];
    $mobile      = str_replace( $persian, $western, $mobile );
    $mobile      = str_replace( $arabic, $western, $mobile );

    // .915 => 0915
    if( strpos( $mobile, '.' ) === 0 ){
        $mobile = '0' . substr( $mobile, 1 );
    }

    // 0098918 => 918
    if( strpos( $mobile, '0098' ) === 0 ){
        $mobile = substr( $mobile, 4 );
    }
    // 098910 => 910
    if( strlen( $mobile ) == 13 && strpos( $mobile, '098' ) === 0 ){
        $mobile = substr( $mobile, 3 );
    }
    // +98915 => 915
    if( strlen( $mobile ) == 13 && strpos( $mobile, '+98' ) === 0 ){
        $mobile = substr( $mobile, 3 );
    }
    // +98 915 => 915
    if( strlen( $mobile ) == 14 && strpos( $mobile, '+98 ' ) === 0 ){
        $mobile = substr( $mobile, 4 );
    }
    // 98915 => 915
    if( strlen( $mobile ) == 12 && strpos( $mobile, '98' ) === 0 ){
        $mobile = substr( $mobile, 2 );
    }
    // Prepend 0
    if( strpos( $mobile, '0' ) !== 0 ){
        $mobile = '0' . $mobile;
    }
    /**
     * check for all character was digit
     */
    if( ! ctype_digit( $mobile ) ){
        return '';
    }

    if( strlen( $mobile ) != 11 ){
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
        $user_id = wp_create_user( $mobile, $password );

        if( ! is_wp_error( $user_id ) ){

            $user = new WP_User( $user_id );

            global $wpdb;
            $wpdb->update($wpdb->users, [
                'user_login' => 'u' . $user_id,
            ],[
                'ID' => $user_id
            ]);

            wp_cache_flush();

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

/**
 * Get login page URL
 */
function authora_get_login_page_url() {
    return home_url('/login/');
}

/**
 * Check if current page is login page
 */
function authora_is_login_page() {
    return is_page('login');
}

/**
 * Check if current page is WooCommerce login page
 */
function authora_is_woocommerce_login() {
    return function_exists('is_account_page') && is_account_page() && !is_user_logged_in();
}