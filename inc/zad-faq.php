<?php defined( 'ABSPATH' ) || exit;
/** FAQ network: one page per question (zad_faq) linked to services. */

function zad_register_faq() {
	$fs = zad_slug( 'zad_faq_slug', 'faq' );
	register_post_type( 'zad_faq', array(
		'labels'       => array( 'name' => 'الأسئلة الشائعة', 'singular_name' => 'سؤال', 'add_new' => 'إضافة سؤال', 'add_new_item' => 'إضافة سؤال جديد', 'edit_item' => 'تعديل السؤال', 'all_items' => 'كل الأسئلة', 'menu_name' => 'الأسئلة' ),
		'public'       => true,
		'has_archive'  => $fs,
		'rewrite'      => array( 'slug' => $fs, 'with_front' => false ),
		'menu_icon'    => 'dashicons-editor-help',
		'menu_position'=> 7,
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'excerpt', 'revisions' ),
	) );
	register_taxonomy( 'faq_cat', 'zad_faq', array(
		'labels'            => array( 'name' => 'أقسام الأسئلة', 'singular_name' => 'قسم', 'menu_name' => 'الأقسام' ),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => $fs . '-category', 'with_front' => false ),
	) );
}
add_action( 'init', 'zad_register_faq', 11 );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_faq_meta', 'ربط السؤال بالخدمات', 'zad_faq_metabox', 'zad_faq', 'side', 'default' );
} );
function zad_faq_metabox( $post ) {
	wp_nonce_field( 'zad_faq_save', 'zad_faq_nonce' );
	$sel = array_map( 'intval', (array) get_post_meta( $post->ID, '_zad_faq_services', true ) );
	echo '<p>الإجابة المختصرة تُكتب في «المقتطف»، والشرح الكامل في المحتوى.</p><select name="zad_faq_services[]" multiple size="8" style="width:100%">';
	foreach ( get_posts( array( 'post_type' => 'zad_service', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $s ) {
		echo '<option value="' . (int) $s->ID . '"' . ( in_array( $s->ID, $sel, true ) ? ' selected' : '' ) . '>' . esc_html( $s->post_title ) . '</option>';
	}
	echo '</select>';
}
add_action( 'save_post_zad_faq', function ( $id ) {
	if ( ! isset( $_POST['zad_faq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_faq_nonce'] ) ), 'zad_faq_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$v = isset( $_POST['zad_faq_services'] ) ? array_map( 'strval', array_map( 'absint', (array) $_POST['zad_faq_services'] ) ) : array();
	update_post_meta( $id, '_zad_faq_services', $v );
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->is_post_type_archive( 'zad_faq' ) || $q->is_tax( 'faq_cat' ) ) {
		$q->set( 'posts_per_page', 24 );
		$q->set( 'orderby', 'title' );
		$q->set( 'order', 'ASC' );
		if ( ! empty( $_GET['q'] ) ) { // phpcs:ignore
			$q->set( 's', sanitize_text_field( wp_unslash( $_GET['q'] ) ) ); // phpcs:ignore
		}
	}
} );

/** FAQs linked to a service. */
function zad_service_faqs( $service_id, $limit = 6 ) {
	return new WP_Query( array(
		'post_type'      => 'zad_faq',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_zad_faq_services', 'value' => '"' . (int) $service_id . '"', 'compare' => 'LIKE' ) ),
	) );
}

/** QAPage schema on single questions. */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'zad_faq' ) ) {
		return;
	}
	$ans = wp_strip_all_tags( get_the_excerpt() ?: get_the_content() );
	zad_print_schema( array(
		'@context'   => 'https://schema.org',
		'@type'      => 'QAPage',
		'mainEntity' => array(
			'@type'          => 'Question',
			'name'           => get_the_title(),
			'answerCount'    => 1,
			'dateCreated'    => get_the_date( 'c' ),
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $ans, 'dateCreated' => get_the_modified_date( 'c' ), 'url' => get_permalink() ),
		),
	) );
	zad_print_schema( zad_crumbs_schema( array( array( 'الرئيسية', home_url( '/' ) ), array( 'الأسئلة', get_post_type_archive_link( 'zad_faq' ) ), array( get_the_title(), '' ) ) ) );
}, 21 );
