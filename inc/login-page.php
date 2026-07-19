<?php

defined('ABSPATH') || exit;

/**
 * Add rewrite rule for login page
 */
function authora_add_login_page_rewrite() {
    add_rewrite_rule(
        '^login/?$',
        'index.php?pagename=login',
        'top'
    );
}
add_action('init', 'authora_add_login_page_rewrite');

/**
 * Add query vars for login page
 */
function authora_add_query_vars($vars) {
    $vars[] = 'login_page';
    return $vars;
}
add_filter('query_vars', 'authora_add_query_vars');

/**
 * Create login page on plugin activation
 */
function authora_create_login_page() {
    // Check if login page already exists
    $login_page = get_page_by_path('login');
    
    if (!$login_page) {
        $page_data = array(
            'post_title'    => __('ورود / ثبت نام', 'authora-easy-login-with-mobile-number'),
            'post_name'     => 'login',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
            'page_template' => 'view/login-page.php'
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id) {
            update_post_meta($page_id, '_wp_page_template', 'view/login-page.php');
        }
    }
}



/**
 * Redirect to login page if user is not logged in
 */
function authora_redirect_to_login() {
    if (!is_user_logged_in() && !is_page('login')) {
        // You can customize this condition based on your needs
        // For example, only redirect on specific pages
        if (is_page() || is_single()) {
            wp_redirect(authora_get_login_page_url());
            exit;
        }
    }
}
// Uncomment the line below if you want automatic redirects
// add_action('template_redirect', 'authora_redirect_to_login');

/**
 * Add login page link to navigation
 */
function authora_add_login_link_to_menu($items, $args) {
    if (!is_user_logged_in()) {
        $login_url = authora_get_login_page_url();
        $login_text = __('ورود / ثبت نام', 'authora-easy-login-with-mobile-number');
        $items .= '<li><a href="' . esc_url($login_url) . '">' . esc_html($login_text) . '</a></li>';
    }
    return $items;
}
// Uncomment the line below if you want to add login link to menus
// add_filter('wp_nav_menu_items', 'authora_add_login_link_to_menu', 10, 2);

/**
 * Handle login page template
 */
function authora_load_login_page_template($template) {
    if (is_page('login')) {
        $login_template = AUTHORA_LOGIN_VIEW . 'login-page.php';
        if (file_exists($login_template)) {
            return $login_template;
        }
    }
    return $template;
}
add_filter('template_include', 'authora_load_login_page_template');

/**
 * Add custom CSS for login page
 */
function authora_login_page_styles() {
    if (is_page('login')) {
        wp_enqueue_style('authora-login-page', AUTHORA_LOGIN_CSS . 'login-page.css', array(), AUTHORA_LOGIN_VERSION);
    }
}
add_action('wp_enqueue_scripts', 'authora_login_page_styles');

/**
 * Add custom JavaScript for login page
 */
function authora_login_page_scripts() {
    if (is_page('login')) {
        wp_enqueue_script('authora-login-page', AUTHORA_LOGIN_JS . 'login-page.js', array('jquery'), AUTHORA_LOGIN_VERSION, true);
        wp_localize_script('authora-login-page', 'authora_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('authora-login'),
            'nonce_verify' => wp_create_nonce('authora-verify'),
            'login_url' => authora_get_login_page_url(),
            'redirect_url' => home_url()
        ));
    }
}
add_action('wp_enqueue_scripts', 'authora_login_page_scripts');
