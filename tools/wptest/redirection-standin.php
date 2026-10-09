<?php
/**
 * TEST-ONLY stand-in for the Redirection plugin (the real one cannot be downloaded in the sandbox). It mimics the data model and the API the theme uses:
 *   tables {prefix}redirection_groups / {prefix}redirection_items (same column names), Red_Group::create(), Red_Item::create()/get_by_id()/->delete()/->get_id(),
 * and the runtime behaviour (exact-path 301, trailing-slash and case tolerant, regex rows). It is NOT the real plugin: the owner must still check the real one.
 * Set the option  standin_off=1  to simulate "Redirection not active" (the classes are then not defined).
 */
global $wpdb;
if ( ! get_option( 'standin_off' ) ) { // (classes inside the block are declared at run time, so the switch really removes them)
$G = $wpdb->prefix . 'redirection_groups'; $I = $wpdb->prefix . 'redirection_items';
if ( ! get_option( 'standin_tables' ) ) {
	$wpdb->query( "CREATE TABLE IF NOT EXISTS $G (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50), tracking INT DEFAULT 1, module_id INT DEFAULT 1, status VARCHAR(10) DEFAULT 'enabled', position INT DEFAULT 0)" );
	$wpdb->query( "CREATE TABLE IF NOT EXISTS $I (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT, match_url VARCHAR(2000), match_data TEXT, regex INT DEFAULT 0, position INT DEFAULT 0, last_count INT DEFAULT 0, last_access TEXT, group_id INT DEFAULT 0, status VARCHAR(10) DEFAULT 'enabled', action_type VARCHAR(20), action_code INT DEFAULT 301, action_data TEXT, match_type VARCHAR(20), title TEXT)" );
	update_option( 'standin_tables', 1 );
}
class Red_Group {
	public $id;
	public static function create( $name, $module ) { global $wpdb; $wpdb->insert( $wpdb->prefix . 'redirection_groups', array( 'name' => $name, 'module_id' => $module ) ); $g = new self(); $g->id = (int) $wpdb->insert_id; return $g; }
	public function get_id() { return $this->id; }
}
class Red_Item {
	public $id;
	public static function create( $d ) {
		global $wpdb;
		$url = (string) $d['url']; $to = is_array( $d['action_data'] ) ? ( $d['action_data']['url'] ?? '' ) : (string) $d['action_data'];
		$wpdb->insert( $wpdb->prefix . 'redirection_items', array( 'url' => $url, 'match_url' => strtolower( rtrim( $url, '/' ) ), 'regex' => ! empty( $d['regex'] ) ? 1 : 0, 'group_id' => (int) $d['group_id'], 'status' => 'enabled',
			'action_type' => $d['action_type'], 'action_code' => (int) $d['action_code'], 'action_data' => $to, 'match_type' => $d['match_type'], 'title' => $d['title'] ?? '' ) );
		$i = new self(); $i->id = (int) $wpdb->insert_id; return $i;
	}
	public static function get_by_id( $id ) { global $wpdb; $r = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'redirection_items WHERE id = %d', $id ) ); if ( ! $r ) { return false; } $i = new self(); $i->id = (int) $r->id; return $i; }
	public function get_id() { return $this->id; }
	public function delete() { global $wpdb; $wpdb->delete( $wpdb->prefix . 'redirection_items', array( 'id' => $this->id ) ); }
}
// runtime: same job as the real plugin (before WordPress routes the request)
add_action( 'init', function () {
	if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) { return; }
	global $wpdb;
	$path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); $dec = rawurldecode( $path ); $key = strtolower( rtrim( $dec, '/' ) );
	$I = $wpdb->prefix . 'redirection_items';
	foreach ( (array) $wpdb->get_results( "SELECT * FROM $I WHERE status = 'enabled' ORDER BY id ASC" ) as $r ) {
		$hit = $r->regex ? (bool) @preg_match( '@' . str_replace( '@', '\@', $r->url ) . '@i', $dec ) : ( strtolower( rtrim( rawurldecode( $r->url ), '/' ) ) === $key );
		if ( $hit && 'url' === $r->action_type ) { $to = $r->action_data; wp_redirect( 0 === strpos( $to, 'http' ) ? $to : home_url( $to ), (int) $r->action_code ); exit; }
	}
}, 1 );
}
