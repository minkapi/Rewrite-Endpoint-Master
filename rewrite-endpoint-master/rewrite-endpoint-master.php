<?php
/*
Plugin Name: Rewrite Endpoint Master
Plugin URI: https://github.com/jim912/Rewrite-Endpoint-Master
Description: This plugin manage custom rewrite endpoints from admin panel.
Author: jim912
Version: 0.1
Author URI: http://www.warna.info/
*/
class Rewrite_Endpoint_Master {

	private $ep_types = array(
		EP_PERMALINK    => 'Permalink',
		EP_ATTACHMENT   => 'Attachment',
		EP_DATE         => 'Date',
		EP_YEAR         => 'Year',
		EP_MONTH        => 'Month',
		EP_DAY          => 'Day',
		EP_ROOT         => 'Root',
		EP_COMMENTS     => 'Comments',
		EP_SEARCH       => 'Search',
		EP_CATEGORIES   => 'Category',
		EP_TAGS         => 'Tag',
		EP_AUTHORS      => 'Author',
		EP_PAGES        => 'Pages',
		EP_ALL_ARCHIVES => 'Archives',
		EP_ALL          => 'All',
		0               => 'Custom',
	);
	
	public $endpoint_vars = array();
	private $endpoints = array();
	
	public function __construct() {
		add_action( 'init'                  , array( $this, 'register_post_type' ), 1 );
		add_action( 'init'                  , array( $this, 'add_endpoints' ), 1 );
		add_action( 'wp_insert_post'        , array( $this, 'update_ep_meta' ) );
		// TODO do flush_rewrite_rules after update endpoint-master post
//		add_action( 'transition_post_status', array( $this, 'flush_rewrite_rules' ), 10, 3 );
		// TODO Custom post_submit_meta_box lile Contact Form 7
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_ep_meta_box' ), 10, 2 );
		}
		// TODO i18n
		// TODO register_deactivation_hook
		register_uninstall_hook( __FILE__, array( 'Rewrite_Endpoint_Master', 'uninstall_plugin' ) );
	}
	
	
	public function register_post_type() {
		register_post_type(
			'endpoint-master',
			array(
				'public'  => false,
				'label'   => 'Endpoints',
				// TODO labels
				'labels'  => array(
				),
				'show_ui' => true,
				'show_in_menu' => 'options-general.php',
				'show_in_admin_bar' => false,
				'supports' => array( 'title' ),
				'rewrite'  => false,
			)
		);
	}
	
	
	public function add_endpoints() {
		global $wp_rewrite;
		if ( ! $wp_rewrite->using_permalinks() ) { return; }
		$this->endpoints = get_posts( array(
			'posts_per_page' => -1,
			'post_type'      => 'endpoint-master',
		) );

		if ( $this->endpoints ) {
			foreach ( $this->endpoints as $endpoint ) {
				if ( $endpoint->_ep_name && $endpoint->_ep_type ) {
					$query_var = $endpoint->_ep_query ? trim( $endpoint->_ep_query ) : null;
					$type = array_sum( $endpoint->_ep_type );
					if ( in_array( '0', $endpoint->_ep_type ) && $endpoint->_ep_custom ) {
						$type += $endpoint->_ep_custom;
					}
					$this->endpoint_vars[$endpoint->_ep_name] = $query_var;
					add_rewrite_endpoint( $endpoint->_ep_name, $type, $query_var );
				}
			}
		}
	}
	
	
	public function add_ep_meta_box( $post_type, $post ) {
		if ( 'endpoint-master' == $post_type ) {
			add_meta_box( 'ep_meta_box', 'Endpoint', array( $this, 'ep_meta_box' ), $post_type, 'normal', 'high');
		}
	}
	
	
	public function ep_meta_box() {
	global $post;
	$ep_name = $post->_ep_name ? $post->_ep_name : '';
	$ep_type = get_post_meta( $post->ID, '_ep_type', true ) ? get_post_meta( $post->ID, '_ep_type', true ) : array();
	$ep_custom = $post->_ep_custom ? $post->_ep_custom : '';
	$ep_query = $post->_ep_query ? $post->_ep_query : '';

?>
<dl>
	<dt>Endpoint Name</dt>
	<dd><input type="text" name="_ep_name" id="_ep_name" size="20" value="<?php echo esc_html( $ep_name ); ?>" /></dd>
	<dt>Endpoint Type</dt>
	<dd>
		<input type="hidden" name="_ep_type" value="">
		<ul>
<?php foreach ( $this->ep_types as $mask => $type ) : ?>
			<li>
				<label for="ep-type-<?php echo esc_attr( $mask ); ?>">
					<input type="checkbox" name="_ep_type[]" id="ep-type-<?php echo esc_attr( $mask ); ?>" value="<?php echo esc_attr( $mask ); ?>"<?php echo in_array( $mask, $ep_type ) ? ' checked="checked"' : ''; ?>>
					<?php echo esc_html( $type ); ?>
				</label>
				<?php if ( 0 == $mask ) : ?>
				<input type="number" name=_ep_custom id="_ep_custom" size="5" value="<?php echo esc_html( $ep_custom ); ?>" />
				<?php endif; ?>
			</li>
<?php endforeach; ?>
		</ul>
	</dd>
	<dt>Query Var</dt>
	<dd><input type="text" name="_ep_query" id="_ep_query" size="20" value="<?php echo esc_html( $ep_query ); ?>" /></dd>
</dl>
<?php
	}
	
	
	public function update_ep_meta( $post_ID ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( isset( $_POST['_ep_name'] ) ) {
			// TODO sanitize
			$ep_name = trim( stripslashes_deep( $_POST['_ep_name'] ) );
			update_post_meta( $post_ID, '_ep_name', $ep_name );
		} else {
			return;
		}

		if ( isset( $_POST['_ep_type'] ) ) {
			// TODO sanitize
			$ep_type = stripslashes_deep( $_POST['_ep_type'] );
			if ( is_array( $ep_type ) ) {
				$ep_type = array_map( 'trim', $ep_type );
			} else {
				$ep_type = trim( $ep_type );
			}
			update_post_meta( $post_ID, '_ep_type', $ep_type );
		}
		if ( isset( $_POST['_ep_custom'] ) ) {
			// TODO sanitize
			$ep_custom = trim( stripslashes_deep( $_POST['_ep_custom'] ) );
			update_post_meta( $post_ID, '_ep_custom', $ep_custom );
		}
		if ( isset( $_POST['_ep_query'] ) ) {
			// TODO sanitize
			$ep_query = trim( stripslashes_deep( $_POST['_ep_query'] ) );
			update_post_meta( $post_ID, '_ep_query', $ep_query );
		}
	}
	
	
	public function flush_rewrite_rules( $new_status, $old_status, $post ) {
		global $wp_rewrite;
		if ( $wp_rewrite->using_permalinks() && 'endpoint-master' == $post->post_type && in_array( 'publish', array( $new_status, $old_status ) ) ) {
			flush_rewrite_rules( false );
		}
	}
	
	
	static function uninstall_plugin() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { return; }

		$posts = get_posts( array(
			'posts_per_page' => -1,
			'post_type' => 'endpoint-master',
			'post_status' => 'any'
		) );
	
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}
	
} // class Rewrite_Endpoint_Master end.
$Rewrite_Endpoint_Master = new Rewrite_Endpoint_Master;


function is_endpoint_page( $ep_name ) {
	global $Rewrite_Endpoint_Master, $wp_query;
	return isset( $Rewrite_Endpoint_Master->endpoint_vars[$ep_name] ) && isset( $wp_query->query[$Rewrite_Endpoint_Master->endpoint_vars[$ep_name]] );
}


function get_ep_permalink( $post, $param = null ) {

}