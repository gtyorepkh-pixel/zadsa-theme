<?php defined( 'ABSPATH' ) || exit;
/** Small template helpers for posts, search and comments (were memo_* in _inc/_memo_functions.php). Pagination: zad_pagination() in inc/zad-helpers.php. */

/** The post's tags as chips. */
function zad_post_tags() {
	$tags = get_the_tag_list( '', ' ' );
	if ( $tags ) {
		echo '<div class="footer-tags p-16 box-bg mb-30"><strong>كلمات مفتاحية : </strong> ' . $tags . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/** Share links (Facebook, WhatsApp, X) for the current post. */
function zad_share_buttons() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( html_entity_decode( get_the_title(), ENT_COMPAT, 'UTF-8' ) );
	$links = array(
		'facebook' => array( 'https://www.facebook.com/sharer/sharer.php?u=' . $url, 'فيسبوك', 'icon-facebook-alt' ),
		'whatsapp' => array( 'whatsapp://send?text=' . $url, 'واتساب', 'icon-whatsapp' ),
		'twitter'  => array( 'https://twitter.com/intent/tweet?text=' . $title . '&url=' . $url, 'تويتر', 'icon-twitter' ),
	);
	$o = '<div class="post-share"><strong><i class="icon icon-reply"></i> مشاركة المقال</strong><div class="social-share-innr">';
	foreach ( $links as $cls => $l ) {
		$o .= '<a rel="nofollow noopener noreferrer" class="' . esc_attr( $cls ) . '" title="شارك على ' . esc_attr( $l[1] ) . '" href="' . esc_url( $l[0], array( 'https', 'whatsapp' ) ) . '" target="_blank"><i class="icon ' . esc_attr( $l[2] ) . '"></i><span class="screen-reader">' . esc_html( $l[1] ) . '</span></a>';
	}
	return $o . '</div></div>';
}

/** Search button icon. */
function zad_search_icon() {
	return '<svg class="search" xmlns="http://www.w3.org/2000/svg" width="15" height="35" fill="#000" role="img" focusable="false" aria-hidden="true" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M19.466,17.502 L23.589,21.625 C24.131,22.167 24.131,23.046 23.589,23.589 C23.047,24.130 22.167,24.131 21.625,23.589 L17.502,19.464 C17.100,19.062 16.998,18.475 17.193,17.977 L16.412,17.196 C12.546,20.652 6.588,20.526 2.876,16.815 C-0.966,12.972 -0.966,6.718 2.876,2.875 C6.719,-0.968 12.973,-0.968 16.816,2.875 C20.527,6.585 20.654,12.545 17.197,16.411 L17.978,17.192 C18.477,16.998 19.064,17.101 19.466,17.502 ZM15.638,4.054 C12.444,0.859 7.248,0.859 4.054,4.054 C0.860,7.247 0.860,12.443 4.054,15.637 C7.248,18.830 12.444,18.830 15.638,15.637 C18.832,12.444 18.832,7.247 15.638,4.054 ZM2.912,10.785 C2.775,8.831 3.524,6.726 5.133,5.117 C6.722,3.530 8.790,2.778 10.721,2.889 C9.016,3.519 7.395,4.532 6.000,5.928 C4.568,7.360 3.539,9.030 2.912,10.785 Z"/></svg>';
}

/** One comment in the list (wp_list_comments callback). */
function zad_comment_item( $comment, $args, $depth ) {
	?>
	<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
		<div class="comment-avatar"><?php echo get_avatar( $comment, 40 ); ?></div>
		<div class="comment-block">
			<?php if ( '0' === (string) $comment->comment_approved ) : ?><em>تعليقك ينتظر الموافقة عليه</em><br /><?php endif; ?>
			<span class="comment-by">
				<strong><?php echo esc_html( get_comment_author() ); ?></strong>
				<span class="comment-date"><?php echo esc_html( sprintf( 'منذ %s', human_time_diff( get_comment_time( 'U' ), current_time( 'timestamp' ) ) ) ); ?></span>
				<span><?php comment_reply_link( array_merge( $args, array( 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?></span>
			</span>
			<?php comment_text(); ?>
		</div>
	<?php
}
