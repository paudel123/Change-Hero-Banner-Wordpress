<?php
/**
 * @package  Lekha Hero Banner Plugin
 */
/*
Plugin Name: Lekha Hero Banner Plugin
Description: This plugin allows to change the hero banner image according to selected page.
Version: 1.0.0
Author: Lekha Nath Paudel
Text Domain: lekha-hero-banner-plugin
*/

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
defined( 'ABSPATH' ) or die( 'Hey, you can not directly access this file' );


register_activation_hook( __FILE__, array('Lekha_Hero_Banner_Plugin', 'activation'));

class Lekha_Hero_Banner_Plugin
{
	/**
	 * Required variables
	 */
	
	protected $textdomain;
	protected $plugin_name;
	protected $plugin_path;
	protected $plugin_dir_path;

	/**
	 * Constructor initializes variables
	*/
	
	function __construct() {
		$this->textdomain      = "lekha-hero-banner-plugin";
		$this->plugin_path     = __FILE__;
		$this->plugin_dir_path = plugin_dir_path( $args['plugin_path'] );
		$this->plugin_name     = plugin_basename( $args['plugin_path'] );
		$this->hook( 'plugins_loaded', 'parent_plugins_loaded' );
		load_plugin_textdomain( 'lekha-hero-banner-plugin' );
		$this->hook( 'init' );
	}

	public static function activation() {
		load_plugin_textdomain( 'lekha-hero-banner-plugin' );
	}

	function hook( $hook ) {
		$priority = 10;
		$method   = $this->check_special_chars( $hook );
		$args     = func_get_args();
		unset( $args[0] ); // Filter name.

		foreach ( (array) $args as $arg ) {
			if ( is_int( $arg ) ) {
				$priority = $arg;
			} else {
				$method = $arg;
			}
		}

		return add_action( $hook, array( $this, $method ), $priority, 999 );
	}

	function check_special_chars( $method ) {
		return str_replace( array( '.', '-' ), '_', $method );
	}

	function init() {

		$this->hook( 'pick_hero_banner_image' );
		$this->hook( 'pick_hero_banner_image_data' );
		$this->hook( 'add_meta_boxes' );

		// Save info.
		$this->hook( 'save_post' );
		
		// Edit forms.
		foreach ( get_taxonomies( array( 'show_ui' => true ) ) as $_tax ) {
			$this->hook( "{$_tax}_edit_form", 'edit_form', 10 );
		}

		$this->hook( 'admin_init', 'add_settings_field' );
	}

	function pick_hero_banner_image( $header_url ) {

		$active_header = $this->get_active_post_header();
		if ( isset( $active_header ) && $active_header ) {
			$header_url = $active_header;
		}

		return $header_url;
	}

	 function pick_hero_banner_image_data( $header_data ) {
		$active_header = false;

		$active_header = $this->get_active_post_header();


		if ( $active_header ) {
			$attachment_id = $this->attachment_id_from_url( $active_header );

			if ( 0 !== $attachment_id ) {
				$data        = wp_get_attachment_metadata( $attachment_id );
				$header_data = (object) array(
					'attachment_id' => $attachment_id,
					'url'           => $active_header,
					'thumbnail_url' => $active_header,
					'height'        => isset( $data['height'] ) ? $data['height'] : 0,
					'width'         => isset( $data['width'] ) ? $data['width'] : 0,
				);
			}
		}

		return $header_data;
	}

	function add_meta_boxes( $post_type ) {
		add_meta_box('lekha-hero-banner-plugin', esc_html__( 'Header' ),
			array($this, 'display_meta_box'),
			$post_type, 'normal', 'high'
		);
	}

	function add_settings_field() {
		add_settings_field($this->textdomain, esc_html__( 'Choose Header', 'lekha-hero-banner-plugin' ),
			array($this, 'select_header',
			),
			$this->textdomain,
			$this->textdomain
		);
	}

	function edit_form() {
		do_settings_sections( $this->textdomain );
	}

	function display_meta_box( $post ) {
		$active = $this->get_active_post_header( $post->ID, true );
		$this->header_selection_form( $active );
	}

    function select_header() {
		$active = '';
		$active = get_option( 'wpdh_tax_meta', '' );
		if ( $active ) {
			$active = isset( $active[ $tag->term_taxonomy_id ] ) ? $active[ $tag->term_taxonomy_id ] : '';
		}
		// If no header set yet, get default header.
		if ( ! $active ) {
			$active = get_theme_mod( 'header_image' );
		}

		$this->header_selection_form( $active );
	}
	
	function save_post( $post_ID ) {
		$value = esc_url_raw( $_POST[ $this->textdomain ] );
		update_post_meta( $post_ID, '_wpdh_display_header', $value );
		return $post_ID;
	}

	function header_selection_form( $active = '' ) {
		$headers = $this->get_headers();

		if ( empty( $headers ) ) {
			printf(
				/* translators: Upload URL. */
				wp_kses_post( __( 'The are no headers available. Please <a href="%s">upload a header image</a>!', 'lekha-hero-banner-plugin' ) ),
				esc_url( add_query_arg( array( 'page' => 'custom-header' ), admin_url( 'themes.php' ) ) )
			);

			return;
		}

		foreach ( array_keys( $headers ) as $header ) {
			foreach ( array( 'url', 'thumbnail_url' ) as $url ) {
				$headers[ $header ][ $url ] = sprintf(
					$headers[ $header ][ $url ],
					get_template_directory_uri(),
					get_stylesheet_directory_uri()
				);
			}
		}

		wp_nonce_field( 'lekha-hero-banner-plugin', 'lekha-hero-banner-plugin-nonce' );
		?>
		<div class="available-headers">
			<?php
			foreach ( $headers as $header_key => $header ) :
				$header_url       = $header['url'];
				$header_thumbnail = $header['thumbnail_url'];
				$header_desc      = isset( $header['description'] ) ? $header['description'] : '';
			?>
			<div class="default-header">
				<label>
					<input name="lekha-hero-banner-plugin" type="radio" value="<?php echo esc_attr( $header_url ); ?>" <?php checked( $header_url, $active ); ?> />
					<img width="230" src="<?php echo esc_url( $header_thumbnail ); ?>" alt="<?php echo esc_attr( $header_desc ); ?>" title="<?php echo esc_attr( $header_desc ); ?>"/>
				</label>
			</div>
			<?php endforeach; ?>
			<div class="clear"></div>
		</div>
	<?php
	}

	function get_headers() {
		global $_wp_default_headers;
		$headers = array_merge( (array) $_wp_default_headers, get_uploaded_header_images() );

		return (array) apply_filters( 'wpdh_get_headers', $headers );
	}

	function get_active_post_header( $post_ID = 0, $raw = false ) {
		if ( ! $post_ID ) {
			$post_ID = get_post()->ID;
		}

		$active = get_post_meta( $post_ID, '_wpdh_display_header', true );

		return apply_filters( 'wpdh_get_active_post_header', $this->get_active_header( $active, $raw ) );
	}

    function get_active_header( $header, $raw = false ) {
		if ( 'random' === $header && ! $raw ) {
			$headers = $this->get_headers();
			$header  = sprintf(
				$headers[ array_rand( $headers ) ]['url'],
				get_template_directory_uri(),
				get_stylesheet_directory_uri()
			);
		}

		return apply_filters( 'wpdh_get_active_header', $header );
	}

	function attachment_id_from_url( $image_url ) {
		global $wpdb;

		$attachment_id = wp_cache_get( 'wpdh_attachment_id_' . $image_url, 'post' );

		if ( false === $attachment_id ) {
			$attachment_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid=%s LIMIT 1;",
				$image_url
			) );

			wp_cache_set( 'wpdh_attachment_id_' . $image_url, $attachment_id, 'post' );
		}

		return absint( $attachment_id );
	}
} // End of class Lekha_Hero_Banner_Plugin.

function start() {
	new Lekha_Hero_Banner_Plugin();
}

add_action( 'init', 'start', 1 );