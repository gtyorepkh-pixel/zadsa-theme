<?php defined( 'ABSPATH' ) || exit;
/** Existing site hooks; no data migration or option changes. */

add_action( 'wp_head', function() {
	if ( is_single() || is_page() ) {
		add_filter( 'post_thumbnail_html', '__return_false', 999 );
	}
} );

add_action( 'pre_get_posts', function( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) { return; }
	if ( $query->is_category() || $query->is_tag() ) {
		$query->set( 'post_type', get_post_types( array( 'public' => true ), 'names' ) );
	}
	if ( $query->is_category() ) {
		$cat = get_queried_object();
		if ( $cat && 0 === (int) $cat->parent ) {
			$children = get_categories( array( 'parent' => $cat->term_id, 'hide_empty' => false, 'fields' => 'ids' ) );
			if ( ! empty( $children ) ) {
				$query->set( 'category__not_in', $children );
			}
		}
	}
} );

add_filter( 'do_redirect_guess_404_permalink', '__return_false' );

add_filter( 'the_content', function( $content ) {
	$target_types = array( 'pest_control', 'cleaning', 'sections', 'pests-library', 'guide' );
	if ( is_singular( $target_types ) && ! zad_is_service() && ! zad_is_faq() && is_main_query() ) {
		$pub = get_the_date();
		$mod = get_the_modified_date();
		$html = '<div class="cpt-dates-box"><span class="publish-date"><strong>تاريخ النشر: </strong> ' . esc_html( $pub ) . '</span>';
		if ( get_the_modified_time( 'U' ) > get_the_time( 'U' ) + 86400 ) {
			$html .= ' · <span class="modified-date"><strong>آخر تحديث: </strong> ' . esc_html( $mod ) . '</span>';
		}
		return $html . '</div>' . $content;
	}
	return $content;
} );

add_filter( 'the_content', function( $content ) {
	$id = zad_opt( 'zad_trustindex' );
	if ( $id && is_singular() && is_main_query() && shortcode_exists( 'trustindex' ) ) {
		$content .= '<div class="trustindex-container">' . do_shortcode( '[trustindex data-widget-id="' . esc_attr( $id ) . '"]' ) . '</div>';
	}
	return $content;
}, 5 );
