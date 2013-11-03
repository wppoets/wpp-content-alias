<?php
/**
 * Copyright (c) 2013, WP Poets and/or its affiliates <plugins@wppoets.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
/**
 * @author Michael Stutz <michaeljstutz@gmail.com>
 */
class WPP_Content_Alias_Admin {
	/** Used to keep the init state of the class */
	private static $_initialized = false;
	
	/**
	 * 
	 */
	public static function init() {
		if ( self::$_initialized ) 
			return;
		
		add_filter( 'plugin_action_links_' . WPP_CONTENT_ALIAS_FILTER_FILE, array(__CLASS__, 'plugin_action_links'), 10, 1 );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		self::$_initialized = true;
	}
	
	/**
	 * 
	 */
	public static function plugin_action_links( $links ) {
		$before_links = array();
		$before_links['settings'] = sprintf( '<a href="%1$s">%2$s</a>', admin_url( 'options-general.php?page=' . WPP_Content_Alias::ADMIN_PAGE_ROOT . 'settings&tab=primary'), 'Settings' );
		$after_links = array();
		return array_merge( $before_links, $links, $after_links );
	}

	/**
	 * 
	 */
	public static function admin_init() {
		//TODO: Do something here?
	}
	
	/**
	 * 
	 */
	public static function admin_enqueue_scripts() {
		wp_register_style(
			WPP_Content_Alias::PLUGIN_BASE_NAME . 'AdminCss', 
			plugins_url( 'css/wpp-content-alias-admin.css', WPP_CONTENT_ALIAS_PLUGIN_FILE ),
			'20130501'
			);
		wp_enqueue_style( WPP_Content_Alias::PLUGIN_BASE_NAME . 'AdminCss' );
		
		wp_register_script(
			WPP_Content_Alias::PLUGIN_BASE_NAME . 'AdminJs', 
			plugins_url( 'js/wpp-content-alias-admin.js', WPP_CONTENT_ALIAS_PLUGIN_FILE ),
			array( 'jquery', 'jquery-ui-core' ), 
			'20130501'
			);
		wp_enqueue_script( WPP_Content_Alias::PLUGIN_BASE_NAME . 'AdminJs' );
	}
	
	/**
	 * 
	 */
	public static function admin_menu() {
		add_options_page(
			'Settings',
			'Content Alias',
			'manage_options',
			WPP_Content_Alias::ADMIN_PAGE_ROOT . 'settings',
			array( __CLASS__, 'display_settings_page' )
		);
	}
	
	/**
	 * 
	 */
	public static function save_post( $post_id ) {
		//if(true) return; // Disable the below logic because we are not finished with the interface yet
		
		if ( ! current_user_can( 'edit_page', $post_id ) ) //Check users permissions
			return;
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) //Check skip if we are only auto saving
			return;
		
		if ( wp_is_post_revision( $post_id ) ) //Check to make sure it is not a revision
			return;
		
		if ( ! isset( $_POST[ WPP_Content_Alias::METABOX_FORM_NONCENAME ] ) || ! wp_verify_nonce( $_POST[ WPP_Content_Alias::METABOX_FORM_NONCENAME ], plugin_basename( __FILE__ ) ) ) //Verify the form
			return;
		
		delete_post_meta( $post_id, WPP_Content_Alias::POSTMETA_CONTENT_ALIAS ); //Start off by deleting any excisting values
		if ( isset( $_POST[ WPP_Content_Alias::METABOX_FORM_CONTENT_ALIASES ] ) && ! empty( $_POST[ WPP_Content_Alias::METABOX_FORM_CONTENT_ALIASES ] ) ) {
			$post_aliases = $_POST[ WPP_Content_Alias::METABOX_FORM_CONTENT_ALIASES ];
			if ( is_array( $post_aliases ) ) {
				foreach( (array) $post_aliases as $post_alias ) {
					WPP_Content_Alias::add_alias( $post_id, $post_alias );
				}
			}
		}
	}
	
	/**
	 * 
	 */
	public static function add_meta_boxes() {
		//TODO: add admin section for all or selected post types
		$post_types = get_post_types( '','names' );
		foreach( $post_types as $post_type ) {
			add_meta_box(
				WPP_Content_Alias::METABOX_ID,						//$id
				__( WPP_Content_Alias::METABOX_TITLE ),	//$title
				array( __class__, 'display_metabox' ),		//$callback
				$post_type,															//$post_type
				WPP_Content_Alias::METABOX_CONTEXT,			//$context
				WPP_Content_Alias::METABOX_PRIORITY			//priority
			);
		}
	}
	
	/**
	 * 
	 */
	public static function display_metabox( $post ) {
		wp_nonce_field( plugin_basename( __FILE__ ), WPP_Content_Alias::METABOX_FORM_NONCENAME );
		$post_aliases = get_post_meta( $post->ID, WPP_Content_Alias::POSTMETA_CONTENT_ALIAS, false );
		// Start HTML for meta boxes ?>
		<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(document).ready(function($){
				$('.wppca-add-row').on('click', function() {
					console.log('something clicked');
					var row = $('#wppca-empty-row').clone(true);
					row.removeAttr('id');
					row.removeClass('empty-row screen-reader-text');
					row.insertBefore('#wppca-empty-row');
					return false;
				});
				$('.wppca-remove-row').on('click', function() {
					var agree = confirm("Are you sure you want to remove the alias?");
					if(agree) {
						$(this).parents('tr').remove();
					}
					return false;
				});
				$('#wppca-save-button').click(function(e) {
					e.preventDefault();
					$('#publish').click();
				});
			});
			/* ]]> */
		</script>
		<table id="wppca-alias-list" width="100%">
		<thead>
			<tr>
				<th width="90%">Aliases</th>
				<th width="2%"><button class="button button-primary wppca-add-row" style="font-weight: normal;">+</button></th>
			</tr>
		</thead>
		<tbody>
		<?php // Pause HTML
		if ( empty( $post_aliases ) ) {
		// Resume HTML ?>
		<tr>
			<td><input type="text" class="widefat" name="<?php echo WPP_Content_Alias::METABOX_FORM_CONTENT_ALIASES; ?>[]" /></td>
			<td><a class="button wppca-remove-row" href="#">-</a></td>
		</tr>
		<?php // Pause HTML
		} else {
			foreach( $post_aliases as $post_alias ) {
		// Resume HTML ?>
		<tr>
			<td><input type="text" class="widefat" name="<?php echo WPP_Content_Alias::METABOX_FORM_CONTENT_ALIASES; ?>[]" value="<?php echo $post_alias; ?>" readonly/></td>
			<td><a class="button wppca-remove-row" href="#">-</a></td>
		</tr>
		<?php // Pause HTML
			}
		}
		// Resume HTML ?>
		<tr id="wppca-empty-row" class="empty-row screen-reader-text">
			<td><input type="text" class="widefat" name="<?php echo WPP_Content_Alias::METABOX_FORM_CONTENT_ALIASES; ?>[]" /></td>
			<td><button class="button wppca-remove-row">-</button></td>
		</tr>
		</tbody>
		<tfoot>
			<tr id="wppca-bottom-row">
				<td></td>
				<td><button class="button button-primary wppca-add-row">+</button></td>
			</tr>
		</tfoot>
		</table>
		<hr />
		<button id="wppca-save-button" class="button button-primary" value="Save" style="float: right;">Save</button>
		<div style="clear: both;"></div>
		<?php // End HTML for meta boxes
	}
	
	/**
	 * 
	 */
	public static function display_settings_page() {
		if ( isset( $_GET['tab'] ) )
			$current_tab = $_GET['tab'];
		else
			$current_tab = 'primary';
		
		if ( isset( $_POST[ WPP_Content_Alias::METABOX_FORM_NONCENAME ] ) && wp_verify_nonce( $_POST[ WPP_Content_Alias::METABOX_FORM_NONCENAME ], plugin_basename( __FILE__ ) ) ) {
			//TODO: add the save logic here
			print( '<div id="message" class="updated settings-error"><p><strong>Settings saved</strong></p></div>' . "\n" );
		}
		
		// Start HTML ?>
		<div class="wrap">
			<h2>Content Alias</h2>
			<?php screen_icon(); ?>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo admin_url( 'options-general.php?page=' . WPP_Content_Alias::ADMIN_PAGE_ROOT . 'settings&tab=primary' ); ?>" class="nav-tab <?php echo $current_tab == 'primary' ? 'nav-tab-active' : ''; ?>">Options</a>
				<a href="<?php echo admin_url( 'options-general.php?page=' . WPP_Content_Alias::ADMIN_PAGE_ROOT . 'settings&tab=tracking' ); ?>" class="nav-tab <?php echo $current_tab == 'tracking' ? 'nav-tab-active' : ''; ?>">Tracking</a>
			</h2>
			<form method="post" action="">
			<?php if ( $current_tab == 'primary' ) { ?>
				<h3>Primary Settings</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Content Types</th>
						<td>Coming Soon...</td>
					</tr>
					<tr valign="top">
						<th scope="row">Type of Redirect</th>
						<td>Coming Soon...</td>
					</tr>
				</table>
			<?php
				wp_nonce_field( plugin_basename( __FILE__ ), WPP_Content_Alias::METABOX_FORM_NONCENAME );
				submit_button();
			?>
			<?php } elseif( $current_tab == 'tracking' ) { ?>
				<h3>Tracking Settings</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Enabled</th>
						<td>Coming Soon...</td>
					</tr>
				</table>
			<?php
				wp_nonce_field( plugin_basename( __FILE__ ), WPP_Content_Alias::METABOX_FORM_NONCENAME );
				submit_button();
				
				if ( ! class_exists( 'WPP_Content_Alias_List_Table' ) )
					require_once( WPP_CONTENT_ALIAS_PLUGIN_PATH . '/core/class-wpp-content-alias-list-table.php');
				
				$stats_table = new WPP_Content_Alias_List_Table();
				$stats_table->set_columns( array( 'url' => 'URL Paths', 'count' => 'Hits', 'last' => 'Last used', ) );
				$stats_table->set_data( array( array( 'url'=>'Coming Soon...', ) ) );
				$stats_table->prepare_items();
			?>
			</form>
			<hr />
			<h3>Tracking Stats</h3>
			<p>Top 10 hit URLs...</p>
			<?php $stats_table->display(); ?>
			<?php } ?>
		</div>
		<?php // End HTML
	}
}
