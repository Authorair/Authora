<?php

defined('ABSPATH') || exit;

function authora_activation(){

    global $wpdb;
    
    $table              = $wpdb->authora_login;
    $table_collation    = $wpdb->collate;

    $sql = "CREATE TABLE `$table` (
        `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
        `mobile` varchar(11) NOT NULL,
        `code` varchar(20) NOT NULL,
        `token` varchar(64) NOT NULL DEFAULT '',
        `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
        `message_id` bigint(20) unsigned NOT NULL DEFAULT 0,
        `price` smallint(5) unsigned NOT NULL DEFAULT 0,
        `status` varchar(20) NOT NULL DEFAULT 'pending',
        `expired_at` datetime NOT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`ID`),
        KEY `user_id` (`user_id`),
        KEY `mobile` (`mobile`),
        KEY `status` (`status`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$table_collation";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    // Create login page
    authora_create_login_page();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Add mobile number column to users table
function authora_add_mobile_column($columns) {
    $columns['mobile'] = __('شماره موبایل', 'authora-easy-login-with-mobile-number');
    return $columns;
}
add_filter('manage_users_columns', 'authora_add_mobile_column');

function authora_add_mobile_to_contact_methods($methods) {
    $methods['mobile'] = __('شماره موبایل', 'authora-easy-login-with-mobile-number');
    return $methods;
}
add_filter('user_contactmethods', 'authora_add_mobile_to_contact_methods');

// Display mobile number in users list
function authora_display_mobile_column($value, $column_name, $user_id) {
    if ($column_name === 'mobile') {
        $mobile = get_user_meta($user_id, 'mobile', true);
        return $mobile ? $mobile : '—';
    }
    return $value;
}
add_action('manage_users_custom_column', 'authora_display_mobile_column', 10, 3);

// Add mobile number field to user profile
function authora_add_mobile_field($user) {
    ?>
    <h3><?php esc_html_e('اطلاعات تماس', 'authora-easy-login-with-mobile-number'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="mobile"><?php esc_html_e('شماره موبایل', 'authora-easy-login-with-mobile-number'); ?></label></th>
            <td>
                <input type="text" name="mobile" id="mobile"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'mobile', true)); ?>"
                    class="regular-text" />
                <?php wp_nonce_field('authora_save_mobile_' . $user->ID, 'authora_mobile_nonce'); ?>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'authora_add_mobile_field');
add_action('edit_user_profile', 'authora_add_mobile_field');

// Check if authora_login table exists
function authora_check_authora_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'authora_login';
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}

// Save mobile number and sync with authora_login table
function authora_save_mobile_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    // Check nonce
    if (!isset($_POST['authora_mobile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['authora_mobile_nonce'])), 'authora_save_mobile_' . $user_id)) {
        return false;
    }

    if (isset($_POST['mobile'])) {
        $mobile = sanitize_text_field($_POST['mobile']);
        update_user_meta($user_id, 'mobile', $mobile);
        
        // Only sync if authora_login table exists
        if (authora_check_authora_table()) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'authora_login';
            
            try {
                // Check if user exists in authora_login table
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE user_id = %d",
                    $user_id
                ));
                
                if ($existing) {
                    // Update existing record
                    $wpdb->update(
                        $table_name,
                        array(
                            'mobile' => $mobile,
                            'updated_at' => current_time('mysql')
                        ),
                        array('user_id' => $user_id)
                    );
                } else {
                    // Insert new record
                    $wpdb->insert(
                        $table_name,
                        array(
                            'user_id' => $user_id,
                            'mobile' => $mobile,
                            'status' => 'pending',
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        )
                    );
                }
            } catch (Exception $e) {
                // Log error but don't stop the process
                error_log('Error syncing mobile number: ' . $e->getMessage());
            }
        }
    }
}
add_action('personal_options_update', 'authora_save_mobile_field');
add_action('edit_user_profile_update', 'authora_save_mobile_field');

// Sync mobile number on user registration
function authora_sync_mobile_on_registration($user_id) {
    $mobile = get_user_meta($user_id, 'mobile', true);
    if ($mobile && authora_check_authora_table()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'authora_login';
        
        try {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'mobile' => $mobile,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        } catch (Exception $e) {
            // Log error but don't stop the process
            error_log('Error syncing mobile number on registration: ' . $e->getMessage());
        }
    }
}
add_action('user_register', 'authora_sync_mobile_on_registration');


