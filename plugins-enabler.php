<?php
/*
Plugin Name: Plugins Enabler
Plugin URI: http://imathi.eu/2013/10/22/plugins-enabler-2-0/
Description: Adds a granular control on the visibility of plugins in your network (Multisite)
Version: 2.0
Requires at least: 3.7
Tested up to: 3.7
License: GPLv2 or later
Author: imath
Author URI: http://imathi.eu
Network: true
Text Domain: plugins-enabler
Domain Path: /languages/
*/

/**
 *
 *	Copyright (C) 2013 imath
 *
 *	This program is free software; you can redistribute it and/or
 *	modify it under the terms of the GNU General Public License
 *	as published by the Free Software Foundation; either version 2
 *	of the License, or (at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program; if not, write to the Free Software
 *	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Main class.
 *
 * @since  2.0
 */
class Plugins_Enabler {
	/**
	 * Returns the instance of this class.
	 *
	 * @since  2.0
	 *
	 * @return Plugins_Enabler The instance
	 */
	public static function instance() {

		static $instance = null;

		if ( null === $instance ) {
			$instance = new Plugins_Enabler;
			$instance->setup_globals();
			$instance->setup_hooks();
		}

		// Always return the instance
		return $instance;
	}

	/**
	 * Stores some globals for the plugin.
	 *
	 * @since  2.0
	 */
	private function setup_globals() {
		$this->version    = '2.0';
		$this->file       = __FILE__;
		$this->basename   = apply_filters( 'plugins_enabler_plugin_basename', plugin_basename( $this->file ) );
		$this->plugin_dir = apply_filters( 'plugins_enabler_dir_path', plugin_dir_path( $this->file ) );
		$this->plugin_url = apply_filters( 'plugins_enabler_dir_url',  plugin_dir_url ( $this->file ) );
		$this->domain     = 'plugins-enabler';

		// Dirs and urls
		$this->includes_dir = apply_filters( 'plugins_enabler_includes_dir', trailingslashit( $this->plugin_dir . 'includes'  ) );
		$this->includes_url = apply_filters( 'plugins_enabler_includes_url', trailingslashit( $this->plugin_url . 'includes'  ) );
		$this->js_url       = apply_filters( 'plugins_enabler_js_url', trailingslashit( $this->plugin_url . 'js'  ) );
		$this->css_url      = apply_filters( 'plugins_enabler_css_url', trailingslashit( $this->plugin_url . 'css'  ) );
		$this->lang_dir     = apply_filters( 'plugins_enabler_includes_dir', trailingslashit( $this->plugin_dir . 'languages'  ) );
		
	}

	/**
	 * Sets some key actions and filters
	 * 
	 * @since  2.0
	 */
	private function setup_hooks() {
		// Add menu item to plugins menu
		add_action( 'network_admin_menu',  array( $this, 'network_admin_menus' ) );

		// Adds notices if needed
		add_action( 'network_admin_notices',  array( $this, 'admin_messages' ) );

		// Ajax Actions
		add_action( 'wp_ajax_plugins_enabler_get_blogs', array( $this, 'list_blogs' ) );
		add_action( 'wp_ajax_plugins_enabler_get_plugins', array( $this, 'list_plugins' ) );
		add_action( 'wp_ajax_plugins_enabler_save_plugins', array( $this, 'save_plugins' ) );

		// Translation
		add_action( 'init', array( $this, 'load_translation' ), 11 );
		
		// Filters
		add_filter( 'all_plugins', array( $this, 'check_enabled' ), 10, 1 );
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * The menus !
	 * 
	 * @since  2.0
	 */
	public function network_admin_menus() {
		$screen = add_plugins_page( 
			__( 'Plugins Enabler Manager', 'plugins-enabler'), 
			__( 'Plugins Enabler Manager', 'plugins-enabler'), 
			'manage_network_plugins', 
			'plugins-enabler', 
			array( &$this, 'plugins_manager' )
		);

		$this->screen_id = $screen . '-network';

		add_action( 'load-' . $screen, array( $this, 'enqueue_scripts' ) );

		/* Taking care of previous versions */
		$db_version = get_option( 'plugins-enabler-version' );

		if( !empty( $db_version ) && version_compare( $db_version, $this->version, '<' ) ) {

			if( $need_upgrade = self::need_upgrade() ) {
				add_submenu_page(
					'upgrade.php',
					__( 'Update Plugins Enabler', 'plugins-enabler' ),
					__( 'Update Plugins Enabler', 'plugins-enabler' ),
					'manage_network',
					'plugins-enabler-update',
					array( $this, 'network_update_screen' )
				);
			}

		} else {
			// we store the db version to avoid this upgrade check.
			update_option( 'plugins-enabler-version', $this->version );
		}
		
	}

	/**
	 * The admin notices
	 * 
	 * @since  2.0
	 */
	public function admin_messages() {
		$notices = array();
		$upgrader = add_query_arg( array( 'page' => 'plugins-enabler-update' ), network_admin_url( 'upgrade.php' ) );
		$settings = network_admin_url( 'settings.php#menu');

		if( !function_exists( 'wp_get_sites' ) )
			$notices[] = __( 'Oops, Plugins Enabler requires at least WordPress 3.7, please upgrade !', 'plugins-enabler' );


		if( self::need_upgrade() )
			$notices[] = sprintf( __( 'Thanks for upgrading Plugins Enabler, you need to decide whether to upgrade plugins visibility settings for your sites, <a href="%s">please read this</a>', 'plugins-enabler' ), $upgrader );

		$menu_items = get_site_option( 'menu_items', array() );

		if( empty( $menu_items['plugins'] ) )
			$notices[] = sprintf( __( 'The goal of Plugins Enabler is to let admin view enabled plugins in their plugins menu, please check your <a href="%s">network options</a> to allow the plugins menu for sites.', 'plugins-enabler' ), $settings );

		if( count( $notices ) < 1 )
			return;
		?>
		<div id="message" class="updated fade">
		<?php foreach ( $notices as $notice ) : ?>
				<p><?php echo $notice ?></p>
		<?php endforeach ?>
		</div>
		<?php
	}

	/**
	 * Is this an upgrade ?
	 * 
	 * @since  2.0
	 */
	public static function need_upgrade() {
		global $wpdb;

		$old_table = $wpdb->base_prefix . 'plugins_enabler';
		$retval = false;

		if( 1 == get_option( 'plugins_enabler_upgraded' ) )
			return $retval;

		if( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $old_table ) ) != $old_table ) {
			// this way we'll performed above check once only !
			update_option( 'plugins_enabler_upgraded', 1 );
			return $retval;
		}
			

		if( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}plugins_enabler" ) > 0 )
			$retval = true;

		return $retval;
	}

	/**
	 * Screen to upgrade plugins visibility from previous version
	 * 
	 * @since  2.0
	 */
	public function network_update_screen() {
		global $wpdb;

		$upgrade = add_query_arg( array( 'page' => 'plugins-enabler-update', 'action' => 'do' ), network_admin_url( 'upgrade.php' ) );
		$skip = add_query_arg( array( 'page' => 'plugins-enabler-update', 'action' => 'skip' ), network_admin_url( 'upgrade.php' ) );
		$manager = add_query_arg( array( 'page' => 'plugins-enabler' ), network_admin_url( 'plugins.php' ) );
		?>
		<div id="wrap">
			<h2><?php esc_html_e( 'Plugins Enabler Upgrade', 'plugins-enabler' );?></h2>

			<?php if( empty( $_REQUEST['action'] ) ) :?>
				<div class="error inline below-h2">
					<p>
						<strong><?php esc_html_e( 'Warning', 'plugins-enabler');?>:</strong> 
						<?php esc_html_e( 'Using this tool will loop through the old plugin&#39;s table in order to create a new option in each site of the network. ', 'plugins-enabler');?>
						<?php esc_html_e( 'Please backup your files and database before upgrading. If you are not sure, you can skip this step, by manually resetting plugins visibility in Plugins Enabler manager plugins submenu.', 'plugins-enabler');?>
					</p>
				</div>
				<p>
					<a href="<?php echo $skip;?>" class="button button-secondary"><?php esc_html_e( 'Skip this step', 'plugins-enabler');?></a>&nbsp;
					<a href="<?php echo $upgrade;?>" class="button button-primary"><?php esc_html_e( 'Upgrade', 'plugins-enabler');?></a>
				</p>

			<?php else :
				
				switch( $_REQUEST['action'] ) {

					case 'skip' :
						update_option( 'plugins_enabler_upgraded', 1 );
						?>
						<p><?php printf( __( 'Preference saved, now you can <a href="%s">manage plugins visibility for regular admins</a>.', 'plugins-enabler' ), $manager );?></p>
						<?php
						break;

					case 'do' :
						$blogs = $wpdb->get_results( "SELECT blog_id as id, enabled_value as plugins FROM {$wpdb->base_prefix}plugins_enabler" );
						?>
						<p><?php esc_html_e( 'Processing.. Please wait', 'plugins-enabler' );?></p>
						<?php
						foreach( $blogs as $blog ) {
							if( !empty( $blog->plugins ) )
								update_blog_option( $blog->id, 'plugins_enabler_enabled_plugins', maybe_unserialize( $blog->plugins ) );
						}
						update_option( 'plugins_enabler_upgraded', 1 );
						?>
						<p><?php printf( __( 'Upgrade done, now you can <a href="%s">manage plugins visibility for regular admins</a>.', 'plugins-enabler' ), $manager );?></p>
						<p><?php printf( __( 'You can also drop the table named <code>%s</code> as it&#39;s not used anymore', 'plugins-enabler' ), $wpdb->base_prefix . 'plugins_enabler' );?></p>
						<?php
						break;
				}

				// finally upgraded !
				if( 1 == get_option( 'plugins_enabler_upgraded' ) )
					update_option( 'plugins-enabler-version', $this->version );
			
			endif;?>
		</div>
		<?php
	}

	/**
	 * Enqueues the scripts and css.
	 *
	 * @since 2.0
	 */
	public function enqueue_scripts() {

		if ( ! isset( get_current_screen()->id ) || get_current_screen()->id != $this->screen_id )
			return;

		wp_enqueue_style( 'plugins-enabler-css', $this->css_url . 'style.css', false, $this->version );
		wp_enqueue_script( 'plugins-enabler-js', $this->js_url . 'app.js', array( 'wp-util', 'backbone' ), $this->version, true );
		wp_localize_script( 'plugins-enabler-js', 'pluginsenabler_strings', array(
			'_penonce'       => wp_create_nonce( 'plugins-enabler-nonce' ),
			'cheating'       => __( 'Trying to cheat ?', 'plugins-enabler'),
			'loadingblogs'   => __( 'Loading Sites', 'plugins-enabler' ),
			'loadingplugins' => __( 'Loading Plugins', 'plugins-enabler' ),
			'limit'          => apply_filters( 'plugins_enabler_blogs_per_page', 20 )
		));
	}

	/**
	 * Prepare the json reply to list the blogs
	 *
	 * @since 2.0
	 */
	public function prepare_blogs_for_js( $blog = false ) {

		$response = array(
			'id'          => intval( $blog['blog_id'] ),
			'name'        => apply_filters( 'bloginfo', get_blog_option( $blog['blog_id'], 'blogname' ) ),
			'siteurl'     => esc_url( get_blog_option( $blog['blog_id'], 'siteurl' ) ),
			'adminurl'    => esc_url( get_admin_url( $blog['blog_id'] ) ),
			'admin_email' => sanitize_email( get_blog_option( $blog['blog_id'], 'admin_email' ) ),
			'mature'      => (bool) $blog['mature'],
			'public'      => (bool) $blog['public'],
			'registered'  => strtotime( $blog['registered'] ) * 1000,
		);

		return apply_filters( 'plugins_enabler_prepare_blogs_for_js', $response );
	}

	/**
	 * Query blogs and return a json reply
	 * 
	 * NB:  wp_get_sites has been introduced in WordPress 3.7
	 * 
	 * @since 2.0
	 */
	public function list_blogs() {
		check_ajax_referer( 'plugins-enabler-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network_plugins' ) )
			wp_send_json_error( __( 'You do not have the capacity to do that!', 'plugins-enabler' ) );

		if( !function_exists( 'wp_get_sites' ) )
			wp_send_json_error( __( 'Oops, this plugin requires at least WordPress 3.7, please upgrade !', 'plugins-enabler' ) );
		
		$args = !empty( $_POST['args'] ) ? (array) $_POST['args'] : array();
		
		$defaults = array(
			'limit'    => 20,
			'offset'   => 0,
			'archived' => false,
			'deleted'  => false,
			'spam'     => false,
		);

		$args = wp_parse_args( $args, $defaults );
		
		$blogs = wp_get_sites( apply_filters( 'plugins_enabler_blogs_request', $args ) );

		if( count( $blogs ) == 0 )
			wp_send_json_error( __( 'Plugins enabler does not support large networks', 'plugins-enabler' ) );

		$blogs = array_map( array( $this, 'prepare_blogs_for_js' ), $blogs );
		$blogs = array_filter( $blogs );

		$options = array(
			'limit'  => (int) $args['limit'],
			'offset' => (int) $args['offset'],
			'total'  => (int) get_blog_count()
		);

		$result = array( 'blogs' => $blogs, 'options' => $options );

		wp_send_json_success( $result );
	}

	/**
	 * Checks if a plugin is active
	 * 
	 * @since 2.0
	 */
	public static function is_active_for_blog( $blog_id = 0, $plugin = '' ) {
		$retval = false;

		if( !empty( $blog_id ) && !empty( $plugin ) )
			$retval = in_array( $plugin, (array) get_blog_option( $blog_id, 'active_plugins', array() ) ) || is_plugin_active_for_network( $plugin );

		return $retval;
	}

	/**
	 * Checks if a plugin is viewable by regular admins
	 * 
	 * @since 2.0
	 */
	public static function is_enabled_for_blog( $blog_id = 0, $plugin = '' ) {
		$retval = false;

		if( !empty( $blog_id ) && !empty( $plugin ) )
			$retval = in_array( $plugin, (array) get_blog_option( $blog_id, 'plugins_enabler_enabled_plugins', array() ) );

		return $retval;
	}

	/**
	 * Returns a json reply containing the plugins (activated or not) for the blog
	 * 
	 * @since 2.0
	 */
	public function list_plugins() {
		check_ajax_referer( 'plugins-enabler-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network_plugins' ) )
			wp_send_json_error( __( 'You do not have the capacity to do that!', 'plugins-enabler' ) );

		$blog_id = !empty( $_POST['blog_id'] ) ? intval( $_POST['blog_id'] ) : 0;
		$plugins = get_plugins();

		if( count( $plugins ) == 0 || empty( $blog_id ) )
			wp_send_json_error( __( 'Site id not set or no plugins found', 'plugins-enabler' ) );

		$plugins_list = array();

		foreach( $plugins as $key => $plugin ) {
			if( empty( $plugin['Network'] ) ) {
				$plugins_list[] = array(
					'id'   		  => sanitize_text_field( $key ),
					'name' 		  => esc_html( $plugin['Name'] ),
					'description' => wp_kses( $plugin['Description'], array() ),
					'active'      => self::is_active_for_blog( $blog_id, $key ),
					'allowed'     => self::is_enabled_for_blog( $blog_id, $key )
				);
			}
		}

		$plugins_list = array_filter( $plugins_list );
		wp_send_json_success( $plugins_list );
	}

	/**
	 * Sanitize the plugin name
	 * 
	 * @since 2.0
	 */
	public function sanitize_plugin_name( $plugin = '' ) {
		if( !empty( $plugin ) )
			$plugin = plugin_basename( trim( $plugin ) );

		return $plugin;
	}

	/**
	 * Save the plugins to build the regular admin visibility
	 * 
	 * @since 2.0
	 */
	public function save_plugins() {
		check_ajax_referer( 'plugins-enabler-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network_plugins' ) )
			wp_send_json_error( __( 'You do not have the capacity to do that!', 'plugins-enabler' ) );

		$blog_id = intval( $_POST['id'] );

		if( empty( $blog_id ) )
			wp_send_json_error( __( 'The blog you want to manage the plugins for is not defined', 'plugins-enabler' ) );

		$plugins = !empty( $_POST['plugins'] ) ? $_POST['plugins'] : array();

		if( count( $plugins ) > 0 )
			$plugins = array_map( array( $this, 'sanitize_plugin_name'), $plugins );

		update_blog_option( $blog_id, 'plugins_enabler_enabled_plugins', $plugins );

		wp_send_json_success( __( 'Plugins visibility saved', 'plugins-enabler') );
	}

	/**
	 * HTML output containing the UI to manage plugins visibility
	 * 
	 * @since 2.0
	 */
	public function plugins_manager() {
		?>
		<div class="wrap">
			<h2>Plugins Enabler - Manager</h2>

			<div id="plugins-enabler-main">

				<div id="plugins-enabler-blogs">
					<table class="wp-list-table widefat sites">
						<thead>
							<tr class="alternate">
								<td colspan="3"><h3><?php esc_html_e( 'Edit regular Admins plugins visibility', 'plugins-enabler');?></h3></td>
							</tr>
							<tr>
								<th class="manage-column"><?php esc_html_e( 'IDs', 'plugins-enabler' );?></th>
								<th class="manage-column"><?php esc_html_e( 'Sites', 'plugins-enabler' );?></th>
								<th class="manage-column"><?php esc_html_e( 'Admin emails', 'plugins-enabler');?></th>
							</tr>
						</thead>
						<tbody id="blogs-list-items"></tbody>
						<tfoot id="blogs-list-loadmore">
							<tr class="alternate">
								<td colspan="3">
									<div class="main-action plugins-enabler-actions">
										<span class="spinner"></span>
										<button class="button button-secondary" id="loadmore-blogs" disabled="disabled">
											<?php esc_html_e( 'Load more sites', 'plugins-enabler' );?>
										</button>
									</div>
									<div id="plugins-enabler-blogmessage"></div>
								</td>
							</tr>
						</tfoot>
					</table>
				</div>
				
				<div id="plugins-enabler-blog-plugins">
					<table class="wp-list-table widefat plugins">
						<thead id="plugins-enabler-blog-header"></thead>
						<tbody id="plugins-enabler-plugins-list"></tbody>
						<tfoot id="plugins-enabler-plugins-actions"></tfoot>
					</table>
				</div>

			</div>
		</div>

		<script type="text/template" id="tmpl-plugins-enabler-loader">
	    	<td colspan="{{data.colspan}}">
	    		<div id="waiting">
					<span class="spinner"></span>
					<span>{{data.message}}</span>
				</div>
	    	</td>
	    </script>

		<script type="text/template" id="tmpl-blogs-list">
			<th scope="row">
				<strong>{{data.id}}</strong>
			</th>
	    	<td class="column-blogname blogname">
	    		<a href="#blog/{{data.id}}" data-blogid="{{data.id}}" class="blogs-item">{{data.name}}</a>
	    		<div class="row-actions">
	    			<span class="edit">
	    				<span class="edit">
	    					<a href="#blog/{{data.id}}"><?php esc_html_e( 'Edit Plugins visibility', 'plugins-enabler' );?></a>
	    				</span> | 
	    			</span>
	    			<span class="backend">
	    				<span class="backend">
	    					<a href="{{data.adminurl}}" class="edit"><?php esc_html_e( 'Dashboard', 'plugins-enabler' );?></a>
	    				</span> | 
	    			</span>
	    			<span class="visit">
	    				<span class="view">
	    					<a href="{{data.siteurl}}" rel="permalink"><?php esc_html_e( 'Visit', 'plugins-enabler' );?></a>
	    				</span>
	    			</span>
	    		</div>
	    	</td>
	    	<td class="column-blogname adminemail">
	    		<span>{{data.admin_email}}</span>
	    	</td>
	    </script>

	    <script type="text/template" id="tmpl-plugins-header">
	    	<tr class="active">
	    		<td colspan="3"><h3><?php esc_html_e( 'Managing regular Admins plugin visibility for site:', 'plugins-enabler' );?> <a href="{{data.siteurl}}">{{data.name}}</a></h3></td>
	    	</tr>
	    	<tr>
	    		<th><?php esc_html_e( 'Plugin Name', 'plugins-enabler' );?></th><th><?php esc_html_e( 'Plugin Description', 'plugins-enabler' );?></th><th><?php esc_html_e( 'Plugin Status', 'plugins-enabler' );?></th>
	    	</tr>
	    </script>

	    <script type="text/template" id="tmpl-plugins-list">
	    	<td class="plugin-title">
	    		<strong>
		    		<input type="checkbox" value="{{data.id}}" class="plugins-item"
			    		<# if ( data.allowed ) { #>
			    			checked="checked"
			    		<# } #>> 
		    		{{data.name}}
	    		</strong>
	    	</td>
	    	<td class="column-description desc">{{data.description}}</td>
	    	<td>
	    		<# if ( data.active ) { #>
					<strong><?php esc_html_e( 'Active', 'plugins-enabler' );?></strong>
				<# } else { #>
					<?php esc_html_e( 'Inactive', 'plugins-enabler' );?>
				<# } #>
	    	</td>
	    </script>

	    <script type="text/template" id="tmpl-plugins-actions">
	    	<tr>
	    		<td colspan="3">
	    			<div class="main-action plugins-enabler-actions">
	    				<span class="spinner"></span>
		    			<button id="save-plugins" class="button button-primary" value="{{data.id}}" disabled="disabled">
							<?php esc_attr_e( 'Save visibility', 'plugins-enabler' ); ?>
						</button>
	    			</div>
	    			<div class="sub-action plugins-enabler-actions">
	    				<a href="#" class="button"><?php esc_html_e( 'Back to sites list', 'plugins-enabler');?></a>
	    			</div>
	    			<div id="plugins-enabler-pluginmessage"></div>
	    		</td>
	    	</tr>
	    </script>
		<?php
	}

	/**
	 * Checks if a plugin is viewable by a regular admin
	 * 
	 * @since 2.0
	 */
	public function check_enabled( $plugins = array() ) {
		if( is_network_admin() || is_super_admin() )
			return $plugins;

		$enabled = get_option( 'plugins_enabler_enabled_plugins', array() );

		if( !isset( $enabled ) || !is_array( $enabled ) )
			return $plugins;

		$all = array_keys( $plugins );
		$to_hide = array_diff( $all, $enabled );

		foreach( $to_hide as $plugin )
			unset( $plugins[$plugin] );

		return $plugins;
	}

	/**
	 * We're never too sure !
	 * 
	 * @since 2.0
	 */
	public function map_meta_cap( $caps = array(), $cap = '', $user_id = 0, $args = array() ){
		if( $cap != 'activate_plugins' )
			return $caps;

		if( !is_admin() )
			return $caps;

		if( is_network_admin() || is_super_admin() )
			return $caps;

		if ( ! isset( get_current_screen()->id ) || get_current_screen()->id != 'plugins' )
			return $caps;

		if( isset( $_REQUEST['plugin'] ) || isset( $_POST['checked'] ) ) {

			$plugins = !empty( $_REQUEST['plugin'] ) ? (array) $_REQUEST['plugin'] : $_POST['checked'];
			$enabled = get_option( 'plugins_enabler_enabled_plugins');

			$can_manage = count( array_diff( $plugins, $enabled ) );
			
			if( !empty( $can_manage ) )
				$caps = array( 'manage_network' );
		}

		return $caps;
	}

	/**
	 * Translation
	 * 
	 * @since 2.0
	 */
	public function load_translation() {
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/plugins-enabler/' . $mofile;

		// Look in global /wp-content/languages/plugins-enabler folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/plugins-enabler/languages/ folder
		load_textdomain( $this->domain, $mofile_local );
	}

}

/**
 * It's a multisite only plugin !
 * 
 * @since 2.0
 */
function plugins_enabler() {
	if( is_multisite() )
		return Plugins_Enabler::instance();
}

// We are now ready for take off
add_action( 'init', 'plugins_enabler' );
