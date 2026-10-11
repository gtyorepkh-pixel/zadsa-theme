<?php defined( 'ABSPATH' ) || exit;
/**
 * "أعمالنا" (our work) data: gathers before/after pairs and gallery photos from every service,
 * the old gallery option, and extra photos added on the page itself. Cached; refreshed on save.
 */

add_action( 'add_meta_boxes_page', function ( $post ) {
	if ( 'temp/memo-gallery.php' !== get_post_meta( $post->ID, '_wp_page_template', true ) ) {
		return;
	}
	add_meta_box( 'zad_work_box', 'أعمال إضافية (صور)', function ( $p ) {
		wp_nonce_field( 'zad_work', 'zad_work_nonce' );
		$val = (string) get_post_meta( $p->ID, '_zad_work_gallery', true );
		echo '<p class="description">تُجمَّع صور «قبل/بعد» ومعرض الصور من كل الخدمات تلقائياً. أضف هنا أي صور أعمال أخرى.</p>';
		echo '<p><input type="hidden" class="zad-media-val" id="zad_work_gallery" name="zad_work_gallery" value="' . esc_attr( $val ) . '"><button type="button" class="button zad-media-btn" data-target="zad_work_gallery">اختيار الصور</button> <span class="zad-prev" id="zad_work_gallery_prev">';
		foreach ( array_filter( array_map( 'intval', explode( ',', $val ) ) ) as $aid ) { echo wp_get_attachment_image( $aid, array( 60, 60 ) ); }
		echo '</span></p>';
	}, 'page', 'normal' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'page' === get_post_type() ) {
		wp_enqueue_media();
		wp_enqueue_script( 'zad-admin', get_template_directory_uri() . '/assets/js/admin.js', array(), zad_asset_ver( 'assets/js/admin.js' ), true );
	}
} );

add_action( 'save_post_page', function ( $id ) {
	if ( isset( $_POST['zad_work_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_work_nonce'] ) ), 'zad_work' ) && current_user_can( 'edit_post', $id ) ) {
		$v = isset( $_POST['zad_work_gallery'] ) ? implode( ',', array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['zad_work_gallery'] ) ) ) ) ) ) : '';
		update_post_meta( $id, '_zad_work_gallery', $v );
	}
} );

add_action( 'save_post', function () { delete_transient( 'zad_work_items' ); } );

/** @return array[] items: type ba|img, ids, title, link, cat (name), cat_slug, city */
function zad_work_items() {
	$cached = get_transient( 'zad_work_items' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$items = array();
	$meta  = function ( $post ) {
		$cats = get_the_terms( $post->ID, 'service_cat' );
		$cat  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
		$city = '';
		foreach ( (array) get_the_terms( $post->ID, 'service_area' ) as $t ) {
			if ( $t instanceof WP_Term && 0 === (int) $t->parent ) { $city = $t->name; break; }
		}
		return array( 'link' => get_permalink( $post ), 'service' => wp_strip_all_tags( get_the_title( $post ) ), 'cat' => $cat ? $cat->name : ( get_post_type_object( $post->post_type )->labels->name ?? '' ), 'cat_slug' => $cat ? $cat->slug : sanitize_title( $post->post_type ), 'city' => $city );
	};

	// 1. services: before/after pairs + galleries
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 300, 'zad_all' => true, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $p ) {
		$m   = $meta( $p );
		$ba  = array_values( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $p->ID, '_zad_ba', true ) ) ) ) );
		$txt = zad_lines( get_post_meta( $p->ID, '_zad_ba_text', true ) );
		for ( $i = 0; $i + 1 < count( $ba ); $i += 2 ) {
			$items[] = $m + array( 'type' => 'ba', 'ids' => array( $ba[ $i ], $ba[ $i + 1 ] ), 'title' => $txt[ $i / 2 ] ?? $m['service'] );
		}
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $p->ID, '_zad_gallery', true ) ) ) ) as $aid ) {
			$cap = wp_get_attachment_caption( $aid ) ?: get_post_meta( $aid, '_wp_attachment_image_alt', true );
			$items[] = $m + array( 'type' => 'img', 'ids' => array( $aid ), 'title' => $cap ?: $m['service'] );
		}
	}
	// 2. legacy gallery option (old theme repeater)
	foreach ( (array) zad_opt( 'memopt_gallery_grp', array() ) as $it ) {
		$b = $it['memopt_gallery_grp_img_before']['id'] ?? 0;
		$a = $it['memopt_gallery_grp_img_after']['id'] ?? 0;
		if ( $b && $a ) {
			$items[] = array( 'type' => 'ba', 'ids' => array( (int) $b, (int) $a ), 'title' => $it['memopt_gallery_grp_h'] ?? '', 'link' => $it['memopt_gallery_grp_link'] ?? '', 'service' => '', 'cat' => 'أعمال أخرى', 'cat_slug' => 'other', 'city' => '' );
		}
	}
	// 2b. «أعمالنا» pages (zad_work): their before/after pairs and cover, each linking to the work's own page
	foreach ( get_posts( array( 'post_type' => 'zad_work', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => true ) ) as $wk ) {
		list( $wcat, $wcity, ) = zad_wk_terms( $wk->ID );
		$wm  = array( 'link' => get_permalink( $wk ), 'service' => wp_strip_all_tags( get_the_title( $wk ) ), 'cat' => $wcat ? $wcat->name : 'أعمالنا', 'cat_slug' => $wcat ? $wcat->slug : 'works', 'city' => $wcity );
		$wba = zad_wk_ids( $wk->ID, '_zad_wk_ba' );
		for ( $i = 0; $i + 1 < count( $wba ); $i += 2 ) { $items[] = $wm + array( 'type' => 'ba', 'ids' => array( $wba[ $i ], $wba[ $i + 1 ] ), 'title' => wp_strip_all_tags( get_the_title( $wk ) ) ); }
		$wc = zad_wk_image_id( $wk->ID );
		if ( $wc ) { $items[] = $wm + array( 'type' => 'img', 'ids' => array( $wc ), 'title' => wp_strip_all_tags( get_the_title( $wk ) ) ); }
	}
	// 3. extra photos on the work page(s)
	foreach ( get_posts( array( 'post_type' => 'page', 'meta_key' => '_wp_page_template', 'meta_value' => 'temp/memo-gallery.php', 'numberposts' => 5 ) ) as $pg ) {
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $pg->ID, '_zad_work_gallery', true ) ) ) ) as $aid ) {
			$cap = wp_get_attachment_caption( $aid ) ?: get_post_meta( $aid, '_wp_attachment_image_alt', true );
			$items[] = array( 'type' => 'img', 'ids' => array( $aid ), 'title' => $cap, 'link' => '', 'service' => '', 'cat' => 'أعمال أخرى', 'cat_slug' => 'other', 'city' => '' );
		}
	}
	set_transient( 'zad_work_items', $items, 6 * HOUR_IN_SECONDS );
	return $items;
}
