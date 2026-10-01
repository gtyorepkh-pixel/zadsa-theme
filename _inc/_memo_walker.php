<?php defined( 'ABSPATH' ) || exit;
class memo_walker extends Walker_Nav_Menu {

	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0){


		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';


		$class_names = ( ! empty( $item->current ) || ! empty( $item->current_item_ancestor ) ) ? 'current ' : '';
		$class_names .= @$args->walker->has_children  ? 'has-children ' : '' ;
		$is_mega = ( 0 === $depth && ! empty( $args->theme_location ) && 'mainmenu' === $args->theme_location && function_exists( 'zad_mega_html' ) && ! empty( $item->url ) && untrailingslashit( $item->url ) === untrailingslashit( (string) get_post_type_archive_link( 'zad_service' ) ) );
		if ( $is_mega ) { $class_names .= 'has-mega '; }
		$class_names  = trim( $class_names );
		$class_names = ! empty( $class_names ) ? ' class="'.$class_names.'" ' : '';
		$output .= $indent . "\n<li" . $class_names .">\n";
		$attributes  = '';
		$attributes .= ! empty( $item->target ) ? ' target="' . esc_attr( $item->target ) .'"' : '';
		$attributes .= ! empty( $item->xfn ) ? ' rel="' . esc_attr( $item->xfn ) .'"' : '';
		$attributes .=  ! empty( $item->url ) ?  ' href="'.esc_attr( $item->url ).'"' : '';
		$attributes .=  ! empty( $item->attr_title ) ?  ' title="'.esc_attr( $item->attr_title ).'"' : '';
		$attributes  = trim( $attributes );
		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$arrow = ( @$args->walker->has_children && $args->theme_location == 'mainmenu' ? '<button type="button" class="submenu-toggle" aria-expanded="false">' . zad_icon( 'chevron', 18 ) . '<span class="sr">توسيع القائمة الفرعية</span></button>' : '' ) ;
		@$item_output = "$args->before<a $attributes>$args->link_before$title</a>"."$arrow$args->link_after$args->after";

		$output .= apply_filters(
			'walker_nav_menu_start_el',
			   $item_output,
			   $item,
			   $depth,
			   $args
		);
		if ( $is_mega ) {
			$output .= zad_mega_html();
		}
	}

	public function start_lvl( &$output, $depth = 0, $args = array() ){
		$indent = str_repeat("\t", $depth);
        $output .= "\n$indent<ul class=\"sub-menu\">\n";
	}

	public function end_lvl( &$output, $depth = 0, $args = array() ){
		$indent = str_repeat("\t", $depth);
	    $output .= "$indent</ul>\n";
	}
	
	public function end_el(&$output, $item, $depth = 0, $args = array() ){
		$output .= "</li>\n";
        
	}
    public static function fallback( $args ) {
  
        $defaults = array(
            'container'       => 'div',
            'container_id'    => false,
            'container_class' => false,
            'menu_class'      => 'menu',
            'menu_id'         => false,
        );
        $args     = wp_parse_args( $args, $defaults );
        if ( !empty( $args['container'] ) ) {
          echo sprintf( '<%s id="dddd %s" class="%s">', $args['container'], $args['container_id'], $args['container_class'] );
        }
        echo sprintf( '<ul id="ddddd %s" class="%s">', $args['container_id'], $args['container_class'] ) .
        '<li class="nav-item">' .
        '<a href="' . admin_url( 'nav-menus.php' ) . '" class="nav-link">' . __( 'Add a menu' ) . '</a>' .
        '</li></ul>';
        if ( !empty( $args['container'] ) ) {
          echo sprintf( '</%s>', $args['container'] );
        }
    }
	}