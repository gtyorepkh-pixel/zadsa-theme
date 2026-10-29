<?php defined( 'ABSPATH' ) || exit;
/** Native WordPress settings. The existing option keys and row structures remain authoritative. */
final class Zad_Settings {
	public static $options = array();
	public static $sections = array();
	public static function createOptions( $prefix, $args ) { self::$options[ $prefix ] = $args; }
	public static function createSection( $prefix, $section ) { self::$sections[ $prefix ][] = $section; }
	public static function choices( $field ) {
		$options = $field['options'] ?? array();
		if ( is_array( $options ) ) { return $options; }
		if ( in_array( $options, array( 'pages', 'posts' ), true ) ) {
			$args = wp_parse_args( $field['query_args'] ?? array(), array( 'post_type' => 'pages' === $options ? 'page' : 'post', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
			$out = array();
			foreach ( get_posts( $args ) as $p ) { $out[ $p->ID ] = $p->post_title . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' ); }
			return $out;
		}
		if ( 'post_types' === $options ) { $out = array(); foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) { $out[ $type->name ] = $type->labels->singular_name; } return $out; }
		if ( 'users' === $options ) { $out = array(); foreach ( get_users( array( 'fields' => array( 'ID', 'display_name' ) ) ) as $user ) { $out[ $user->ID ] = $user->display_name; } return $out; }
		return array();
	}
	public static function clean( $field, $value, $old = null ) {
		$type = $field['type'] ?? 'text';
		if ( 'group' === $type ) {
			$rows = array();
			foreach ( is_array( $value ) ? $value : array() as $row ) {
				if ( ! is_array( $row ) ) { continue; }
				$key = isset( $row['_zad_row_key'] ) ? (string) $row['_zad_row_key'] : '';
				$prior = ctype_digit( $key ) && isset( $old[ (int) $key ] ) ? (array) $old[ (int) $key ] : array();
				$out = $prior;
				foreach ( $field['fields'] as $child ) {
					if ( empty( $child['id'] ) ) { continue; }
					$id = $child['id'];
					$out[ $id ] = self::clean( $child, $row[ $id ] ?? null, $prior[ $id ] ?? null );
				}
				unset( $out['_zad_row_key'] );
				$rows[] = $out;
			}
			return $rows;
		}
		if ( 'media' === $type ) {
			$v = is_array( $value ) ? $value : array(); $id = absint( $v['id'] ?? 0 );
			$url = esc_url_raw( (string) ( $v['url'] ?? '' ) );
			if ( $id && 'attachment' !== get_post_type( $id ) ) { $id = 0; }
			if ( $id ) { $url = wp_get_attachment_url( $id ) ?: $url; }
			$out = array_merge( is_array( $old ) ? $old : array(), array( 'id' => $id, 'url' => $url ) );
			if ( $id ) { $m = wp_get_attachment_metadata( $id ); $out['width'] = (int) ( $m['width'] ?? 0 ); $out['height'] = (int) ( $m['height'] ?? 0 ); $out['alt'] = (string) get_post_meta( $id, '_wp_attachment_image_alt', true ); }
			return $out;
		}
		if ( 'switcher' === $type || ( 'checkbox' === $type && empty( $field['options'] ) ) ) { return ! empty( $value ) ? '1' : false; }
		if ( 'select' === $type || 'checkbox' === $type ) {
			$choices = self::choices( $field );
			$valid = array_merge( array_map( 'strval', array_keys( $choices ) ), array_map( 'strval', (array) $old ), array( '' ) );
			if ( ! empty( $field['multiple'] ) || 'checkbox' === $type ) {
				return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', is_array( $value ) ? $value : array() ), function ( $v ) use ( $valid ) { return '' !== $v && in_array( $v, $valid, true ); } ) ) );
			}
			$v = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			return in_array( $v, $valid, true ) ? $v : ( $old ?? '' );
		}
		$v = is_scalar( $value ) ? (string) $value : '';
		if ( 'number' === $type ) { return '' === $v ? '' : ( is_numeric( $v ) ? 0 + $v : ( $old ?? '' ) ); }
		if ( 'color' === $type ) { return sanitize_hex_color( $v ) ?: ( $old ?? '' ); }
		if ( 'wp_editor' === $type ) { return wp_kses_post( $v ); }
		return 'textarea' === $type ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
	}
	public static function old_input( $field, $value ) {
		if ( 'group' !== ( $field['type'] ?? '' ) || ! is_array( $value ) ) { return $value; }
		foreach ( $value as $i => &$row ) { if ( is_array( $row ) ) { $row['_zad_row_key'] = (string) $i; foreach ( $field['fields'] as $child ) { if ( isset( $child['id'], $row[ $child['id'] ] ) ) { $row[ $child['id'] ] = self::old_input( $child, $row[ $child['id'] ] ); } } } } unset( $row ); return $value;
	}
	public static function field( $f, $value, $name, $template = false ) {
		$type = $f['type'] ?? 'text';
		if ( 'subheading' === $type ) { echo '<h2 class="zad-native-heading">' . wp_kses_post( $f['content'] ?? '' ) . '</h2>'; return; }
		$id = 'zn-' . substr( md5( $name ), 0, 12 );
		$dep = isset( $f['dependency'] ) ? ' data-dependency="' . esc_attr( wp_json_encode( $f['dependency'] ) ) . '"' : '';
		echo '<div class="zad-native-field" data-field="' . esc_attr( $f['id'] ?? '' ) . '"' . $dep . '><label for="' . esc_attr( $id ) . '"><b>' . esc_html( $f['title'] ?? '' ) . '</b></label>';
		if ( 'group' === $type ) {
			echo '<div class="zad-native-group" data-name="' . esc_attr( $name ) . '"><div data-rows>';
			foreach ( is_array( $value ) ? $value : array() as $i => $row ) { self::row( $f, $row, $name, $i, (string) $i ); }
			echo '</div><template data-row-template>'; self::row( $f, array(), $name, '__row__', '-1', true ); echo '</template>';
			echo '<button type="submit" class="button" data-add-row name="zad_row_action" value="' . esc_attr( ( $f['id'] ?? '' ) . '|add|0' ) . '">' . esc_html( $f['button_title'] ?? 'إضافة بند' ) . '</button></div>';
		} elseif ( 'media' === $type ) {
			$v = is_array( $value ) ? $value : array();
			echo '<div data-native-media><p data-media-preview>';
			if ( ! empty( $v['id'] ) ) { echo wp_get_attachment_image( $v['id'], 'thumbnail' ); }
			echo '</p><label>رقم الملف <input type="number" min="0" name="' . esc_attr( $name . '[id]' ) . '" value="' . esc_attr( $v['id'] ?? '' ) . '" data-media-id></label> <button type="button" class="button" data-media-pick>اختيار من مكتبة الوسائط</button><p><label>رابط الملف <input type="url" class="large-text" name="' . esc_attr( $name . '[url]' ) . '" value="' . esc_attr( $v['url'] ?? '' ) . '" data-media-url></label></p></div>';
		} elseif ( 'select' === $type || ( 'checkbox' === $type && ! empty( $f['options'] ) ) ) {
			$options = self::choices( $f ); $multi = ! empty( $f['multiple'] ) || 'checkbox' === $type;
			foreach ( (array) $value as $v ) { if ( '' !== (string) $v && ! isset( $options[ $v ] ) ) { $options[ $v ] = 'قيمة محفوظة: ' . $v; } }
			if ( $multi ) { $ordered = array(); foreach ( (array) $value as $selected_value ) { if ( isset( $options[ $selected_value ] ) ) { $ordered[ $selected_value ] = $options[ $selected_value ]; } } $options = $ordered + $options; }
			if ( $multi ) { echo '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value="">'; }
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '"' . ( $multi ? ' multiple size="6"' : '' ) . '><option value="">' . esc_html( $f['placeholder'] ?? '— اختر —' ) . '</option>';
			foreach ( $options as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( in_array( (string) $key, array_map( 'strval', (array) $value ), true ), true, false ) . '>' . esc_html( $label ) . '</option>'; }
			echo '</select>';
			if ( $multi && ! empty( $f['sortable'] ) ) { echo '<p><button type="button" class="button" data-select-order="up">تحريك الاختيار لأعلى</button> <button type="button" class="button" data-select-order="down">تحريك الاختيار لأسفل</button></p>'; }
		} elseif ( 'switcher' === $type || 'checkbox' === $type ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value=""><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $value ), true, false ) . '> تفعيل';
		} elseif ( 'textarea' === $type || 'wp_editor' === $type ) {
			if ( 'wp_editor' === $type && ! $template ) { wp_editor( (string) $value, $id, array( 'textarea_name' => $name, 'textarea_rows' => 8, 'media_buttons' => true ) ); }
			else { echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="large-text" rows="5">' . esc_textarea( (string) $value ) . '</textarea>'; }
		} else {
			$input = in_array( $type, array( 'color', 'number' ), true ) ? $type : 'text';
			echo '<input class="regular-text" id="' . esc_attr( $id ) . '" type="' . esc_attr( $input ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . ( 'number' === $input ? ' step="any"' : '' ) . '>';
		}
		if ( ! empty( $f['desc'] ) ) { echo '<p class="description">' . wp_kses_post( $f['desc'] ) . '</p>'; }
		echo '</div>';
	}
	public static function row( $f, $row, $name, $i, $key, $template = false ) {
		echo '<fieldset class="zad-native-row" data-row data-index="' . esc_attr( $i ) . '"><legend>بند</legend><input type="hidden" name="' . esc_attr( $name . '[' . $i . '][_zad_row_key]' ) . '" value="' . esc_attr( $key ) . '">';
		foreach ( $f['fields'] as $child ) { self::field( $child, $row[ $child['id'] ?? '' ] ?? ( $child['default'] ?? '' ), $name . '[' . $i . '][' . ( $child['id'] ?? '' ) . ']', $template ); }
		foreach ( array( 'up' => 'تحريك لأعلى', 'down' => 'تحريك لأسفل', 'delete' => 'حذف البند' ) as $action => $label ) {
			echo '<button class="button" type="submit" data-row-action="' . esc_attr( $action ) . '" name="zad_row_action" value="' . esc_attr( $f['id'] . '|' . $action . '|' . $i ) . '">' . esc_html( $label ) . '</button> ';
		}
		echo '</fieldset>';
	}
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$prefix = '_memo_theme_options'; $sections = self::$sections[ $prefix ] ?? array();
		$section = min( max( 0, absint( $_GET['section'] ?? 0 ) ), max( 0, count( $sections ) - 1 ) );
		$values = (array) get_option( $prefix, array() );
		echo '<div class="wrap"><h1>إعدادات زاد السعودية</h1><p>إعدادات أصلية؛ كل قسم يحفظ بياناته فقط.</p>';
		if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>تم حفظ القسم.</p></div>'; }
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $sections as $i => $s ) { echo '<a class="nav-tab' . ( $i === $section ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'memo-theme-options', 'section' => $i ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $s['title'] ) . '</a>'; }
		echo '</nav><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="zad_native_settings"><input type="hidden" name="section" value="' . (int) $section . '">';
		wp_nonce_field( 'zad_native_settings', 'zad_settings_nonce' );
		foreach ( $sections[ $section ]['fields'] ?? array() as $f ) {
			if ( isset( $f['id'] ) ) { echo '<input type="hidden" name="zad_present[' . esc_attr( $f['id'] ) . ']" value="1">'; }
			self::field( $f, $values[ $f['id'] ?? '' ] ?? ( $f['default'] ?? '' ), 'zad_values[' . ( $f['id'] ?? '' ) . ']' );
		}
		echo '<input type="hidden" name="zad_form_complete" value="1">'; submit_button( 'حفظ هذا القسم' ); echo '</form></div>';
	}
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['zad_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_settings_nonce'] ) ), 'zad_native_settings' ) ) { wp_die( 'غير مصرح', '', array( 'response' => 403 ) ); }
		if ( empty( $_POST['zad_form_complete'] ) ) { wp_die( 'لم تصل جميع الحقول. لم يتم تغيير الإعدادات؛ قلّل عدد البنود أو ارفع حد حقول النموذج في إعدادات السيرفر.' ); }
		$prefix = '_memo_theme_options'; $section = absint( $_POST['section'] ?? 0 );
		$sections = self::$sections[ $prefix ] ?? array(); if ( ! isset( $sections[ $section ] ) ) { wp_die( 'قسم غير موجود' ); }
		$old = (array) get_option( $prefix, array() ); $out = $old;
		$values = wp_unslash( (array) ( $_POST['zad_values'] ?? array() ) ); $present = (array) ( $_POST['zad_present'] ?? array() );
		$operation = explode( '|', sanitize_text_field( wp_unslash( $_POST['zad_row_action'] ?? '' ) ) );
		foreach ( $sections[ $section ]['fields'] as $f ) {
			$id = $f['id'] ?? ''; if ( ! $id || ! isset( $present[ $id ] ) ) { continue; }
			$clean = self::clean( $f, $values[ $id ] ?? null, $old[ $id ] ?? null );
			$normalized_old = self::clean( $f, self::old_input( $f, $old[ $id ] ?? null ), $old[ $id ] ?? null );
			$out[ $id ] = isset( $old[ $id ] ) && ! ( false === $clean && '' === $old[ $id ] ) && $clean === $normalized_old ? $old[ $id ] : $clean;
			if ( 'group' === ( $f['type'] ?? '' ) && $id === ( $operation[0] ?? '' ) ) {
				$i = absint( $operation[2] ?? 0 ); $rows = $out[ $id ];
				if ( 'add' === ( $operation[1] ?? '' ) ) { $row = array(); foreach ( $f['fields'] as $child ) { if ( isset( $child['id'] ) ) { $row[ $child['id'] ] = $child['default'] ?? ''; } } $rows[] = $row; }
				elseif ( isset( $rows[ $i ] ) && 'delete' === $operation[1] ) { array_splice( $rows, $i, 1 ); }
				elseif ( isset( $rows[ $i ] ) ) { $next = 'up' === $operation[1] ? $i - 1 : $i + 1; if ( isset( $rows[ $next ] ) ) { $tmp = $rows[ $next ]; $rows[ $next ] = $rows[ $i ]; $rows[ $i ] = $tmp; } }
				$out[ $id ] = $rows;
			}
		}
		update_option( $prefix, $out );
		wp_safe_redirect( add_query_arg( array( 'page' => 'memo-theme-options', 'section' => $section, 'saved' => 1 ), admin_url( 'admin.php' ) ) ); exit;
	}
}
add_action( 'admin_menu', function () { add_menu_page( 'إعدادات زاد', 'إعدادات القالب', 'manage_options', 'memo-theme-options', array( 'Zad_Settings', 'page' ), 'dashicons-admin-generic', 2 ); } );
add_action( 'admin_post_zad_native_settings', array( 'Zad_Settings', 'save' ) );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_memo-theme-options' !== $hook ) { return; }
	wp_enqueue_media();
	wp_enqueue_style( 'zad-native-settings', ZAD_THEME_URI . 'assets/css/zad-admin-native.css', array(), ZAD_VERSION );
	wp_enqueue_script( 'zad-native-settings', ZAD_THEME_URI . 'assets/js/zad-admin-native.js', array(), ZAD_VERSION, true );
} );
