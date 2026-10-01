<?php defined('ABSPATH') || exit;



if (!function_exists('memo_get_img')) :
    function memo_get_img($name)
    {
        return  esc_url(MEMO_THEME_URI .'assets/img/'.$name);
    }
endif;
if (!function_exists('memo_get_svg'))  :
    function memo_get_svg($args = array())
    {
        if (!empty($args)) {
            global $memo_icons;
            $defaults = array(
                'icon'     => '',
                'title'    => '',
                'width'     => '',
                'fill'     => '',
                'viewbox'     => '0 0 24 24',
                'height' => false,
            );
            $args = wp_parse_args($args, $defaults);
            $svg = '';
            if (array_key_exists($args['icon'], $memo_icons)) {
            $repl = sprintf(
                '<svg class="%s" xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" fill="%s" role="img" focusable="false" aria-hidden="true" viewBox="'.trim($args['viewbox']).'"> '.trim($memo_icons[$args['icon']]).
                '</svg>', $args['icon'] , $args['width'], $args['height'], $args['fill']);
            }
            return $repl;
        }
    }
endif;

if (!function_exists('memo_breadcrumbs')) :
    function memo_breadcrumbs()
    {
        global $post;
       
        $star = '<div class="breadcrumbs"><ol>';
        $end = '</ol></div>';
        $before = '<li>';
        $after = '</li>';
        $home = $before . '<a href="' . esc_url(home_url('/')) . '" title="عودة إلى الصفحة الرئيسية"><i class="icon icon-home"></i></a>' . $after;
        $breadcrumbs =  $home;
        if (is_single()) {
            $category = get_the_category();
            @$cat_name = $category[0]->name;
            if ($post->post_type === 'blogs') {
				
				$breadcrumbs .= $before.'<span class="current"><a href="'.esc_url( home_url( '/' )).'blogs/" >المدونة</a></span>'.$after;
				
				
				$post_type = get_post_type_object(get_post_type());
				$slug = $post_type->rewrite;
				$queried_object = get_object_taxonomies($slug['slug']);
				$queried_objectd = @$queried_object[0];
				$terms = get_the_terms( $post->ID, $queried_objectd );
				if ($terms) {
					foreach ($terms as $term) {
						$breadcrumbs .= $before.'<a href="'.get_term_link($term->term_id, $queried_objectd).'" >'.$term->name.'</a>'.$after;
					}
				}
                
            
             }else{
                if (!empty($category)) {
                    if ($category[0]->parent != 0) {
                        $breadcrumbs .= $before .  get_category_parents($category[0]->parent, TRUE, '') . $after;
                    }
                    $breadcrumbs .= $before .  '<a href="' . get_category_link($category[0]->term_id) . '" >' . $cat_name . '</a>' . $after;
                }
                
             }
             
            $breadcrumbs .= $before . '<span class="current">' . get_the_title() . '</span>' . $after;
        } elseif (is_category()) {
            $category = get_query_var('cat');
            $category = get_category($category);
            $cat_name = $category->name;
            if ($category->parent !== 0) {
                $breadcrumbs .= $before .  get_category_parents($category->parent, TRUE, '') . $after;
            }
            $breadcrumbs .= $before .  '<span class="current">' . $cat_name . '</span>' . $after;
        } elseif (is_tag()) {
            $breadcrumbs .= $before .  '<span class="current">' . single_tag_title('', false) . '</span>' . $after;
        } elseif (is_author()) {
            $breadcrumbs .= $before .  '<span class="current">' . get_the_author() . '</span>' . $after;
        } elseif (is_search()) {
            $breadcrumbs .= $before .  '<span class="current">نتائج البحث عن :' . get_search_query() . '</span>' . $after;
        } elseif (is_page()) {
            $breadcrumbs .= $before .  '<span class="current">' . get_the_title() . '</span>' . $after;
        } elseif (is_home()) {
            $breadcrumbs .= $before .  '<span class="current">' . single_post_title('', false) . '</span>' . $after;
        } elseif (is_404()) {
            $breadcrumbs = $before . '<span class="separator"> < </span><a href="' . esc_url(home_url('/')) . '">عودة الى الصفحة الرئيسية</a>' . $after;
            return $star . $breadcrumbs . $end;
        } else {
            $breadcrumbs .= $before . '<span class="current">' . get_the_title() . '</span>' . $after;
        }
        return $star  . $breadcrumbs . $end;
    }
endif;




if (!function_exists('memo_sharing_buttons')) :
    function memo_sharing_buttons()
    {
        global $post;
        $content = '';
        $URL = urlencode(get_permalink());
        $titleShare = htmlspecialchars(urlencode(html_entity_decode(get_the_title(), ENT_COMPAT, 'UTF-8')), ENT_COMPAT, 'UTF-8');
        $twitter = 'https://twitter.com/intent/tweet?text=' . $titleShare . '&amp;url=' . $URL . '';
        $facebook = 'https://www.facebook.com/sharer/sharer.php?u=' . $URL;
        $messenger = 'fb-messenger://share/?link=' . $URL;
        $whatsapp = 'whatsapp://send?text=' . $URL;
        $telegram = 'https://telegram.me/share/url?url=' . $URL . '&amp;title=' . $titleShare;
        $content .= '
  <div class="post-share">
  <strong><i class="icon icon-reply"></i> مشاركة المقال</strong>
  <div class="social-share-innr">
    <a rel="nofollow noopener noreferrer" class="facebook" title="شارك على فيسبوك" href="' . $facebook . '" target="_blank">
    <i class="icon icon-facebook-alt"></i>
    <span class="screen-reader">فيسبوك</span></a>
    <a rel="nofollow noopener noreferrer" class="whatsapp" title="شارك على واتساب" href="' . $whatsapp . '" target="_blank">
    <i class="icon icon-whatsapp"></i>
    <span class="screen-reader">واتساب</span></a>
    <a rel="nofollow noopener noreferrer" class="twitter"  title="شارك على تويتر"  href="' . $twitter . '"  target="_blank" >
    <i class="icon icon-twitter"></i>
    <span class="screen-reader">تويتر</span></a>
   
    </div>
  </div>';
        return $content;
    }
endif;
if (!function_exists('memo_tags_in')) :
    function memo_tags_in()
    {
        global $memo_options; 
        $posted_in = '';
        $tag_list = get_the_tag_list('', ' ');
        if ($tag_list) {
            $posted_in .=  '<div class="footer-tags p-16 box-bg mb-30"><strong>كلمات مفتاحية : </strong> %2$s</div>';
        }
            printf(
                $posted_in,
                get_the_category_list(', '),
                $tag_list,
                esc_url(get_permalink()),
                the_title_attribute('echo=0')
            );
    };
endif;

if (!function_exists('memo_get_phone')) {
    function memo_get_phone()
    {
        global $post;
        $page_options = get_post_meta( get_the_ID(), '_memo_metabox_options', true );
        $options = get_option( '_memo_theme_options' );
        
       if (@$page_options) {
        if (!empty(@$page_options['memo_single_phone']) ) {
            return trim(@$page_options['memo_single_phone']);
        }else {
            return $options['memopt_phone'];
        }
    } else {
        return $options['memopt_phone'];
    }
        
    }
}
if (!function_exists('memo_get_whatsapp')) {
    function memo_get_whatsapp()
    {
        global $post;
         $page_options = get_post_meta( get_the_ID(), '_memo_metabox_options', true );
        $options = get_option( '_memo_theme_options' );
         if (@$page_options) {

        if (!empty(@$page_options['memo_single_whatss']) ) {
            return trim(@$page_options['memo_single_whatss']);
        } else {
            return $options['memopt_whatsapp'];
        }
    } else {
        return $options['memopt_whatsapp'];
    }
    }
}

if (!function_exists('memo_pagination')) :

    function memo_pagination()
    {
        global $wp_query;
        $all_pages = $wp_query->max_num_pages; // Get All Posts
        $current_page = max(1, get_query_var('paged')); // Get current page
        if ($all_pages > 1) { // check if total pages
            return '<div class="pagination">' . paginate_links(array(
                'mid_size' => 1,
                'end_size' => 2,
            )) . '</div>';
        }
    }

endif;


if( ! function_exists( 'better_commets' ) ):
    function better_commets($comment, $args, $depth) {
                ?>
    <li <?php comment_class(); ?> id="li-comment-<?php comment_ID() ?>">
        <div class="comment-avatar">
            <?php echo get_avatar($comment,$size='40' ); ?>
        </div>
        <div class="comment-block">
            <?php if ($comment->comment_approved == '0') : ?>
            <em>تعليقك ينتظر الموافقة عليه</em>
            <br />
            <?php endif; ?>
            <span class="comment-by">
                <strong><?php echo get_comment_author() ?></strong>
                <span
                    class="comment-date"><?= sprintf( esc_html__( 'منذ %s', 'textdomain' ), human_time_diff(get_comment_time ( 'U' ), current_time( 'timestamp' ) ) ); ?></span>
                <span><?php comment_reply_link(array_merge( $args, array('depth' => $depth, 'max_depth' => $args['max_depth']))) ?></span>
    
    
    
            </span>
            <div class="comment-excerpt">
    
            </div>
            <?php comment_text() ?>
    
        </div>
    
        <?php } endif;






  if (!function_exists('memo_tags_in')) :
    function memo_tags_in()
    {
        $posted_in = '';
        $tag_list = get_the_tag_list('', ' ');
        if ($tag_list) {
            $posted_in .=  '<div class="footer-tags p-16 box-bg mb-30">%2$s</div>';
        }
            printf(
                $posted_in,
                get_the_category_list(', '),
                $tag_list,
                esc_url(get_permalink()),
                the_title_attribute('echo=0')
            );
    };
endif;