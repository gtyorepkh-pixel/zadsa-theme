<?php defined( 'ABSPATH' ) || exit;

/** Sanitized URL base from theme options (Latin letters, digits, dashes). */
function zad_slug( $opt, $default ) {
	$v = sanitize_title_with_dashes( (string) zad_opt( $opt, $default ) );
	return $v ? $v : $default;
}

/** Re-flush rewrite rules automatically when a URL base option changes. */
add_action( 'init', function () {
	$h = md5( zad_slug( 'zad_services_slug', 'services' ) . '|' . zad_slug( 'zad_areas_slug', 'areas' ) . '|' . zad_slug( 'zad_faq_slug', 'faq' ) );
	if ( get_option( 'zad_rw_hash' ) !== $h ) {
		flush_rewrite_rules( false );
		update_option( 'zad_rw_hash', $h, false );
	}
}, 99 );

/**
 * Services CPT (zad_service), taxonomies, meta box, admin columns and the
 * leads CPT (zad_lead) that stores quote requests.
 */

add_action( 'init', 'zad_register_content_types' );
function zad_register_content_types() {
	$slug = zad_slug( 'zad_services_slug', 'services' );
	$area = zad_slug( 'zad_areas_slug', 'areas' );

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
		'has_archive'   => $slug,
		'rewrite'       => array( 'slug' => $slug, 'with_front' => false ),
		'menu_icon'     => 'dashicons-hammer',
		'menu_position' => 5,
		'show_in_rest'  => true,
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions', 'author' ),
	) );

	register_taxonomy( 'service_cat', 'zad_service', array(
		'labels'            => array( 'name' => 'أقسام الخدمات', 'singular_name' => 'قسم', 'add_new_item' => 'إضافة قسم', 'menu_name' => 'الأقسام' ),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => $slug . '-category', 'with_front' => false ),
	) );

	register_taxonomy( 'service_area', 'zad_service', array(
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
	zad_register_content_types();
	if ( function_exists( 'zad_register_faq' ) ) {
		zad_register_faq();
	}
	flush_rewrite_rules();
} );

/* ------------------------------------------------------------------ */
/* Service meta box                                                    */
/* ------------------------------------------------------------------ */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_service_meta', 'تفاصيل الخدمة', 'zad_service_metabox', 'zad_service', 'normal', 'high' );
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
	$services = get_posts( array( 'post_type' => 'zad_service', 'numberposts' => 200, 'post__not_in' => array( $post->ID ), 'orderby' => 'title', 'order' => 'ASC' ) );
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

		<h4>كيف نحدد السعر (عوامل التسعير)</h4>
		<?php zad_repeater_ui( $post->ID, 'factors', 't', 'd', 'العنوان', 'الوصف', 'إضافة عامل' ); ?>

		<h4>قائمة الأسعار <small>(سطر لكل بند: الفئة | الخدمة | السعر | التفاصيل | الضمان — الفئة والتفاصيل والضمان اختيارية. يُستخدم أيضاً في مقدّر السعر الفوري)</small></h4>
		<p><textarea name="zad[prices]" rows="8" style="width:100%" placeholder="غرف خاصة | غرفة صغيرة | 400 ريال / شهرياً | مناسبة لشقة صغيرة | &#10;خدمات إضافية | النقل والتغليف | 300 - 650 ريال | تُدفع مرة واحدة"><?php echo esc_textarea( $g( 'prices' ) ); ?></textarea></p>

		<h4>علامات الإصابة / المشكلة <small>(كيف تعرف أنك تحتاج الخدمة)</small></h4>
		<?php zad_repeater_ui( $post->ID, 'signs', 't', 'd', 'العلامة', 'الوصف', 'إضافة علامة' ); ?>

		<h4>الأضرار المحتملة عند التأجيل</h4>
		<?php zad_repeater_ui( $post->ID, 'harms', 't', 'd', 'الضرر', 'الوصف', 'إضافة ضرر' ); ?>

		<h4>الأمان أولاً <small>(بطاقات)</small></h4>
		<?php zad_repeater_ui( $post->ID, 'safety', 't', 'd', 'العنوان', 'الوصف', 'إضافة نقطة أمان' ); ?>
		<p><label>إرشادات ما بعد الخدمة (سطر لكل إرشاد)<textarea name="zad[aftercare]" rows="3" style="width:100%"><?php echo esc_textarea( $g( 'aftercare' ) ); ?></textarea></label></p>

		<h4>الباقات <small>(سطر لكل باقة: الاسم | السعر | ميزة؛ ميزة؛ ميزة)</small></h4>
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

		<h4>خدمات ذات صلة <small>(اتركها فارغة ليتم اختيارها تلقائياً من نفس القسم)</small></h4>
		<p><select name="zad[related][]" multiple size="6" style="width:100%">
			<?php foreach ( $services as $s ) : ?>
				<option value="<?php echo (int) $s->ID; ?>" <?php echo in_array( $s->ID, $related, true ) ? 'selected' : ''; ?>><?php echo esc_html( $s->post_title ); ?></option>
			<?php endforeach; ?>
		</select></p>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( $screen && in_array( $screen->post_type, array( 'zad_service' ), true ) && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_style( 'zad-admin', get_template_directory_uri() . '/assets/css/admin.css', array(), ZAD_VERSION );
		wp_enqueue_script( 'zad-admin', get_template_directory_uri() . '/assets/js/admin.js', array(), ZAD_VERSION, true );
	}
} );

add_action( 'save_post_zad_service', function ( $post_id ) {
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

	$text = array( 'tagline', 'icon', 'badge', 'cta_text', 'price_unit', 'duration', 'warranty', 'response', 'phone', 'whatsapp' );
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
	update_post_meta( $post_id, '_zad_ba_text', isset( $in['ba_text'] ) ? sanitize_textarea_field( $in['ba_text'] ) : '' );
	update_post_meta( $post_id, '_zad_ba', isset( $in['ba'] ) ? implode( ',', array_filter( array_map( 'absint', explode( ',', $in['ba'] ) ) ) ) : '' );
	update_post_meta( $post_id, '_zad_related', isset( $in['related'] ) ? array_map( 'absint', (array) $in['related'] ) : array() );
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
	if ( $q->is_post_type_archive( 'zad_service' ) || $q->is_tax( array( 'service_cat', 'service_area' ) ) ) {
		$q->set( 'posts_per_page', 12 );
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
		if ( $q->is_post_type_archive( 'zad_service' ) && ! empty( $_GET['q'] ) ) { // phpcs:ignore
			$q->set( 's', sanitize_text_field( wp_unslash( $_GET['q'] ) ) ); // phpcs:ignore
		}
	}
	// Category/tag archives of the old theme: keep services visible too.
}, 20 );

function zad_related_services( $post_id, $limit = 3 ) {
	$ids = array_filter( array_map( 'intval', (array) get_post_meta( $post_id, '_zad_related', true ) ) );
	$args = array( 'post_type' => 'zad_service', 'posts_per_page' => $limit, 'post__not_in' => array( $post_id ), 'no_found_rows' => true );
	if ( $ids ) {
		$args['post__in'] = $ids;
		$args['orderby']  = 'post__in';
	} else {
		$terms = wp_get_post_terms( $post_id, 'service_cat', array( 'fields' => 'ids' ) );
		if ( $terms ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'service_cat', 'terms' => $terms ) );
		}
		$args['orderby'] = array( 'menu_order' => 'ASC', 'date' => 'DESC' );
	}
	return new WP_Query( $args );
}
