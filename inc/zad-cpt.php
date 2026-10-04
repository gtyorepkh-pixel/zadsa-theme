<?php defined( 'ABSPATH' ) || exit;

/** Sanitized URL base from theme options (Latin letters, digits, dashes). */
function zad_slug( $opt, $default ) {
	$v = sanitize_title_with_dashes( (string) zad_opt( $opt, $default ) );
	return $v ? $v : $default;
}

/** URL base of the theme's own service type: the option if set, /service/ beside adopted types, otherwise the old /services/ (native sites keep their URLs). */
function zad_service_pt_slug( $adopted ) {
	$o = sanitize_title_with_dashes( (string) zad_opt( 'zad_service_slug', '' ) );
	if ( $o ) { return $o; }
	return $adopted ? 'service' : zad_slug( 'zad_services_slug', 'services' );
}

/** Two menus must not both be called «الخدمات»: an adopted type carrying that label gets its own name. */
add_filter( 'register_post_type_args', function ( $args, $pt ) {
	$map = apply_filters( 'zad_type_label_map', array( 'cleaning' => 'التنظيف', 'pest_control' => 'مكافحة الحشرات', 'drain_cleaning' => 'تسليك المجاري' ) );
	if ( 'zad_service' !== $pt && isset( $map[ $pt ] ) && isset( $args['labels'] ) && is_array( $args['labels'] ) && in_array( $args['labels']['name'] ?? '', array( 'الخدمات', 'خدمات' ), true ) ) {
		$args['labels']['name'] = $map[ $pt ];
		$args['labels']['menu_name'] = $map[ $pt ];
		if ( isset( $args['labels']['all_items'] ) ) { $args['labels']['all_items'] = 'كل ' . $map[ $pt ]; }
	}
	return $args;
}, 20, 2 );

/** Re-flush rewrite rules automatically when a URL base option changes. */
add_action( 'init', function () {
	$h = md5( zad_slug( 'zad_services_slug', 'services' ) . '|' . zad_opt( 'zad_service_slug', '' ) . '|' . zad_slug( 'zad_areas_slug', 'areas' ) . '|' . zad_slug( 'zad_faq_slug', 'faq' ) . '|' . implode( ',', zad_service_types() ) . '|' . implode( ',', zad_faq_types() ) . '|' . implode( ',', zad_article_types() ) );
	if ( get_option( 'zad_rw_hash' ) !== $h ) {
		flush_rewrite_rules( false );
		update_option( 'zad_rw_hash', $h, false );
	}
}, 99 );

/**
 * Services CPT (zad_service), taxonomies, meta box, admin columns and the
 * leads CPT (zad_lead) that stores quote requests.
 */

add_action( 'init', 'zad_register_content_types', 50 );
function zad_register_content_types() {
	$slug     = zad_slug( 'zad_services_slug', 'services' );
	$area     = zad_slug( 'zad_areas_slug', 'areas' );
	$existing = zad_existing_types( 'service' );
	$types    = array_values( array_unique( array_merge( array_keys( $existing ), array( 'zad_service' ) ) ) ); // zad_service always exists (a mu-plugin may register it first: mu-plugins/zad-core-service.php)

	if ( ! post_type_exists( 'zad_service' ) ) {
	register_post_type( 'zad_service', array(
		'labels'        => array(
			'name'               => 'الخدمات',
			'singular_name'      => 'خدمة',
			'add_new'            => 'إضافة خدمة',
			'add_new_item'       => 'إضافة خدمة جديدة',
			'edit_item'          => 'تعديل الخدمة',
			'new_item'           => 'خدمة جديدة',
			'view_item'          => 'عرض الخدمة',
			'search_items'       => 'بحث في الخدمات',
			'not_found'          => 'لا توجد خدمات',
			'all_items'          => 'كل الخدمات',
			'menu_name'          => 'الخدمات',
		),
		'public'        => true,
		'has_archive'   => $existing ? false : $slug, // beside adopted types: single pages only, no public archive
		'rewrite'       => array( 'slug' => zad_service_pt_slug( (bool) $existing ), 'with_front' => false ),
		'menu_icon'     => 'dashicons-hammer',
		'menu_position' => 5,
		'show_in_rest'  => true,
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions', 'author' ),
	) );
	}

	register_taxonomy( 'service_cat', $types, array(
		'labels'            => array( 'name' => 'أقسام الخدمات', 'singular_name' => 'قسم', 'add_new_item' => 'إضافة قسم', 'menu_name' => 'الأقسام' ),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => $slug . '-category', 'with_front' => false ),
	) );

	register_taxonomy( 'service_area', $types, array(
		'labels'            => array( 'name' => 'مناطق الخدمة', 'singular_name' => 'منطقة', 'add_new_item' => 'إضافة مدينة / حي', 'menu_name' => 'المدن والأحياء' ),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => $area, 'with_front' => false ),
	) );

	register_post_type( 'zad_lead', array(
		'labels'              => array( 'name' => 'طلبات العملاء', 'singular_name' => 'طلب', 'menu_name' => 'طلبات العملاء', 'all_items' => 'كل الطلبات', 'edit_item' => 'تفاصيل الطلب', 'not_found' => 'لا توجد طلبات' ),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_icon'           => 'dashicons-email-alt',
		'menu_position'       => 6,
		'supports'            => array( 'title' ),
		'capability_type'     => 'post',
		'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'        => true,
		'exclude_from_search' => true,
	) );
}

add_action( 'after_switch_theme', function () {
	delete_option( 'zad_rw_hash' ); // flushed automatically on the next request
} );

/* ------------------------------------------------------------------ */
/* Service meta box                                                    */
/* ------------------------------------------------------------------ */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_service_meta', 'تفاصيل الخدمة', 'zad_service_metabox', zad_service_types(), 'normal', 'high' );
	add_meta_box( 'zad_lead_meta', 'بيانات الطلب', 'zad_lead_metabox', 'zad_lead', 'normal', 'high' );
} );


/** Two-field repeater UI used by the service meta box. */
function zad_repeater_ui( $post_id, $key, $a, $b, $pa, $pb, $add ) {
	$rows = (array) get_post_meta( $post_id, '_zad_' . $key, true );
	echo '<div class="zad-repeater" data-name="' . esc_attr( $key ) . '" data-a="' . esc_attr( $a ) . '" data-b="' . esc_attr( $b ) . '" data-pa="' . esc_attr( $pa ) . '" data-pb="' . esc_attr( $pb ) . '">';
	foreach ( $rows as $i => $r ) {
		echo '<div class="zad-row"><input type="text" name="zad[' . esc_attr( $key ) . '][' . (int) $i . '][' . esc_attr( $a ) . ']" value="' . esc_attr( $r[ $a ] ?? '' ) . '" placeholder="' . esc_attr( $pa ) . '"><textarea name="zad[' . esc_attr( $key ) . '][' . (int) $i . '][' . esc_attr( $b ) . ']" rows="2" placeholder="' . esc_attr( $pb ) . '">' . esc_textarea( $r[ $b ] ?? '' ) . '</textarea><button type="button" class="button zad-del">حذف</button></div>';
	}
	echo '<button type="button" class="button zad-add">+ ' . esc_html( $add ) . '</button></div>';
}

function zad_media_ui( $post_id, $key, $label ) {
	$val = (string) get_post_meta( $post_id, '_zad_' . $key, true );
	echo '<p><input type="hidden" class="zad-media-val" id="zad_' . esc_attr( $key ) . '" name="zad[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '"><button type="button" class="button zad-media-btn" data-target="zad_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button> <span class="zad-prev" id="zad_' . esc_attr( $key ) . '_prev">';
	foreach ( array_filter( array_map( 'intval', explode( ',', $val ) ) ) as $aid ) {
		echo wp_get_attachment_image( $aid, array( 60, 60 ) );
	}
	echo '</span></p>';
}

function zad_service_metabox( $post ) {
	wp_nonce_field( 'zad_service_save', 'zad_service_nonce' );
	$g = function ( $k, $d = '' ) use ( $post ) {
		$v = get_post_meta( $post->ID, '_zad_' . $k, true );
		return '' === $v ? $d : $v;
	};
	$gallery = (string) $g( 'gallery', '' );
	$related = array_map( 'intval', (array) $g( 'related', array() ) );
	$icon    = $g( 'icon', 'sparkle' );
	?>
	<div class="zad-mb">
		<h4>الأساسيات</h4>
		<div class="zad-grid">
			<p><label>وصف مختصر تحت العنوان<input type="text" name="zad[tagline]" value="<?php echo esc_attr( $g( 'tagline' ) ); ?>" placeholder="مثال: مكافحة نهائية مع ضمان حتى سنة"></label></p>
			<p><label>الأيقونة
				<select name="zad[icon]">
					<?php foreach ( zad_icon_keys() as $k ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $icon, $k ); ?>><?php echo esc_html( $k ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>شارة على البطاقة<input type="text" name="zad[badge]" value="<?php echo esc_attr( $g( 'badge' ) ); ?>" placeholder="الأكثر طلباً"></label></p>
			<p><label>عنوان قسم المحتوى (H2 اختياري)<input type="text" name="zad[content_title]" value="<?php echo esc_attr( $g( 'content_title' ) ); ?>" placeholder="فارغ = لا يظهر عنوان قبل المحتوى"></label></p>
			<p><label>نص زر الطلب<input type="text" name="zad[cta_text]" value="<?php echo esc_attr( $g( 'cta_text' ) ); ?>" placeholder="اطلب الخدمة الآن"></label></p>
			<p><label><input type="checkbox" name="zad[featured]" value="1" <?php checked( $g( 'featured' ), '1' ); ?>> خدمة مميزة (تظهر في الرئيسية)</label></p>
		</div>

		<h4>الأسعار والضمان</h4>
		<div class="zad-grid">
			<p><label>السعر يبدأ من (رقم)<input type="number" min="0" step="1" name="zad[price]" value="<?php echo esc_attr( $g( 'price' ) ); ?>"></label></p>
			<p><label>وحدة السعر<input type="text" name="zad[price_unit]" value="<?php echo esc_attr( $g( 'price_unit', 'ريال' ) ); ?>" placeholder="ريال / للمتر / للزيارة"></label></p>
			<p><label>مدة التنفيذ<input type="text" name="zad[duration]" value="<?php echo esc_attr( $g( 'duration' ) ); ?>" placeholder="من 2 إلى 4 ساعات"></label></p>
			<p><label>الضمان<input type="text" name="zad[warranty]" value="<?php echo esc_attr( $g( 'warranty' ) ); ?>" placeholder="ضمان سنة كاملة"></label></p>
			<p><label>سرعة الاستجابة<input type="text" name="zad[response]" value="<?php echo esc_attr( $g( 'response' ) ); ?>" placeholder="خلال ساعتين"></label></p>
			<p><label>التقييم (1 - 5)<input type="number" min="1" max="5" step="0.1" name="zad[rating]" value="<?php echo esc_attr( $g( 'rating' ) ); ?>"></label></p>
			<p><label>عدد التقييمات<input type="number" min="0" step="1" name="zad[reviews]" value="<?php echo esc_attr( $g( 'reviews' ) ); ?>"></label></p>
		</div>

		<h4>مميزات الخدمة <small>(ميزة في كل سطر)</small></h4>
		<p><textarea name="zad[features]" rows="5" style="width:100%"><?php echo esc_textarea( $g( 'features' ) ); ?></textarea></p>

		<h4>الأرقام والإنجازات <small>(الرقم + الوصف، مثال: 13+ / سنة خبرة)</small></h4>
		<?php zad_repeater_ui( $post->ID, 'stats', 'n', 'l', 'الرقم', 'الوصف', 'إضافة رقم' ); ?>

		<h4>لماذا نحن (بطاقات)</h4>
		<?php zad_repeater_ui( $post->ID, 'why', 't', 'd', 'العنوان', 'الوصف', 'إضافة ميزة' ); ?>

		<h4>أنواع الخدمة / الخدمات الفرعية</h4>
		<?php zad_repeater_ui( $post->ID, 'subs', 't', 'd', 'العنوان', 'الوصف', 'إضافة خدمة فرعية' ); ?>

		<h4>الأدوات والمعدات</h4>
		<?php zad_repeater_ui( $post->ID, 'tools', 't', 'd', 'العنوان', 'الوصف', 'إضافة أداة' ); ?>

		<h4>خطوات التنفيذ</h4>
		<?php zad_repeater_ui( $post->ID, 'steps', 't', 'd', 'عنوان الخطوة', 'وصف الخطوة', 'إضافة خطوة' ); ?>
		<?php zad_steps_box( $post->ID ); ?>

		<?php
		$pm       = (string) $g( 'price_mode' );
		$has_t    = (bool) zad_parse_prices( $g( 'prices' ) );
		$has_p    = (bool) zad_parse_packages( $g( 'packages' ) );
		$pview    = zad_price_view( $post->ID );
		$both     = $has_t && $has_p;
		?>
		<h4>الأسعار — ماذا يظهر في الصفحة؟</h4>
		<p><label>قسم الأسعار المعروض <small>(يظهر قسم واحد فقط: جدول الأسعار أو الباقات، ولا يظهران معاً)</small>
			<select name="zad[price_mode]">
				<option value=""<?php selected( $pm, '' ); ?>>تلقائي (الجدول إن وُجد، وإلا الباقات)</option>
				<option value="table"<?php selected( $pm, 'table' ); ?>>جدول الأسعار</option>
				<option value="packages"<?php selected( $pm, 'packages' ); ?>>الباقات</option>
			</select></label>
			<?php if ( $pview ) : ?><span class="description"> المعروض حالياً: <b><?php echo 'table' === $pview ? 'جدول الأسعار' : 'الباقات'; ?></b></span><?php endif; ?></p>
		<h4>كيف نحدد السعر (عوامل التسعير)</h4>
		<?php zad_repeater_ui( $post->ID, 'factors', 't', 'd', 'العنوان', 'الوصف', 'إضافة عامل' ); ?>

		<h4>قائمة الأسعار <small>(سطر لكل بند: الفئة | الخدمة | السعر | التفاصيل | الضمان — الفئة والتفاصيل والضمان اختيارية. يُستخدم أيضاً في مقدّر السعر الفوري)</small></h4>
		<?php if ( $both && 'table' !== $pview ) : ?><p class="description" style="color:#b45309">⚠ مخفي — المعروض حالياً: الباقات</p><?php endif; ?>
		<p><textarea name="zad[prices]" rows="8" style="width:100%" placeholder="غرف خاصة | غرفة صغيرة | 400 ريال / شهرياً | مناسبة لشقة صغيرة | &#10;خدمات إضافية | النقل والتغليف | 300 - 650 ريال | تُدفع مرة واحدة"><?php echo esc_textarea( $g( 'prices' ) ); ?></textarea></p>

		<h4>علامات الإصابة / المشكلة <small>(كيف تعرف أنك تحتاج الخدمة)</small></h4>
		<?php zad_repeater_ui( $post->ID, 'signs', 't', 'd', 'العلامة', 'الوصف', 'إضافة علامة' ); ?>

		<h4>الأضرار المحتملة عند التأجيل</h4>
		<?php zad_repeater_ui( $post->ID, 'harms', 't', 'd', 'الضرر', 'الوصف', 'إضافة ضرر' ); ?>

		<h4>الأمان أولاً <small>(بطاقات)</small></h4>
		<?php zad_repeater_ui( $post->ID, 'safety', 't', 'd', 'العنوان', 'الوصف', 'إضافة نقطة أمان' ); ?>
		<p><label>إرشادات ما بعد الخدمة (سطر لكل إرشاد)<textarea name="zad[aftercare]" rows="3" style="width:100%"><?php echo esc_textarea( $g( 'aftercare' ) ); ?></textarea></label></p>

		<h4>الباقات <small>(سطر لكل باقة: الاسم | السعر | ميزة؛ ميزة؛ ميزة)</small></h4>
		<?php if ( $both && 'packages' !== $pview ) : ?><p class="description" style="color:#b45309">⚠ مخفي — المعروض حالياً: جدول الأسعار</p><?php endif; ?>
		<p><textarea name="zad[packages]" rows="5" style="width:100%" placeholder="باقة أساسية | 250 ريال | معاينة؛ مبيدات آمنة؛ ضمان شهر"><?php echo esc_textarea( $g( 'packages' ) ); ?></textarea></p>

		<h4>الضمان <small>(بطاقات)</small></h4>
		<?php zad_repeater_ui( $post->ID, 'warrantyrows', 't', 'd', 'العنوان', 'الوصف', 'إضافة بند ضمان' ); ?>

		<h4>البطاقة الفنية <small>(سطر: العنوان | القيمة — تضاف إلى البيانات التلقائية)</small></h4>
		<p><textarea name="zad[spec]" rows="4" style="width:100%" placeholder="المواد المستخدمة | مبيدات مبطّنة مرخصة SFDA"><?php echo esc_textarea( $g( 'spec' ) ); ?></textarea></p>

		<p><label>ملاحظة تحت جدول الأسعار (خصومات، شروط…)<textarea name="zad[price_note]" rows="2" style="width:100%"><?php echo esc_textarea( $g( 'price_note' ) ); ?></textarea></label></p>

		<h4>الأسئلة الشائعة</h4>
		<?php zad_repeater_ui( $post->ID, 'faq', 'q', 'a', 'السؤال', 'الإجابة', 'إضافة سؤال' ); ?>

		<h4>قبل / بعد <small>(اختر الصور بالترتيب: قبل، بعد، قبل، بعد… والعناوين سطراً لكل زوج: العنوان | الوصف)</small></h4>
		<?php zad_media_ui( $post->ID, 'ba', 'اختيار صور قبل/بعد' ); ?>
		<p><textarea name="zad[ba_text]" rows="3" style="width:100%"><?php echo esc_textarea( $g( 'ba_text' ) ); ?></textarea></p>

		<h4>معرض الصور (قبل / بعد / أعمال)</h4>
		<p>
			<input type="hidden" id="zad_gallery" name="zad[gallery]" value="<?php echo esc_attr( $gallery ); ?>">
			<button type="button" class="button" id="zad_gallery_btn">اختيار الصور</button>
			<span id="zad_gallery_prev" class="zad-prev">
				<?php foreach ( array_filter( array_map( 'intval', explode( ',', $gallery ) ) ) as $aid ) { echo wp_get_attachment_image( $aid, array( 60, 60 ) ); } ?>
			</span>
		</p>
		<p><label>رابط فيديو (YouTube / mp4)<input type="url" name="zad[video]" value="<?php echo esc_attr( $g( 'video' ) ); ?>" style="width:100%" dir="ltr"></label></p>
		<p><label>مدة الفيديو بالثواني (للسكيما)<input type="number" min="0" name="zad[video_duration]" value="<?php echo esc_attr( $g( 'video_duration' ) ); ?>" style="width:140px"></label></p>

		<h4>تواصل مخصص لهذه الخدمة <small>(اتركه فارغاً لاستخدام الأرقام العامة)</small></h4>
		<div class="zad-grid">
			<p><label>رقم الجوال<input type="text" name="zad[phone]" value="<?php echo esc_attr( $g( 'phone' ) ); ?>" dir="ltr"></label></p>
			<p><label>رقم الواتساب<input type="text" name="zad[whatsapp]" value="<?php echo esc_attr( $g( 'whatsapp' ) ); ?>" dir="ltr"></label></p>
		</div>

		<h4>خدمات ومقالات ذات صلة <small>(فارغ = لا يظهر القسم؛ اختر 3–6 صفحات وثيقة الصلة)</small></h4>
		<?php zad_related_picker( $post->ID, $related ); ?>
		<?php zad_guides_box( $post->ID ); ?>
		<?php zad_coverage_box( $post->ID ); ?>
		<?php zad_quick_box( $post->ID ); ?>
	</div>
	<?php zad_mb_tabs( $post->ID ); ?>
	<?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( $screen && in_array( $screen->post_type, zad_service_types(), true ) && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_style( 'zad-admin', get_template_directory_uri() . '/assets/css/admin.css', array(), zad_asset_ver( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'zad-admin', get_template_directory_uri() . '/assets/js/admin.js', array(), zad_asset_ver( 'assets/js/admin.js' ), true );
	}
} );

add_action( 'save_post', function ( $post_id ) {
	if ( ! in_array( get_post_type( $post_id ), zad_service_types(), true ) ) {
		return;
	}
	if ( ! isset( $_POST['zad_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_service_nonce'] ) ), 'zad_service_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['zad'] ) ? wp_unslash( (array) $_POST['zad'] ) : array();

	$text = array( 'tagline', 'icon', 'badge', 'content_title', 'cta_text', 'price_unit', 'duration', 'warranty', 'response', 'phone', 'whatsapp' );
	foreach ( $text as $k ) {
		update_post_meta( $post_id, '_zad_' . $k, isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '' );
	}
	$icon = get_post_meta( $post_id, '_zad_icon', true );
	if ( ! in_array( $icon, zad_icon_keys(), true ) ) {
		update_post_meta( $post_id, '_zad_icon', 'sparkle' );
	}
	foreach ( array( 'price', 'reviews' ) as $k ) {
		$v = isset( $in[ $k ] ) && '' !== $in[ $k ] ? absint( $in[ $k ] ) : '';
		update_post_meta( $post_id, '_zad_' . $k, $v );
	}
	$rating = isset( $in['rating'] ) && '' !== $in['rating'] ? min( 5, max( 1, (float) $in['rating'] ) ) : '';
	update_post_meta( $post_id, '_zad_rating', $rating );
	update_post_meta( $post_id, '_zad_featured', ! empty( $in['featured'] ) ? '1' : '' );
	update_post_meta( $post_id, '_zad_features', isset( $in['features'] ) ? sanitize_textarea_field( $in['features'] ) : '' );
	update_post_meta( $post_id, '_zad_video', isset( $in['video'] ) ? esc_url_raw( $in['video'] ) : '' );
	update_post_meta( $post_id, '_zad_video_duration', isset( $in['video_duration'] ) && '' !== $in['video_duration'] ? absint( $in['video_duration'] ) : '' );

	$gal = isset( $in['gallery'] ) ? implode( ',', array_filter( array_map( 'absint', explode( ',', $in['gallery'] ) ) ) ) : '';
	update_post_meta( $post_id, '_zad_gallery', $gal );

	$maps = array(
		'stats'   => array( 'n', 'l' ),
		'why'     => array( 't', 'd' ),
		'subs'    => array( 't', 'd' ),
		'tools'   => array( 't', 'd' ),
		'steps'   => array( 't', 'd' ),
		'factors' => array( 't', 'd' ),
		'faq'     => array( 'q', 'a' ),
		'signs'   => array( 't', 'd' ),
		'harms'   => array( 't', 'd' ),
		'safety'  => array( 't', 'd' ),
		'warrantyrows' => array( 't', 'd' ),
	);
	foreach ( $maps as $key => $f ) {
		$rows = array();
		foreach ( isset( $in[ $key ] ) ? (array) $in[ $key ] : array() as $r ) {
			$a = isset( $r[ $f[0] ] ) ? sanitize_text_field( $r[ $f[0] ] ) : '';
			$b = isset( $r[ $f[1] ] ) ? sanitize_textarea_field( $r[ $f[1] ] ) : '';
			if ( '' !== $a ) {
				$rows[] = array( $f[0] => $a, $f[1] => $b );
			}
		}
		update_post_meta( $post_id, '_zad_' . $key, $rows );
	}
	foreach ( array( 'aftercare', 'packages', 'spec', 'price_note' ) as $tk ) {
		update_post_meta( $post_id, '_zad_' . $tk, isset( $in[ $tk ] ) ? sanitize_textarea_field( $in[ $tk ] ) : '' );
	}
	update_post_meta( $post_id, '_zad_prices', isset( $in['prices'] ) ? sanitize_textarea_field( $in['prices'] ) : '' );
	update_post_meta( $post_id, '_zad_price_mode', in_array( $in['price_mode'] ?? '', array( 'table', 'packages' ), true ) ? $in['price_mode'] : '' );
	update_post_meta( $post_id, '_zad_ba_text', isset( $in['ba_text'] ) ? sanitize_textarea_field( $in['ba_text'] ) : '' );
	update_post_meta( $post_id, '_zad_ba', isset( $in['ba'] ) ? implode( ',', array_filter( array_map( 'absint', explode( ',', $in['ba'] ) ) ) ) : '' );
	zad_related_save( $post_id, $in );
	zad_guides_save( $post_id, $in );
	zad_coverage_save( $post_id, $in );
	zad_steps_save( $post_id, $in );
	zad_quick_save( $post_id, $in );
} );

/* ------------------------------------------------------------------ */
/* Admin columns                                                       */
/* ------------------------------------------------------------------ */

add_filter( 'manage_zad_service_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['zad_price']    = 'السعر';
			$new['zad_featured'] = 'مميزة';
		}
	}
	return $new;
} );
add_action( 'manage_zad_service_posts_custom_column', function ( $col, $id ) {
	if ( 'zad_price' === $col ) {
		$p = get_post_meta( $id, '_zad_price', true );
		echo $p ? esc_html( $p . ' ' . get_post_meta( $id, '_zad_price_unit', true ) ) : '—';
	}
	if ( 'zad_featured' === $col ) {
		echo get_post_meta( $id, '_zad_featured', true ) ? '★' : '—';
	}
}, 10, 2 );

add_filter( 'manage_zad_lead_posts_columns', function () {
	return array( 'cb' => '<input type="checkbox">', 'title' => 'الاسم', 'zad_phone' => 'الجوال', 'zad_service' => 'الخدمة', 'zad_area' => 'المنطقة', 'zad_status' => 'الحالة', 'date' => 'التاريخ' );
} );
add_action( 'manage_zad_lead_posts_custom_column', function ( $col, $id ) {
	if ( 'zad_phone' === $col ) {
		$p = get_post_meta( $id, '_lead_phone', true );
		echo $p ? '<a href="' . esc_url( zad_tel_href( $p ) ) . '" dir="ltr">' . esc_html( $p ) . '</a> · <a target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . zad_intl_number( $p ) ) . '">واتساب</a>' : '—';
	}
	if ( 'zad_service' === $col ) {
		echo esc_html( get_post_meta( $id, '_lead_service', true ) ?: '—' );
	}
	if ( 'zad_area' === $col ) {
		echo esc_html( get_post_meta( $id, '_lead_area', true ) ?: '—' );
	}
	if ( 'zad_status' === $col ) {
		$s = get_post_meta( $id, '_lead_status', true ) ?: 'new';
		$m = array( 'new' => 'جديد', 'contacted' => 'تم التواصل', 'done' => 'مكتمل', 'lost' => 'ملغي' );
		echo esc_html( $m[ $s ] ?? $s );
	}
}, 10, 2 );

function zad_lead_metabox( $post ) {
	wp_nonce_field( 'zad_lead_save', 'zad_lead_nonce' );
	$rows = array( 'phone' => 'الجوال', 'service' => 'الخدمة', 'area' => 'المنطقة', 'message' => 'التفاصيل', 'source' => 'الصفحة', 'ip' => 'IP' );
	echo '<table class="form-table">';
	foreach ( $rows as $k => $label ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( get_post_meta( $post->ID, '_lead_' . $k, true ) ) ) . '</td></tr>';
	}
	$st = get_post_meta( $post->ID, '_lead_status', true ) ?: 'new';
	echo '<tr><th>الحالة</th><td><select name="lead_status">';
	foreach ( array( 'new' => 'جديد', 'contacted' => 'تم التواصل', 'done' => 'مكتمل', 'lost' => 'ملغي' ) as $k => $v ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $st, $k, false ) . '>' . esc_html( $v ) . '</option>';
	}
	echo '</select></td></tr></table>';
}
add_action( 'save_post_zad_lead', function ( $post_id ) {
	if ( ! isset( $_POST['zad_lead_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_lead_nonce'] ) ), 'zad_lead_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$st = isset( $_POST['lead_status'] ) ? sanitize_key( wp_unslash( $_POST['lead_status'] ) ) : 'new';
	update_post_meta( $post_id, '_lead_status', in_array( $st, array( 'new', 'contacted', 'done', 'lost' ), true ) ? $st : 'new' );
} );

/* ------------------------------------------------------------------ */
/* Category icon (term meta)                                           */
/* ------------------------------------------------------------------ */

foreach ( array( 'add', 'edit' ) as $mode ) {
	add_action( "service_cat_{$mode}_form_fields", function ( $term = null ) use ( $mode ) {
		$cur  = ( 'edit' === $mode && $term ) ? get_term_meta( $term->term_id, 'zad_icon', true ) : 'sparkle';
		$html = '<select name="zad_icon" id="zad_icon">';
		foreach ( zad_icon_keys() as $k ) {
			$html .= '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $k ) . '</option>';
		}
		$html .= '</select>';
		if ( 'edit' === $mode ) {
			echo '<tr class="form-field"><th><label for="zad_icon">أيقونة القسم</label></th><td>' . $html . '</td></tr>'; // phpcs:ignore
		} else {
			echo '<div class="form-field"><label for="zad_icon">أيقونة القسم</label>' . $html . '</div>'; // phpcs:ignore
		}
	} );
}
add_action( 'created_service_cat', 'zad_save_cat_icon' );
add_action( 'edited_service_cat', 'zad_save_cat_icon' );
function zad_save_cat_icon( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['zad_icon'] ) ) {
		return;
	}
	$i = sanitize_key( wp_unslash( $_POST['zad_icon'] ) );
	if ( in_array( $i, zad_icon_keys(), true ) ) {
		update_term_meta( $term_id, 'zad_icon', $i );
	}
}

/* ------------------------------------------------------------------ */
/* Query helpers                                                       */
/* ------------------------------------------------------------------ */

/** Order services by menu_order then date on the services archives. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->is_post_type_archive( zad_service_types() ) || $q->is_tax( array( 'service_cat', 'service_area' ) ) ) {
		$q->set( 'post_type', zad_service_types() );
		$q->set( 'posts_per_page', 12 );
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
		if ( $q->is_post_type_archive( zad_service_types() ) && ! empty( $_GET['q'] ) ) { // phpcs:ignore
			$q->set( 's', sanitize_text_field( wp_unslash( $_GET['q'] ) ) ); // phpcs:ignore
		}
	}
	// Category/tag archives of the old theme: keep services visible too.
}, 20 );

function zad_related_services( $post_id, $limit = 3 ) {
	$ids  = array_values( array_filter( array_map( 'intval', (array) get_post_meta( $post_id, '_zad_related', true ) ) ) );
	$all  = array_values( array_unique( array_merge( zad_service_types(), zad_article_types() ) ) );
	$args = array( 'post_type' => $all, 'post_status' => 'publish', 'posts_per_page' => $limit, 'post__not_in' => array( $post_id ), 'no_found_rows' => true, 'ignore_sticky_posts' => true );
	if ( $ids ) {
		$args['post__in']       = $ids;
		$args['orderby']        = 'post__in';
		$args['posts_per_page'] = max( $limit, min( 12, count( $ids ) ) );
		unset( $args['post__not_in'] );
		return new WP_Query( $args );
	}
	if ( ! zad_opt( 'zad_related_auto', false ) ) {
		return new WP_Query( array( 'post__in' => array( 0 ), 'no_found_rows' => true ) ); // nothing chosen = nothing shown
	}
	$args['post_type'] = zad_service_types();
	$terms = wp_get_post_terms( $post_id, 'service_cat', array( 'fields' => 'ids' ) );
	if ( $terms ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'service_cat', 'terms' => $terms ) );
	}
	$args['orderby'] = array( 'menu_order' => 'ASC', 'date' => 'DESC' );
	return new WP_Query( $args );
}


/** Every service gets a category named after its post type when it has none (adopted content). */
function zad_type_category( $post_id ) {
	$pt = get_post_type( $post_id );
	$o  = get_post_type_object( $pt );
	if ( ! $o || 'zad_service' === $pt ) {
		return;
	}
	if ( has_term( '', 'service_cat', $post_id ) ) {
		return;
	}
	$name = $o->labels->name;
	$t    = term_exists( $name, 'service_cat' );
	if ( ! $t ) {
		$t = wp_insert_term( $name, 'service_cat', array( 'slug' => sanitize_title( zad_type_base( $pt ) ) ?: $pt ) );
	}
	if ( ! is_wp_error( $t ) ) {
		wp_set_object_terms( $post_id, array( (int) ( is_array( $t ) ? $t['term_id'] : $t ) ), 'service_cat' );
	}
}
add_action( 'save_post', function ( $id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( in_array( get_post_type( $id ), zad_service_types(), true ) && 'publish' === get_post_status( $id ) ) {
		zad_type_category( $id );
	}
}, 30 );


/** Turns the long service form into side-by-side tabs (pure JS; every field is still submitted). */
function zad_mb_tabs( $post_id ) {
	?>
	<style>
	.zad-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;padding:8px;background:#f0f6fa;border:1px solid #cfe0e8;border-radius:8px;position:sticky;top:32px;z-index:5}
	.zad-tabs button{border:1px solid #cfe0e8;background:#fff;color:#0c687e;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:700;font-size:13px}
	.zad-tabs button:hover{border-color:#0c687e}
	.zad-tabs button.is-on{background:#0c687e;border-color:#0c687e;color:#fff}
	.zad-tab-panel{display:none}.zad-tab-panel.is-on{display:block}
	.zad-tab-panel>h4:first-child{margin-top:4px}
	</style>
	<script>
	(function(){
		var box=document.querySelector('.zad-mb');if(!box||box.dataset.tabbed)return;box.dataset.tabbed=1;
		var rules=[
			['نظرة عامة',/^(الأساسيات|بطاقة الهيرو)/],
			['المميزات والأرقام',/^(مميزات|الأرقام|لماذا|أنواع الخدمة|الأدوات)/],
			['خطوات التنفيذ',/^(خطوات التنفيذ|شكل قسم)/],
			['المشاكل والأمان',/^(علامات|الأضرار|الأمان|الأعراض)/],
			['الأسعار',/^(الأسعار|كيف نحدد|قائمة الأسعار|الباقات)/],
			['الضمان',/^(الضمان|البطاقة الفنية)/],
			['الأسئلة',/^الأسئلة/],
			['الصور والفيديو',/^(قبل|معرض)/],
			['التواصل والربط',/^(تواصل|خدمات ومقالات)/],
			['التغطية',/^التغطية/]
		];
		var order=rules.map(function(r){return r[0]}),groups={},cur=rules[0][0];
		function pick(t){t=t.trim();for(var i=0;i<rules.length;i++){if(rules[i][1].test(t))return rules[i][0];}return null;}
		[].slice.call(box.children).forEach(function(n){
			if(n.tagName==='H4'){var g=pick(n.textContent);if(g)cur=g;}
			(groups[cur]=groups[cur]||[]).push(n);
		});
		var nav=document.createElement('div');nav.className='zad-tabs';nav.setAttribute('role','tablist');
		var key='zadmbtab'+(<?php echo (int) $post_id; ?>),saved=null;try{saved=sessionStorage.getItem(key);}catch(e){}
		var names=order.filter(function(g){return groups[g]&&groups[g].length;}),panels={};
		names.forEach(function(g){
			var p=document.createElement('div');p.className='zad-tab-panel';groups[g].forEach(function(n){p.appendChild(n);});panels[g]=p;box.appendChild(p);
			var b=document.createElement('button');b.type='button';b.textContent=g;b.setAttribute('role','tab');b.onclick=function(){show(g);};nav.appendChild(b);
		});
		function show(g){names.forEach(function(x,i){panels[x].classList.toggle('is-on',x===g);nav.children[i].classList.toggle('is-on',x===g);});try{sessionStorage.setItem(key,g);}catch(e){}}
		box.insertBefore(nav,box.firstChild);
		show(names.indexOf(saved)>-1?saved:names[0]);
	})();
	</script>
	<?php
}
