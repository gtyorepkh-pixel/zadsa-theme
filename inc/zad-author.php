<?php defined( 'ABSPATH' ) || exit;
/**
 * Authors: profile page (author.php), byline + box in articles, Person data, and the 301 for authors without a bio.
 * The bio is the WordPress profile field "معلومات السيرة الذاتية" (description).
 */

function zad_author_bio( $uid ) {
	return trim( (string) get_the_author_meta( 'description', $uid ) );
}
function zad_author_has_bio( $uid ) {
	return '' !== zad_author_bio( $uid );
}

/** Profile photo: the custom field on the user profile, else the site logo ('' when none). */
function zad_author_image( $uid ) {
	$u = trim( (string) get_user_meta( $uid, 'zad_author_image', true ) );
	if ( '' !== $u ) { return $u; }
	$logo = zad_opt( 'memopt_logo' );
	return ( is_array( $logo ) && ! empty( $logo['url'] ) ) ? $logo['url'] : '';
}

/** Profile links (website + social contact fields) as absolute URLs, for Person.sameAs. */
function zad_author_sameas( $uid ) {
	$out = array();
	$web = trim( (string) get_the_author_meta( 'user_url', $uid ) );
	if ( '' !== $web ) { $out[] = $web; }
	$hosts = array( 'twitter' => 'https://x.com/', 'facebook' => 'https://www.facebook.com/', 'instagram' => 'https://www.instagram.com/', 'linkedin' => 'https://www.linkedin.com/in/', 'youtube' => 'https://www.youtube.com/@' );
	foreach ( $hosts as $k => $base ) {
		$v = trim( (string) get_user_meta( $uid, $k, true ) );
		if ( '' === $v ) { continue; }
		$out[] = preg_match( '#^https?://#', $v ) ? $v : $base . ltrim( $v, '@/' );
	}
	return array_values( array_unique( array_filter( $out ) ) );
}

add_filter( 'user_contactmethods', function ( $m ) {
	foreach ( array( 'twitter' => 'X (تويتر)', 'facebook' => 'فيسبوك', 'instagram' => 'إنستغرام', 'linkedin' => 'لينكدإن', 'youtube' => 'يوتيوب' ) as $k => $l ) {
		if ( ! isset( $m[ $k ] ) ) { $m[ $k ] = $l; }
	}
	return $m;
} );
foreach ( array( 'show_user_profile', 'edit_user_profile' ) as $zad_h ) {
	add_action( $zad_h, function ( $user ) {
		echo '<h2>صورة الكاتب (الثيم)</h2><table class="form-table"><tr><th><label for="zad_author_image">رابط الصورة</label></th><td><input type="url" class="regular-text" name="zad_author_image" id="zad_author_image" value="' . esc_attr( get_user_meta( $user->ID, 'zad_author_image', true ) ) . '"><p class="description">تظهر في صفحة الكاتب وصندوقه. إن تُركت فارغة يظهر شعار الموقع. البايو من حقل «معلومات السيرة الذاتية» أعلاه؛ بدونه لا تُنشر صفحة الكاتب.</p></td></tr></table>';
	} );
}
foreach ( array( 'personal_options_update', 'edit_user_profile_update' ) as $zad_h ) {
	add_action( $zad_h, function ( $uid ) {
		if ( ! current_user_can( 'edit_user', $uid ) || ! isset( $_POST['zad_author_image'] ) ) { return; }
		update_user_meta( $uid, 'zad_author_image', esc_url_raw( wp_unslash( $_POST['zad_author_image'] ) ) );
	} );
}

/* Authors with no bio have no public page: 301 to the home page. */
add_action( 'template_redirect', function () {
	if ( is_author() && ! zad_author_has_bio( get_queried_object_id() ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

/* Author archive lists the author's articles of every article type. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_author() ) { return; }
	$types = array_unique( array_merge( array( 'post' ), function_exists( 'zad_article_types' ) ? zad_article_types() : array() ) );
	$q->set( 'post_type', $types );
	$q->set( 'posts_per_page', 12 );
} );

/** Reviewer of a service page: the theme setting (a user), else the page author. */
function zad_reviewer_id( $post = null ) {
	$post = get_post( $post );
	$uid  = (int) zad_opt( 'zad_reviewer', 0 );
	return ( $uid && get_userdata( $uid ) ) ? $uid : ( $post ? (int) $post->post_author : 0 );
}

/** «آخر تحديث: … · راجعه: …» under the service H1. */
function zad_eeat_line( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) { return ''; }
	$o = '<p class="eeat">آخر تحديث: <time datetime="' . esc_attr( get_post_modified_time( 'c', true, $post ) ) . '">' . esc_html( get_the_modified_date( 'j F Y', $post ) ) . '</time>';
	$uid = zad_reviewer_id( $post );
	if ( $uid ) {
		$name = get_the_author_meta( 'display_name', $uid );
		$o   .= ' · راجعه: ' . ( zad_author_has_bio( $uid ) ? '<a href="' . esc_url( get_author_posts_url( $uid ) ) . '" rel="author">' . esc_html( $name ) . '</a>' : esc_html( $name ) );
	}
	return $o . '</p>';
}

/** "بقلم {name}" under the article title (only when the author has a public page). */
function zad_author_byline( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) { return ''; }
	$uid = (int) $post->post_author;
	if ( ! $uid || ! zad_author_has_bio( $uid ) ) { return ''; }
	return '<p class="byline">بقلم <a href="' . esc_url( get_author_posts_url( $uid ) ) . '" rel="author">' . esc_html( get_the_author_meta( 'display_name', $uid ) ) . '</a></p>';
}

/** Author box at the end of an article (hidden without a bio). */
function zad_author_box( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) { return ''; }
	$uid = (int) $post->post_author;
	if ( ! $uid || ! zad_author_has_bio( $uid ) ) { return ''; }
	$img = zad_author_image( $uid );
	$o   = '<aside class="abox" aria-label="كاتب المقال">';
	if ( $img ) { $o .= '<img class="abox__img" src="' . esc_url( $img ) . '" alt="" width="72" height="72" loading="lazy" decoding="async">'; }
	$o  .= '<div class="abox__tx"><span class="abox__k">كاتب المقال</span><b class="abox__n">' . esc_html( get_the_author_meta( 'display_name', $uid ) ) . '</b><p>' . esc_html( zad_author_bio( $uid ) ) . '</p>';
	$o  .= '<a class="abox__all" href="' . esc_url( get_author_posts_url( $uid ) ) . '" rel="author">عرض كل مقالات الكاتب</a></div></aside>';
	return $o;
}
