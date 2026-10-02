<?php defined( 'ABSPATH' ) || exit;
/**
 * Storefront engine room
 *
 * @package memohost
 */

define('MEMO_VERSION_VERSION', '1.1');
define( 'ZAD_VERSION', '3.0.0' );
define( 'MEMO_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'MEMO_THEME_URI', trailingslashit( esc_url( get_template_directory_uri() ) ) );

// تعطيل إنشاء robots.txt الافتراضي في ووردبريس
add_filter( 'robots_txt', function( $output, $public ) {
    // نعيد ما في الملف الفعلي robots.txt إن وجد
    $file = ABSPATH . 'robots.txt';
    if ( file_exists( $file ) ) {
        return file_get_contents( $file );
    }
    return $output;
}, 10, 2 );

// Theme setup
require_once MEMO_THEME_DIR .'_inc/_memo.setup.php';
require_once MEMO_THEME_DIR .'_inc/_memo_icons.php';
require_once MEMO_THEME_DIR .'inc/zad-helpers.php';
require_once MEMO_THEME_DIR .'inc/zad-roles.php';
require_once MEMO_THEME_DIR .'inc/zad-legacy.php';
require_once MEMO_THEME_DIR .'inc/zad-cpt.php';
require_once MEMO_THEME_DIR .'inc/zad-leads.php';
require_once MEMO_THEME_DIR .'inc/zad-city.php';
require_once MEMO_THEME_DIR .'inc/zad-sc.php';
require_once MEMO_THEME_DIR .'inc/zad-schema.php';
require_once MEMO_THEME_DIR .'inc/zad-seo.php';
require_once MEMO_THEME_DIR .'inc/zad-suite.php';
require_once MEMO_THEME_DIR .'inc/zad-frontend.php';
require_once MEMO_THEME_DIR .'inc/zad-faq.php';
require_once MEMO_THEME_DIR .'inc/zad-wizard.php';
require_once MEMO_THEME_DIR .'inc/zad-ix.php';
require_once MEMO_THEME_DIR .'inc/zad-work.php';
require_once MEMO_THEME_DIR .'inc/zad-trust.php';
require_once MEMO_THEME_DIR .'inc/zad-home-defaults.php';
require_once MEMO_THEME_DIR .'inc/zad-move.php';
require_once MEMO_THEME_DIR .'inc/zad-seed-pillar.php';
require_once MEMO_THEME_DIR .'inc/zad-tts.php';
require_once MEMO_THEME_DIR .'inc/zad-listen.php';
require_once MEMO_THEME_DIR .'inc/zad-sitemap.php';
require_once MEMO_THEME_DIR .'inc/zad-demo.php';
require_once MEMO_THEME_DIR .'inc/zad-diag.php';
require_once get_theme_file_path() .'/_inc/_admin/admin-options.php';

$GLOBALS['memo_theme_options'] = get_option( '_memo_options' );
$memotheme_options = $GLOBALS['memo_theme_options'];
global $memotheme_options;

// إخفاء الصورة البارزة في الصفحات والمقالات المفردة
function zadksa_hide_featured_single() {
    if (is_single() || is_page()) {
        add_filter('post_thumbnail_html', '__return_false', 999);
    }
}
add_action('wp_head', 'zadksa_hide_featured_single');

/**
 * وظائف الأرشفة والظهور في التصنيفات
 */
function show_all_post_types_in_taxonomy_archives( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( $query->is_category() || $query->is_tag() ) {
        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );
        $query->set( 'post_type', $post_types );
    }
}
add_action( 'pre_get_posts', 'show_all_post_types_in_taxonomy_archives' );

// استبعاد مقالات التصنيفات الأبناء من الأب (لتنظيم الأرشفة)
function exclude_child_category_posts($query) {
    if ($query->is_category() && $query->is_main_query()) {
        $category = get_queried_object();
        if ($category->parent != 0) {
            return;
        }
        $child_categories = get_categories(array(
            'parent' => $category->term_id,
            'hide_empty' => false
        ));
        $exclude_ids = array();
        foreach ($child_categories as $child) {
            $exclude_ids[] = $child->term_id;
        }
        if (!empty($exclude_ids)) {
            $query->set('category__not_in', $exclude_ids);
        }
    }
}
add_action('pre_get_posts', 'exclude_child_category_posts');



// تعطيل Yoast JSON-LD إذا لزم الأمر
add_filter( 'wpseo_json_ld_output', '__return_false' );

/**
 * إضافة تواريخ التحديث (SEO) للمقالات المخصصة
 */
add_filter('the_content', 'add_enhanced_seo_dates_to_cpt');
function add_enhanced_seo_dates_to_cpt($content) {
    $target_post_types = array('pest_control', 'cleaning', 'sections', 'pests-library', 'guide'); 

    if (is_singular($target_post_types) && !zad_is_service() && !zad_is_faq() && is_main_query()) {
        $publish_date = get_the_date();
        $modified_date = get_the_modified_date();
        $u_time = get_the_time('U');
        $u_modified_time = get_the_modified_time('U');

        $date_html = '<div class="cpt-dates-box">';
        $date_html .= '<span class="publish-date"><strong>تاريخ النشر: </strong> ' . $publish_date . '</span>';

        if ($u_modified_time > $u_time + 86400) {
            $date_html .= '<br><span class="modified-date"><strong>آخر تحديث: </strong> ' . $modified_date . '</span>';
        }
        $date_html .= '</div>';
        return $date_html . $content;
    }
    return $content;
}
add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
/**
 * إضافة صندوق الكاتب في نهاية المحتوى
 */
add_filter('the_content', 'add_author_box_to_all_site_content');
function add_author_box_to_all_site_content($content) {
    if (is_singular() && !zad_is_service() && !zad_is_faq() && is_main_query()) {
        $author_id    = get_the_author_meta('ID');
        $author_name  = get_the_author_meta('display_name');
        $author_bio   = get_the_author_meta('description');
        $author_url   = get_author_posts_url($author_id);
        $author_avatar = get_avatar($author_id, 90);

        $author_box = '<div class="author-box">';
        $author_box .= '<div class="author-avatar">' . $author_avatar . '</div>';
        $author_box .= '<div class="author-info">';
        $author_box .= '<h4>بقلم: <a href="' . $author_url . '">' . $author_name . '</a></h4>';
        
        if ($author_bio) {
            $author_box .= '<p>' . $author_bio . '</p>';
        } else {
            $author_box .= '<p>خبير محتوى في موقع زاد السعودية، متخصص في تقديم حلول منزلية فنية دقيقة بناءً على تجارب ميدانية.</p>';
        }
        
        $author_box .= '</div></div>'; 
        return $content . $author_box;
    }
    return $content;
}
// Trustindex widget (id configured in theme options) appended to singular content.
add_filter( 'the_content', 'insert_trustindex_custom_placement', 5 );
function insert_trustindex_custom_placement( $content ) {
	$id = zad_opt( 'zad_trustindex' );
	if ( $id && is_singular() && is_main_query() && shortcode_exists( 'trustindex' ) ) {
		$content .= '<div class="trustindex-container">' . do_shortcode( '[trustindex data-widget-id="' . esc_attr( $id ) . '"]' ) . '</div>';
	}
	return $content;
}
