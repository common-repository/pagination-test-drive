<?php
/*
Plugin Name: Pagination Test Drive
Plugin URI: https://www.560designs.com/development/pagination-test-drive.html
Description: You can test the pagination without having to post an article even once.
Version: 1.3.3
Author: Yuya Hoshino
Author URI: https://www.560designs.com/
Text Domain: pagination-test-drive
Domain Path: /languages
*/

/*  Copyright 2016 Yuya Hoshino (email : y.hoshino56@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Pagination_Test_Drive {
	public $updated = array ();

	public function __construct() {
		load_plugin_textdomain( 'pagination-test-drive', false, plugin_basename( dirname ( __FILE__ ) ) . '/languages' );
		register_deactivation_hook( __FILE__, array ( $this, 'ptd_deactivation' ) );
		register_uninstall_hook( __FILE__, array ( 'Pagination_Test_Drive', 'ptd_uninstall' ) );
		add_action( 'admin_menu', array ( $this, 'ptd_admin_menu' ) );
		add_filter( 'the_posts', array ( $this, 'ptd_the_post_filtering' ) );
	}

	public function ptd_deactivation() {
		delete_option( 'pagination_test_drive' );
	}

	public function ptd_uninstall() {
		delete_option( 'pagination_test_drive' );
	}

	public function ptd_admin_menu() {
		$hook_suffix = add_submenu_page( 'tools.php', 'Pagination Test Drive', 'Pagination Test Drive', 'administrator', 'pagination_test_drive', array ( $this, 'ptd_front_page' ) );
		add_action( 'admin_print_styles-' . $hook_suffix, array ( $this, 'ptd_styles' ) );
	}

	public function ptd_styles() {
		wp_enqueue_style( 'bte-styles', plugins_url( 'css/styles.css', __FILE__ ) );
	}

	public function ptd_the_post_filtering( $posts ) {
		if ( is_user_logged_in() ) {
			$ptd_array = get_option( 'pagination_test_drive', 'a:0:{}' );
			global $virtual_found_posts, $wp_query;
			$virtual_found_posts = ( !empty ( $ptd_array['virtual_found_posts'] ) ) ? (int) $ptd_array['virtual_found_posts'] : 100;
			$posts_per_page = ( !empty ( $ptd_array['posts_per_page'] ) ) ? (int) $ptd_array['posts_per_page'] : (int) get_query_var( 'posts_per_page' );

			$current_user = wp_get_current_user();
			$roles = $current_user->roles;
			$role = array_shift ( $roles );

			if ( !empty ( $ptd_array['role'] ) && in_array(  $role, $ptd_array['role'] ) ) {
				add_filter( 'posts_request_ids', function () {
					global $wpdb;
					return "SELECT * FROM {$wpdb->options} WHERE option_id = 1";
				} );
				add_filter( 'found_posts', function () {
					global $virtual_found_posts;
					return $virtual_found_posts;
				} );

				$post_type = get_query_var( 'post_type' ) ? get_query_var( 'post_type' ) : 'post';
				$flag = false;
				if ( !empty ( $ptd_array[$post_type] ) && count ( $archives = $ptd_array[$post_type] ) > 0 ) {
					foreach ( $archives as $archive ) {
						if ( $archive == 'post_type_archive' ) {
							if ( is_post_type_archive( $post_type ) && !is_tax() && !is_author() ) $flag = true;
						} elseif ( strpos ( $archive, 'tax_' ) === 0 ) {
							if ( is_tax( str_replace ( 'tax_', '', $archive ) ) ) $flag = true;
						} else {
							$is = 'is_' . $archive;
							if ( $is() ) $flag = true;
						}
					}
				}
				if ( $flag ) {
					if ( get_query_var( 'post_type' ) == $post_type || $post_type == 'post' ) {
						global $current_user;
						get_currentuserinfo();
						$user_ID = (int) $current_user->ID;
						$url = get_bloginfo( 'url' );
						$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
						$wp_query->posts = '';
						$wp_query->found_posts = (int) $virtual_found_posts;
						$wp_query->max_num_pages = ceil ( $wp_query->found_posts / $posts_per_page );

						$dummy_arr = array (
							'ID' => 1,
							'post_author' => $user_ID,
							'post_content' => 'Pagination Test Drive',
							'post_excerpt' => 'Pagination Test Drive',
							'post_status' => 'publish',
							'comment_status' => 'closed',
							'ping_status' => 'closed',
							'post_password' => '',
							'to_ping' => '',
							'pinged' => '',
							'post_modified' => date ( 'Y-m-d H:i:s' ),
							'post_modified_gmt' => date ( 'Y-m-d H:i:s' ),
							'post_content_filtered' => '',
							'post_parent' => 0,
							'guid' => $url . '/?p=1',
							'menu_order' => 0,
							'post_type' => $post_type,
							'post_mime_type' => '',
							'comment_count' => '0',
							'filter' => 'raw'
						);

						$posts = array ();
						$limit = $wp_query->max_num_pages == $paged ? $virtual_found_posts : $posts_per_page * $paged;
						for ( $i = $posts_per_page * ( $paged - 1 ) + 1; $i <= $limit; $i++ ) {
							$unique_arr = array (
								'post_title' => 'Pagination Test Drive #' .  $i,
								'post_name' => 'pagination-test-drive-' .  $i,
								'post_date' => date ( 'Y-m-d H:i:s', time() + 86400 * $i ),
								'post_date_gmt' => date ( 'Y-m-d H:i:s', time() + 86400 * $i ),
							);
							$dummy_arr = array_merge ( $dummy_arr, $unique_arr );
							$object = new WP_Post( (object) $dummy_arr );
							array_push ( $posts, $object );
						}
					}
				}
			}
		}
		return $posts;
	}

	public function ptd_front_page() {
		$post_types = get_post_types( array (
			'public' => true
		) );
		$mode = filter_input ( INPUT_POST, 'mode', FILTER_SANITIZE_SPECIAL_CHARS );
		$args = array (
			'role' => array (
				'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
				'flags' => FILTER_REQUIRE_ARRAY,
			),
			'virtual_found_posts' => FILTER_SANITIZE_NUMBER_INT,
			'posts_per_page' => FILTER_SANITIZE_NUMBER_INT,
		);
		foreach ( $post_types as $post_type ) {
			if ( $post_type != 'attachment' ) {
				$args[$post_type] = array (
					'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
					'flags' => FILTER_REQUIRE_ARRAY,
				);
			}
		}
		$post = filter_input_array ( INPUT_POST, $args );
?>
<div class="wrap" id="pagination_test_drive">
<div id="icon-themes" class="icon32">&nbsp;</div><h2><?php echo __( 'Pagination Test Drive', 'pagination-test-drive' ); ?></h2>
<?php
		if ( !empty ( $mode ) && $mode == 'save' ) {
			update_option( 'pagination_test_drive', $post );
			echo '<div class="updated"><p>' . __( 'Settings saved.', 'pagination-test-drive' ) . '</p></div>';
		}
		$ptd_array = get_option( 'pagination_test_drive', 'a:0:{}' );
?>
<div class="wrapInner">
<p class="myDescription"><?php echo __( 'You can test the pagination without having to post an article even once.', 'pagination-test-drive' ); ?></p>
<form method="post" action="?page=pagination_test_drive">
<div class="myFields">
<dl>
<dt>
<p><?php echo __( 'Enabled Roles', 'pagination-test-drive' ); ?></p>
<p><?php echo __( 'Which roles will you test with?', 'pagination-test-drive' ); ?></p>
</dt>
<dd>
<?php
$roles = get_editable_roles();
if ( !empty ( $roles ) && !is_wp_error( $roles ) ) {
	echo '<ul>' . "\n";
	foreach ( $roles as $key => $role ) {
		echo '<li>' . "\n";
		echo '<input id="ptdField' . esc_attr ( $key ) . '" type="checkbox" name="role[]" value="' . esc_attr ( $key ) . '"';
		if ( !empty ( $ptd_array['role'] ) && in_array( $key, $ptd_array['role'] ) )
			echo ' checked="checked"';
		echo ' />';
		echo '<label for="ptdField' . esc_attr ( $key ) . '">' . translate_user_role( $role['name'] ) . '</label>' . "\n";
		echo '</li>' . "\n";
	}
	echo '</ul>' . "\n";
}
?>
</dd>
</dl>

<dl>
<dt>
<p><label for="ptd_virtual_found_posts"><?php echo __( 'Virtual Found Posts', 'pagination-test-drive' ); ?></label></p>
<p><?php echo __( 'How many do you need articles?', 'pagination-test-drive' ); ?></p>
</dt>
<dd>
<p><input type="text" name="virtual_found_posts" id="ptd_virtual_found_posts" size="10" value="<?php echo ( !empty ( $ptd_array['virtual_found_posts'] ) ) ? (int) $ptd_array['virtual_found_posts'] : 100 ?>" /></p>
</dd>
</dl>

<dl>
<dt>
<p><label for="ptd_posts_per_page"><?php echo __( 'Posts Per Page', 'pagination-test-drive' ); ?></label></p>
<p><?php echo __( 'How many posts per page?', 'pagination-test-drive' ); ?></p>
</dt>
<dd>
<p><input type="text" name="posts_per_page" id="ptd_posts_per_page" size="10" value="<?php echo ( !empty ( $ptd_array['posts_per_page'] ) ) ? (int) $ptd_array['posts_per_page'] : '' ?>" /></p>
</dd>
</dl>

<?php
$html = '';
$i = 1;

$post_object = get_post_type_object( 'post' );
if ( !empty ( $post_object ) && !is_wp_error( $post_object ) ) {
	$html .= '<ul>' . "\n";
	$html .= '<li>' . "\n";
	$html .= '<p>' . esc_html( $post_object->label ) . '</p>' . "\n";
	$taxonomies = get_object_taxonomies( 'post', 'objects' );
	if ( !empty ( $taxonomies ) && !is_wp_error( $taxonomies ) ) {
		$html .= '<ul>' . "\n";
		if ( get_option ( 'show_on_front' ) == 'posts' ) {
			$html .= '<li>' . "\n";
			$html .= '<input id="ptdField' . $i . '" type="checkbox" name="post[]" value="front_page"';
			if ( !empty ( $ptd_array['post'] ) && in_array( 'front_page', $ptd_array['post'] ) )
				$html .= ' checked="checked"';
			$html .= ' />';
			$html .= '<label for="ptdField' . $i . '">' . __( 'Front Page', 'pagination-test-drive' ) . '</label>' . "\n";
			$html .= '</li>' . "\n";
			$i++;
		}
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->query_var != 'post_format' ) {
				$taxonomy->name = ( $taxonomy->name == 'post_tag' ) ? 'tag' : esc_attr( $taxonomy->name );
				$html .= '<li>' . "\n";
				$html .= '<input id="ptdField' . $i . '" type="checkbox" name="post[]" value="' . esc_attr( $taxonomy->name ) . '"';
				if ( !empty ( $ptd_array['post'] ) && in_array( $taxonomy->name, $ptd_array['post'] ) )
					$html .= ' checked="checked"';
				$html .= ' />';
				$html .= '<label for="ptdField' . $i . '">' . esc_html( $taxonomy->label ) . '</label>' . "\n";
				$html .= '</li>' . "\n";
				$i++;
			}
		}
		$html .= '</ul>' . "\n";
	}
	$html .= '</li>' . "\n";
	$html .= '</ul>' . "\n";
}

$args = array (
	'public' => true,
	'_builtin' => false
);
$post_types = get_post_types( $args, 'objects' );

if ( !empty ( $post_types ) && !is_wp_error( $post_types ) ) {
	foreach ( $post_types as $post_type_obj ) {
		$taxonomies = get_object_taxonomies( $post_type_obj->name, 'objects' );
		if ( $post_type_obj->has_archive || ( !empty ( $taxonomies ) && !is_wp_error( $taxonomies ) ) ) {
			$html .= '<ul>' . "\n";
			$html .= '<li>' . "\n";
			$html .= '<p>' . esc_html( $post_type_obj->label ) . '</p>' . "\n";
			$html .= '<ul>' . "\n";
			if ( $post_type_obj->has_archive ) {
				$html .= '<li>' . "\n";
				$html .= '<input id="ptdField' . $i . '" type="checkbox" name="' . esc_attr( $post_type_obj->name ) . '[]" value="post_type_archive"';
				if ( !empty ( $ptd_array[$post_type_obj->name] ) && in_array( 'post_type_archive', $ptd_array[$post_type_obj->name] ) )
					$html .= ' checked="checked"';
				$html .= ' />';
				$html .= '<label for="ptdField' . $i . '">' . __( 'Archive Index', 'pagination-test-drive' ) . '</label>' . "\n";
				$html .= '</li>' . "\n";
				$i++;
			}
			if ( !empty ( $taxonomies ) && !is_wp_error( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy ) {
					if ( $taxonomy->public ) {
						$html .= '<li>' . "\n";
						$html .= '<input id="ptdField' . $i . '" type="checkbox" name="' . esc_attr( $post_type_obj->name ) . '[]" value="tax_' . esc_attr( $taxonomy->name ) . '"';
						if ( !empty ( $ptd_array[$post_type_obj->name] ) && in_array( 'tax_' . esc_attr( $taxonomy->name ), $ptd_array[$post_type_obj->name] ) )
							$html .= ' checked="checked"';
						$html .= ' />';
						$html .= '<label for="ptdField' . $i . '">' . esc_html( $taxonomy->label ) . '</label>' . "\n";
						$html .= '</li>' . "\n";
						$i++;
					}
				}
			}
			$html .= '</ul>' . "\n";
			$html .= '</li>' . "\n";
			$html .= '</ul>' . "\n";
		}
	}
}

?>
<dl>
<dt>
<p><?php echo __( 'Enabled Archives', 'pagination-test-drive' ); ?></p>
<p><?php echo __( 'Which archives will you test?', 'pagination-test-drive' ); ?></p>
</dt>
<dd>
<?php echo $html; ?>
</dd>
</dl>
<!-- .myFields --></div>
<input type="submit" value="<?php echo __( 'Save Changes', 'pagination-test-drive' ); ?>" class="button-primary">
<input type="hidden" name="mode" value="save" />
</form>
<!-- .wrapInner --></div>
<!-- .wrap --></div>
<?php
	}
}
new Pagination_Test_Drive();
?>