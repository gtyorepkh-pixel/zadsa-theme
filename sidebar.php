<?php
/**
 * The template for displaying sidebar
 *
 * @package memohost
 */
defined( 'ABSPATH' ) || exit; ?>
<?php if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('memo_widget_sidebar')) : ?>
    
<?php endif; ?>