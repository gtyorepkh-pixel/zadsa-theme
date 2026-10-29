<?php if ( ! defined( 'ABSPATH' ) ) { die; }

if ( class_exists( 'Zad_Settings' ) ) {

	$prefix = '_memo_theme_options';

	Zad_Settings::createOptions( $prefix, array(
		'menu_title'         => 'إعدادات القالب',
		'menu_slug'          => 'memo-theme-options',
		'framework_title'    => 'لوحة تحكم زاد السعودية <small>Zad Pro v3.39.0</small>',
		'menu_icon'          => 'dashicons-admin-generic',
		'menu_position'      => 2,
		'show_bar_menu'      => true,
		'show_sub_menu'      => false,
		'show_in_customizer' => false,
		'show_search'        => true,
		'show_reset_all'     => false,
		'show_reset_section' => false,
		'show_footer'        => false,
		'theme'              => 'light',
	) );

	// التاب الأساسي الأول: الشعار وأرقام التواصل والسوشيال ميديا
	Zad_Settings::createSection( $prefix, array(
		'title'  => 'الشعار وأرقام التواصل',
		'icon'   => 'fa fa-phone',
		'fields' => array(
			array(
				'type'    => 'subheading',
				'content' => 'الشعار الرسمي وأرقام الاتصال والواتساب وحسابات التواصل الاجتماعي',
			),
			array(
				'id'      => 'memopt_logo',
				'type'    => 'media',
				'title'   => 'شعار الموقع (Logo)',
				'library' => 'image',
			),
			array(
				'id'      => 'memopt_phone',
				'type'    => 'text',
				'title'   => 'رقم الجوال الأساسي للاتصال',
				'default' => '0552744437',
			),
			array(
				'id'      => 'memopt_whatsapp',
				'type'    => 'text',
				'title'   => 'رقم الواتساب الأساسي',
				'default' => '+966552744437',
			),
			array(
				'id'      => 'memopt_mail',
				'type'    => 'text',
				'title'   => 'البريد الإلكتروني الرسمي',
				'default' => 'info@zadksa.com',
			),
			array(
				'id'      => 'memopt_address',
				'type'    => 'text',
				'title'   => 'العنوان المختصر في الفوتر',
				'default' => '2851 شارع عبدالملك بن مروان، حي العليا، الرياض',
			),
			array(
				'id'      => 'memopt_footer_h',
				'type'    => 'textarea',
				'title'   => 'نبذة الشركة في الفوتر',
				'default' => 'شركة زاد السعودية للصيانة والنظافة تقدم خدمات تنظيف المنازل والمكيفات والخزانات والكنب ومكافحة الحشرات بضمان مكتوب.',
			),
			array(
				'type'    => 'subheading',
				'content' => 'روابط حسابات التواصل الاجتماعي (تظهر في الفوتر وفي sameAs بالسكيما)',
			),
			array( 'id' => 'memopt_fb', 'type' => 'text', 'title' => 'فيسبوك (Facebook)', 'attributes' => array( 'dir' => 'ltr' ) ),
			array( 'id' => 'memopt_tw', 'type' => 'text', 'title' => 'منصة إكس (X / Twitter)', 'attributes' => array( 'dir' => 'ltr' ) ),
			array( 'id' => 'memopt_insta', 'type' => 'text', 'title' => 'إنستغرام (Instagram)', 'attributes' => array( 'dir' => 'ltr' ) ),
			array( 'id' => 'memopt_yt', 'type' => 'text', 'title' => 'يوتيوب (YouTube)', 'attributes' => array( 'dir' => 'ltr' ) ),
		),
	) );
}
