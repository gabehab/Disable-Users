<?php
/**
 * Plugin Name: Disable Users
 * Plugin URI:  http://wordpress.org/extend/disable-users
 * Description: This plugin provides the ability to disable specific user accounts.
 * Version:     1.1.0
 * Author:      Jared Atchison, khromov
 * Author URI:  http://jaredatchison.com 
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author     Jared Atchison
 * @version    1.1.0
 * @package    JA_DisableUsers
 * @copyright  Copyright (c) 2015, Jared Atchison
 * @link       http://jaredatchison.com
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

final class ja_disable_users {

	/**
	 * Initialize all the things
	 *
	 * @since 1.0.0
	 */
	function __construct() {

		// Actions
		add_action( 'init',                       array( $this, 'load_textdomain'             )        );
		add_action( 'show_user_profile',          array( $this, 'use_profile_field'           )        );
		add_action( 'edit_user_profile',          array( $this, 'use_profile_field'           )        );
		add_action( 'personal_options_update',    array( $this, 'user_profile_field_save'     )        );
		add_action( 'edit_user_profile_update',   array( $this, 'user_profile_field_save'     )        );
		add_action( 'wp_login',                   array( $this, 'user_login'                  ), 10, 2 );
		add_action( 'manage_users_custom_column', array( $this, 'manage_users_column_content' ), 10, 3 );
		add_action( 'admin_footer-users.php',	  array( $this, 'manage_users_css'            )        );
	    add_action( 'admin_post_ja_disable_user', array( $this, 'toggle_user'                 ) );
	    add_action( 'admin_post_ja_enable_user',  array( $this, 'toggle_user'                 ) );

		// Filters
		add_filter( 'login_message',              array( $this, 'user_login_message'          )        );
		add_filter( 'manage_users_columns',       array( $this, 'manage_users_columns'	      )        );
        add_filter( 'wpmu_users_columns',         array( $this, 'manage_users_columns'	      )        );
	}

  /**
   * Gets the capability associated with banning a user
   * @return string
   */
	function get_edit_cap() {
		return is_multisite() ? 'manage_network_users' : 'edit_users';
	}

	/**
	 * Toggles the users disabled status
	 *
	 * @since 1.1.0
	 */
	function toggle_user() {
		$nonce_name = (isset($_GET['action']) && $_GET['action'] === 'ja_disable_user') ? 'ja_disable_user_' : 'ja_enable_user_';
		if(current_user_can($this->get_edit_cap()) && isset($_GET['ja_user_id']) && isset($_GET['ja_nonce']) && wp_verify_nonce($_GET['ja_nonce'], $nonce_name . $_GET['ja_user_id'])) {

			//Don't disable super admins
			if(is_multisite() && is_super_admin((int)$_GET['ja_user_id'])) {
			  wp_die(__('Super admins can not be disabled.', 'ja_disable_users'));
			}

			update_user_meta( (int)$_GET['ja_user_id'], 'ja_disable_user', ($nonce_name === 'ja_disable_user_' ? '1' : '0' ) );

			//Redirect back
			if(isset($_GET['ja_return_url'])) {
			  wp_safe_redirect($_GET['ja_return_url']);
			  exit;
			}
			else {
			  wp_die(__('The user has been updated.', 'ja_disable_users'));
			}
		}
		else {
			wp_die(__('You are not allowed to perform this action, or your nonce expired.', 'ja_disable_users'));
		}
    }

	/**
	 * Load the textdomain so we can support other languages
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		$domain = 'ja_disable_users';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add the field to user profiles
	 *
	 * @since 1.0.0
	 * @param object $user
	 */
	public function use_profile_field( $user ) {

		//Super admins can not be banned
		if( is_multisite() && is_super_admin( $user->ID ) )
			return;

		// Only show this option to users who can delete other users
		if ( !current_user_can( $this->get_edit_cap() ) )
			return;
		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th>
						<label for="ja_disable_user"><?php _e(' Disable User Account', 'ja_disable_users' ); ?></label>
					</th>
					<td>
						<input type="checkbox" name="ja_disable_user" id="ja_disable_user" value="1" <?php checked( 1, get_the_author_meta( 'ja_disable_user', $user->ID ) ); ?> />
						<span class="description"><?php _e( 'If checked, the user will not be able to login with this account.' , 'ja_disable_users' ); ?></span>
					</td>
				</tr>
			<tbody>
		</table>
		<?php
	}

	/**
	 * Saves the custom field to user meta
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function user_profile_field_save( $user_id ) {

		//Don't disable super admins
		if( is_multisite() && is_super_admin( $user_id ) )
			return;

		// Only worry about saving this field if the user has access
		if ( !current_user_can( $this->get_edit_cap() ) )
			return;

		if ( !isset( $_POST['ja_disable_user'] ) ) {
			$disabled = 0;
		} else {
			$disabled = (int)$_POST['ja_disable_user'];
		}
	 
		update_user_meta( $user_id, 'ja_disable_user', $disabled );
	}

	/**
	 * After login check to see if user account is disabled
	 *
	 * @since 1.0.0
	 * @param string $user_login
	 * @param object $user
	 */
	public function user_login( $user_login, $user = null ) {

		if ( !$user ) {
			$user = get_user_by('login', $user_login);
		}
		if ( !$user ) {
			// not logged in - definitely not disabled
			return;
		}
		// Get user meta
		$disabled = get_user_meta( $user->ID, 'ja_disable_user', true );
		
		// Is the use logging in disabled?
		if ( $disabled == '1' ) {
			// Clear cookies, a.k.a log user out
			wp_clear_auth_cookie();

			// Build login URL and then redirect
			$login_url = site_url( 'wp-login.php', 'login' );
			$login_url = add_query_arg( 'disabled', '1', $login_url );
			wp_redirect( $login_url );
			exit;
		}
	}

	/**
	 * Show a notice to users who try to login and are disabled
	 *
	 * @since 1.0.0
	 * @param string $message
	 * @return string
	 */
	public function user_login_message( $message ) {

		// Show the error message if it seems to be a disabled user
		if ( isset( $_GET['disabled'] ) && $_GET['disabled'] == 1 ) 
			$message =  '<div id="login_error">' . apply_filters( 'ja_disable_users_notice', __( 'Account disabled', 'ja_disable_users' ) ) . '</div>';

		return $message;
	}

	/**
	 * Add custom disabled column to users list
	 *
	 * @since 1.0.3
	 * @param array $defaults
	 * @return array
	 */
	public function manage_users_columns( $defaults ) {

		$defaults['ja_user_disabled'] = __( 'User status', 'ja_disable_users' );
		return $defaults;
	}

	/**
	 * Set content of disabled users column
	 *
	 * @since 1.0.3
	 * @param empty $empty
	 * @param string $column_name
	 * @param int $user_ID
	 * @return string
	 */
	public function manage_users_column_content( $empty, $column_name, $user_ID ) {

		if ( $column_name == 'ja_user_disabled' ) {

          //Super admins can't be disabled
          if(is_super_admin($user_ID)) {
            return '<span class="ja-user-enabled">&#x2714;</span>';
          }

		  $user_disabled = (get_the_author_meta( 'ja_disable_user', $user_ID ) == 1);
          $nonce = $user_disabled ? wp_create_nonce( 'ja_enable_user_'. $user_ID ) : wp_create_nonce( 'ja_disable_user_'. $user_ID );
          $return_url = urlencode_deep((is_ssl() ? 'https' : 'http') . '://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);

          if($user_disabled) {
            $link_url = admin_url("admin-post.php?action=ja_enable_user&ja_user_id={$user_ID}&ja_nonce={$nonce}&ja_return_url={$return_url}&message=1");
            return '<span class="ja-user-disabled">&#x2718;</span><br><a href="'. esc_attr__($link_url) .'">'. __('Enable', 'ja_disable_users') .'</a>';
          }
          else {
            $link_url = admin_url("admin-post.php?action=ja_disable_user&ja_user_id={$user_ID}&ja_nonce={$nonce}&ja_return_url={$return_url}&message=1");
            return '<span class="ja-user-enabled">&#x2714;</span> <br><a href="'. esc_attr__($link_url) .'">'. __('Disable', 'ja_disable_users') .'</a>';
          }
		}
	}

	/**
	 * Specifiy the width of our custom column
	 *
	 * @since 1.0.3
 	 */
	public function manage_users_css() {
		?>
	    <style type="text/css">
		    .column-ja_user_disabled {
			    width: 80px;
		    }

		    span.ja-user-enabled {
			    font-size: 30px;
			    color: green;
		    }

		    span.ja-user-disabled {
			    font-size: 30px;
			    color: red;
		    }
	    </style>
		<?php
	}
}
new ja_disable_users();