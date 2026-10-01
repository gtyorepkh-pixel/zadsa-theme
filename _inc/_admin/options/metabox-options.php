<?php if ( ! defined( 'ABSPATH' )  ) { die; } // Cannot access directly.

//
// Metabox of the PAGE
// Set a unique slug-like ID
//
$prefix_page_opts = '_memo_metabox_options';

//
// Create a metabox
//


$type =array('post','page','pest_control','cleaning');
CSF::createMetabox( $prefix_page_opts, array(
  'title'        => 'إعدادات صفحة المقال',
  'post_type'    => $type,
  'priority'    => 'high',

) );


//
// Create a section
//
CSF::createSection( $prefix_page_opts, array(
	'title'  => 'اعدادات عامة ',
  'fields' => array(

    //
    // A text field
    //
     array(
      'id'    => 'memo_single_phone',
      'type'  => 'text',
      'title' => 'الجوال',
    ),
	  
	   array(
      'id'    => 'memo_single_whatss',
      'type'  => 'text',
      'title' => 'واتساب',
    ),
    array(
      'id'    => 'memo_single_title',
      'type'  => 'text',
      'title' => 'العنوان',
    ),


    array(
      'id'      => 'memo_single_desc',
      'type'    => 'wp_editor',
      'title'   => 'الوصف',
      'media_buttons' => false,
    ),


    array(
      'id'    => 'memo_single_img',
      'type'  => 'media',
      'library'  => 'image',
      'title' => 'الصورة',
    ),

    
    
    array(
      'id'    => 'memo_single_video',
      'type'  => 'media',
      'library'  => 'video',
      'title' => 'أو فيديو',
    ),
  )
) );

CSF::createSection( $prefix_page_opts, array(
  'title'  => 'اعدادات ذات صلة ',
  'fields' => array(

    array(
      'id'    => 'memo_single_related_switcher',
      'type'  => 'switcher',
      'title' => 'تفعيل',
    ),

    array(
      'id'          => 'memo_single_related',
      'type'        => 'select',
      'title'       => 'قم بالبحث على المقال الذي تريد إظهارة أسفل المقال',
      'chosen'      => true,
      'multiple'    => true,
      'sortable'    => true,
      'ajax'        => true,
      'options'     => 'posts',
      'placeholder' => 'ابحث عن المقال',
    ),
  )
) );