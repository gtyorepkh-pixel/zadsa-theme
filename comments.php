<?php defined( 'ABSPATH' ) || exit;

if ( post_password_required() ){
	return;
}
if ( have_comments() || comments_open() ) : ?>
<div id="comments" class="comments-area mb-b-30 ">
    <?php if ( have_comments() ) : ?>
    <div class="comments-box mb-30 p-16 box-bg">
        <div class="block-head">
            <div class="h3 subtitle">
                <?php $comments_number = get_comments_number();
							if ( $comments_number > 1 ){
								printf( esc_html__( '%s تعليقات'), get_comments_number_text( '0', '1', '%' ) );
							}
							else {
								esc_html_e( 'تعليق واحد');
							}
						?>
            </div>
        </div>
        <?php the_comments_navigation(); ?>
        <ol class="comment-list unstyled">
            <?php
						wp_list_comments( array(
							'style'       => 'ol',
							'short_ping'  => true,
                            'callback' => 'better_commets'
						) );
					?>
        </ol>
        <?php the_comments_navigation(); ?>
    </div>
    <?php endif; ?>
    <div class="mb-30 p-16 box-bg">
        <?php comment_form( array( 
        'title_reply_before' => '<div class="h3 subtitle" >',
        'title_reply_after' => '</div>'
        
        ) ); ?>
    </div>

</div>

<?php endif; ?>