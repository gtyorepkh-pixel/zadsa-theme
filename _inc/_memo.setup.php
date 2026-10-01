<?php defined('ABSPATH') || exit;

if ( ! class_exists( 'MemoSetupTheme' ) ) :

    class MemoSetupTheme {

    // Initalize
    public function __construct() {

        self::includes();
        add_action( 'after_setup_theme', array( 'MemoSetupTheme', 'setup' ) );
        add_action( 'init', array( 'MemoSetupTheme', 'clean_head' ) );
        add_action('wp_enqueue_scripts', array( 'MemoSetupTheme', 'enqueue_assets' ), 100);
    }

    public static function setup() {
        add_theme_support('post-thumbnails');
        add_theme_support( 'automatic-feed-links' );

        add_theme_support( 'html5', array(
            // Any or all of these.
            'comment-list', 
            'comment-form',
            'search-form',
            'gallery',
            'caption',
        ) );
      add_theme_support( 'title-tag');
      add_theme_support( 'custom-logo' );
      add_theme_support( 'responsive-embeds' );
      add_image_size('zad-card', 640, 400, true);
            
      add_image_size('memo-cards-xlg', 894, 392, true);
      add_image_size('memo-cards-lg', 300, 200, true);
      add_image_size('memo-cards-sm', 90, 90, true);
        
        register_nav_menus(
            array(
                'mainmenu' => 'القائمه العلوية',
                'footermenu' => 'قائمة الفوتر',
                'footerinfo' => 'قائمة الفوتر الثانية',
                'legalmenu' => 'الروابط القانونية (أسفل الفوتر)',
            )
        );
    
   
        
    }
 
    public static function enqueue_assets()
    {
        wp_deregister_script('wp-mediaelement');
        wp_deregister_style('wp-mediaelement');
        wp_dequeue_style('global-styles');

        wp_enqueue_style('zad-fonts', 'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap', array(), null);
        wp_enqueue_style('zad-main', MEMO_THEME_URI . 'assets/css/zad.css', array(), ZAD_VERSION);

        wp_enqueue_script('zad-main', MEMO_THEME_URI . 'assets/js/zad.js', array(), ZAD_VERSION, true);
        wp_localize_script('zad-main', 'ZAD', array('ajax' => admin_url('admin-ajax.php')));
    }

    public static function clean_head() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action( 'wp_head', 'wp_generator' );
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    }

    public static function includes(){

        load_template( MEMO_THEME_DIR . '_inc/_memo_functions.php', true );
        load_template( MEMO_THEME_DIR . '_inc/_memo_walker.php', true );
                load_template( MEMO_THEME_DIR . '_inc/_memo_shortcode.php', true );

        
    }

   
 
}
    

endif;


return new MemoSetupTheme();