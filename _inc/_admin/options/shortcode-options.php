<?php if ( ! defined( 'ABSPATH' )  ) { die; } // Cannot access directly.

//
// Set a unique slug-like ID
//
$prefix = 'csf_memo_shortcodes';

//
// Create a shortcoder
//
CSF::createShortcoder( $prefix, array(
  'button_title'   => 'اضف من هنا',
 'select_title'   => 'اختر منهنا',
  'insert_title'   => 'اضافة الشورت كود',
 'show_in_editor' => true,
  'gutenberg'      => array(
   'title'        => 'اضافة مميزات او ذات صلة',
    'description'  => 'اضافة مميزات او ذات صلة داخل المقال',
    'icon'         => 'screenoptions',
   'category'     => 'widgets',
   'keywords'     => array( 'shortcode', 'csf', 'insert' ),
  'placeholder'  => '...',
  )
) );


CSF::createSection( $prefix, array(
  'title'           => 'المميزات',
  'view'            => 'group',
  'shortcode'       => 'memo_qs_opt',
  'group_shortcode' => 'memo_qs_opt_nested_foo',
  'group_fields'    => array(

    array(
      'id'     => 'mqs_title',
      'type'   => 'text',
      'title'  => 'العنوان',
    ),

    array(
      'id'     => 'mqs_answer',
      'type'   => 'textarea',
      'title'  => 'الوصف',
    ),

    array(
      'id'     => 'mqs_img',
      'type'   => 'upload',
      'title'  => 'الايقونة',
    ),
  )
) );

CSF::createSection( $prefix, array(
  'title'           => 'ذات صلة',
  'view'            => 'group',
  'shortcode'       => 'memo_relatedpost_opt',
  'group_shortcode' => 'memo_relatedpost_opt_nested_foo',
  'group_fields'    => array(

    array(
      'id'     => 'relatedpost_text',
      'type'   => 'text',
      'title'  => 'العنوان',
    ),
    array(
      'id'     => 'relatedpost_link',
      'type'   => 'text',
      'title'  => 'الرابط',
    ),
 array(
      'id'     => 'relatedpost_textarea',
      'type'   => 'textarea',
      'title'  => 'وصف قصير',
    ),
    array(
      'id'     => 'relatedpost_img',
      'type'   => 'upload',
      'title'  => 'الصورة',
    ),
  )
) );