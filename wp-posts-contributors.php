<?php
/**
 * Wp Posts Contributors plugins display a single post contributors.
 *
 * @package           Posts Contributors
 * @author            Abdul Hadi <abdul.hadi.aust@gmail.com>
 * @copyright         2021 Abdul Hadi
 * @license           GPL-2.0-or-later
 *
 * @wordwppc_contributorsss-plugin
 * Plugin Name:       Posts Contributors
 * Plugin URI:        https://github.com/abdulhadicse/wp-post-contributors
 * Description:       This is a simple posts contributors for WordPress posts plugin.
 * Version:           1.0.0
 * Requires at least: 4.1
 * Requires PHP:      5.6
 * Author:            Abdul Hadi
 * Author URI:        http://abdulhadi.info
 * Text Domain:       wp-posts-contributors
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * Copyright (c) 2021 Abdul Hadi (email: abdul.hadi.aust@gmail.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordwppc_contributorsss.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

 // don't call the file directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The main plugin class
 */
final class Wp_Posts_Contributors {

    /**
     * Plugin version
     *
     * @var string
     */
    const version = '1.0.0';

    /**
     * Class construcotr
     */
    private function __construct() {
        $this->define_constants();

        register_activation_hook( __FILE__, [ $this, 'activate' ] );

        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    /**
     * Initializes a singleton instance
     *
     * @return \Wp_Posts_Contributors
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'WPPC_CONTRIBUTORS_VERSION', self::version );
        define( 'WPPC_CONTRIBUTORS_FILE', __FILE__ );
        define( 'WPPC_CONTRIBUTORS_PATH', __DIR__ );
        define( 'WPPC_CONTRIBUTORS_URL', plugins_url( '', WPPC_CONTRIBUTORS_FILE ) );
        define( 'WPPC_CONTRIBUTORS_ASSETS', WPPC_CONTRIBUTORS_URL . '/assets' );
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {
        add_action( 'add_meta_boxes', [ $this, 'wppc_register_meta_box_cb' ] );
        add_action( 'save_post', [ $this, 'wppc_save_contributors_metabox' ] );
        add_action( 'the_content', [ $this, 'wppc_modify_content_with_post_contributors' ]  );
        add_action( 'wp_enqueue_scripts', [ $this,'wppc_enqueue_style' ] );
    }

    /**
     * Register meta box(es).
     */
    public function wppc_register_meta_box_cb() {
        add_meta_box( 'wppc_meta_box', __( 'Contributors', 'wp-posts-contributors' ), [ $this, 'wppc_posts_contributors_meta_box' ], 'post', 'side', 'core' );
    }

    /**
     * Meta box display callback.
     *
     * @param WP_Post $post Current post object.
     */
    public function wppc_posts_contributors_meta_box( $post ) {
        //get all user
        $blogusers = get_users();
        //display user name with checkbox
        if ( !empty ( $blogusers ) ) {
            foreach ( $blogusers as $user ) {
                $user_id          = $user->ID;
                $get_meta         = get_post_meta($post->ID, 'wppc_post_author', true);
                //cehck if $get_meta is empty string to convert empty array
                $get_contributors = ( ''== $get_meta )  ?  [] : $get_meta;
                $checked          = in_array( $user_id, $get_contributors ) ? 'checked' : '';
                //set nonce field
                wp_nonce_field( 'save_contributor', 'wppc_contributors_nonce');
        ?>
			<input 
                type="checkbox"  
                name="contributors[]" 
                value="<?php echo esc_attr($user_id); ?>"
                <?php echo $checked; ?> 
            />
            <label id="label-<?php echo esc_attr($user_id); ?>"><?php echo esc_html( $user->display_name ) ; ?></label>

        <?php echo '</br>';       
            }
        }
        else {
            echo __( 'No users found.', 'wp-posts-contributors');
        }
    }

    /**
     * [save_post] book callback
     * check user input data and save into db
     *
     * @param array $post_id
     * 
     * @return void
     */
    public function wppc_save_contributors_metabox( $post_id ) {
        // Check if nonce is set
		if (!isset($_POST['wppc_contributors_nonce'])) {
			return $post_id;
		}
		if (!wp_verify_nonce($_POST['wppc_contributors_nonce'], 'save_contributor')) {
			return $post_id;
		}
		// Check that the logged in user has permission to edit this post
		if (!current_user_can('edit_post')) {
			return $post_id;
		}
        // verify user input data
        $contributors      = isset( $_POST['contributors'] ) ? sanitize_text_field( $_POST['contributors'] ) : [];
        //update data into db
        update_post_meta( $post_id, 'wppc_post_author', $contributors );
    }

    /**
     * display post contributor custom fields for each post
     *
     * @param string $content
     * 
     * @return void
     */
    public function wppc_modify_content_with_post_contributors( $content ) {
        if ( is_singular('post') ) {
            //get post id
            $post_id  = get_the_id();
            //get contributor data from db using single post id
            $get_meta = get_post_meta($post_id, 'wppc_post_author', true);
            if (! empty($get_meta) ) {
            ob_start();
            ?>
                <div class="contributors-wraper">
                    <h5><?php echo __('Contributors:', 'wp-posts-contributors'); ?></h5>
                    <hr>
                    <ul class="contributors">
                    <?php
                        if (is_array($get_meta)) {
                            foreach ($get_meta as $id) {
                                $user = get_user_by('ID', $id);?>
                                <li>
                                    <a href="<?php echo esc_url( get_author_posts_url(get_the_author_meta( 'ID' ) )); ?>"> 
                                    <div class="rt-avatar"><?php echo get_avatar($user, 55); ?></div> 
                                    <div class="contributor-name"><?php echo esc_html( $user->display_name ); ?></div>
                                    </a>
                                </li>
                            <?php
                            }
                        }
            ?>
                    </ul>
                </div>
            
            <?php

            $data = ob_get_clean();

            return $content . $data;
            
            } 
        }
        return $content;
    }
    /**
     * enqueue post crontributors style
     *
     * @return void
     */
    public function wppc_enqueue_style() {
		wp_enqueue_style('wppc-css', plugin_dir_url( __FILE__ ) . 'post-contributors.css', array());
	}

    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
        $installed = get_option( 'wppc_contributors_installed' );

        if ( ! $installed ) {
            update_option( 'wppc_contributors_installed', time() );
        }

        update_option( 'wppc_contributors_version', WPPC_CONTRIBUTORS_VERSION );
    }
}

/**
 * Initializes the main plugin
 *
 * @return \Wp_Posts_Contributors
 */
function wp_posts_contributors() {
    return Wp_Posts_Contributors::init();
}

// kick-off the plugin
wp_posts_contributors();
