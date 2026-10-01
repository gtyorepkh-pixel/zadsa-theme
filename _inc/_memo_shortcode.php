<?php defined( 'ABSPATH' ) || exit;



function nested_foo_shortcode($atts, $content = null) {
  $atts = shortcode_atts(array(
      'mqs_title' => '',
      'mqs_img' => '',
      'mqs_answer' => '',
  ), $atts);


  return '<div class="post-feature-box col-25"><img src="' . esc_html(@$atts['mqs_img']) . '" alt="' . esc_html(@$atts['mqs_title']) . '"><h3>' . esc_html(@$atts['mqs_title']) . '</h3><p>' . esc_html(@$atts['mqs_answer']) . '</p></div>';
}
add_shortcode('memo_qs_opt_nested_foo', 'nested_foo_shortcode');

function foo_shortcode($atts, $content = null) {
  return '<div class="flex space-between">' . do_shortcode($content) . '</div>';
}
add_shortcode('memo_qs_opt', 'foo_shortcode');




function related_foo_shortcode($atts, $content = null) {
  $atts = shortcode_atts(array(
      'relatedpost_textarea' => '',
      'relatedpost_text' => '',
      'relatedpost_link' => '',
      'relatedpost_img' => '',
  ), $atts);

  return '<div class="post-feature-box col-25"><a href="' . esc_html($atts['relatedpost_link']) . '"><img src="' . esc_html($atts['relatedpost_img']) . '" alt="' . esc_html($atts['relatedpost_text']) . '"><h3>' . esc_html($atts['relatedpost_text']) . '</h3><p>' . esc_html($atts['relatedpost_textarea']) . '</p></a></div>';
}
add_shortcode('memo_relatedpost_opt_nested_foo', 'related_foo_shortcode');

function related_shortcode($atts, $content = null) {
  return '<div class="flex space-between">' . do_shortcode($content) . '</div>';
}
add_shortcode('memo_relatedpost_opt', 'related_shortcode');
