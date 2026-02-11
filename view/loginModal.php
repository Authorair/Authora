<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$digits = 5;
?>
<div id="modal" class="modal-window" lang="<?php echo esc_attr( get_locale() ); ?>" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
  <div class="authora-container">
    <a href="#" title="<?php _e('Close', 'authora'); ?>" class="modal-close">
        <svg width="25" height="25" viewbox="0 0 40 40"><path d="M 10,10 L 30,30 M 30,10 L 10,30" stroke="black" stroke-width="4" /></svg>
    </a>
    <?php include( AUTHORA_LOGIN_VIEW . 'loginFormContent.php' ); ?>
  </div>
</div>