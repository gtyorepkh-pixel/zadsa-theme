<?php defined( 'ABSPATH' ) || exit;
/**
 * Services CPT (zad_service), taxonomies, meta box, admin columns and the
 * leads CPT (zad_lead) that stores quote requests.
 */

add_action( 'init', 'zad_register_content_types' );
function zad_register_content_types() {
	$slug = apply_filters( 'zad_services_slug', 'services' );

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
		'rewrite'           => array( 'slug' => 'service-area', 'with_front' => false ),
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
	flush_rewrite_rules();
} );

/* ------------------------------------------------------------------ */
/* Service meta box                                                    */
/* ------------------------------------------------------------------ */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_service_meta', 'تفاصيل الخدمة', 'zad_service_metabox', 'zad_service', 'normal', 'high' );
	add_meta_box( 'zad_lead_meta', 'بيانات الطلب', 'zad_lead_metabox', 'zad_lead', 'normal', 'high' );
} );

function zad_service_metabox( $post ) {
	wp_nonce_field( 'zad_service_save', 'zad_service_nonce' );
	$g = function ( $k, $d = '' ) use ( $post ) {
		$v = get_post_meta( $post->ID, '_zad_' . $k, true );
		return '' === $v ? $d : $v;
	};
	$steps   = (array) $g( 'steps', array() );
	$faq     = (array) $g( 'faq', array() );
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

		<h4>خطوات التنفيذ</h4>
		<div class="zad-repeater" data-name="steps" data-a="t" data-b="d" data-pa="عنوان الخطوة" data-pb="وصف الخطوة">
			<?php foreach ( $steps as $i => $s ) : ?>
				<div class="zad-row">
					<input type="text" name="zad[steps][<?php echo (int) $i; ?>][t]" value="<?php echo esc_attr( $s['t'] ?? '' ); ?>" placeholder="عنوان الخطوة">
					<textarea name="zad[steps][<?php echo (int) $i; ?>][d]" rows="2" placeholder="وصف الخطوة"><?php echo esc_textarea( $s['d'] ?? '' ); ?></textarea>
					<button type="button" class="button zad-del">حذف</button>
				</div>
			<?php endforeach; ?>
			<button type="button" class="button zad-add">+ إضافة خطوة</button>
		</div>

		<h4>الأسئلة الشائعة</h4>
		<div class="zad-repeater" data-name="faq" data-a="q" data-b="a" data-pa="السؤال" data-pb="الإجابة">
			<?php foreach ( $faq as $i => $s ) : ?>
				<div class="zad-row">
					<input type="text" name="zad[faq][<?php echo (int) $i; ?>][q]" value="<?php echo esc_attr( $s['q'] ?? '' ); ?>" placeholder="السؤال">
					<textarea name="zad[faq][<?php echo (int) $i; ?>][a]" rows="2" placeholder="الإجابة"><?php echo esc_textarea( $s['a'] ?? '' ); ?></textarea>
					<button type="button" class="button zad-del">حذف</button>
				</div>
			<?php endforeach; ?>
			<button type="button" class="button zad-add">+ إضافة سؤال</button>
		</div>

		<h4>معرض الصور (قبل / بعد / أعمال)</h4>
		<p>
			<input type="hidden" id="zad_gallery" name="zad[gallery]" value="<?php echo esc_attr( $gallery ); ?>">
			<button type="button" class="button" id="zad_gallery_btn">اختيار الصور</button>
			<span id="zad_gallery_prev" class="zad-prev">
				<?php foreach ( array_filter( array_map( 'intval', explode( ',', $gallery ) ) ) as $aid ) { echo wp_get_attachment_image( $aid, array( 60, 60 ) ); } ?>
			</span>
		</p>
		<p><label>رابط فيديو (YouTube / mp4)<input type="url" name="zad[video]" value="<?php echo esc_attr( $g( 'video' ) ); ?>" style="width:100%" dir="ltr"></label></p>

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

	$gal = isset( $in['gallery'] ) ? implode( ',', array_filter( array_map( 'absint', explode( ',', $in['gallery'] ) ) ) ) : '';
	update_post_meta( $post_id, '_zad_gallery', $gal );

	foreach ( array( 'steps' => array( 't', 'd' ), 'faq' => array( 'q', 'a' ) ) as $key => $f ) {
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
