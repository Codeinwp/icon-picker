<?php
/**
 * SVG icon handler
 *
 * @package Icon_Picker
 * @author  Dzikri Aziz <kvcrvt@gmail.com>
 */

use enshrined\svgSanitize\Sanitizer;

require_once dirname( __FILE__ ) . '/image.php';

/**
 * Image icon
 *
 */
class Icon_Picker_Type_Svg extends Icon_Picker_Type_Image {

	/**
	 * Icon type ID
	 *
	 * @since  0.1.0
	 * @access protected
	 * @var    string
	 */
	protected $id = 'svg';

	/**
	 * Template ID
	 *
	 * @since  0.1.0
	 * @access protected
	 * @var    string
	 */
	protected $template_id = 'svg';

	/**
	 * Mime type
	 *
	 * @since  0.1.0
	 * @access protected
	 * @var    string
	 */
	protected $mime_type = 'image/svg+xml';


	/**
	 * Constructor
	 *
	 * @since 0.1.0
	 * @param array $args Misc. arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'SVG', 'icon-picker' );

		parent::__construct( $args );
		add_filter( 'upload_mimes', array( $this, '_add_mime_type' ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, '_check_svg_and_sanitize' ) );
	}

	/**
	 * Sanitize the SVG
	 *
	 * @param string $file Temp file path.
	 *
	 * @return bool|int
	 */
	protected function sanitize_svg( $file ) {
		// We can ignore the phpcs warning here as we're reading and writing to the Temp file.
		$dirty = file_get_contents( $file ); // phpcs:ignore

		// Is the SVG gzipped? If so we try and decode the string.
		$is_zipped = $this->is_gzipped( $dirty );
		if ( $is_zipped && ( ! function_exists( 'gzdecode' ) || ! function_exists( 'gzencode' ) ) ) {
			return false;
		}

		if ( $is_zipped ) {
			$dirty = gzdecode( $dirty );

			// If decoding fails, bail as we're not secure.
			if ( false === $dirty ) {
				return false;
			}
		}

		$sanitizer = new Sanitizer();
		$clean     = $sanitizer->sanitize( $dirty );

		if ( false === $clean ) {
			return false;
		}

		// If we were gzipped, we need to re-zip.
		if ( $is_zipped ) {
			$clean = gzencode( $clean );
		}

		// We can ignore the phpcs warning here as we're reading and writing to the Temp file.
		file_put_contents( $file, $clean ); // phpcs:ignore

		return true;
	}

	/**
	 * Check if the contents are gzipped
	 *
	 * @see http://www.gzip.org/zlib/rfc-gzip.html#member-format
	 *
	 * @param string $contents Content to check.
	 *
	 * @return bool
	 */
	protected function is_gzipped( $contents ) {
		// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
		if ( function_exists( 'mb_strpos' ) ) {
			return 0 === mb_strpos( $contents, "\x1f" . "\x8b" . "\x08" );
		} else {
			return 0 === strpos( $contents, "\x1f" . "\x8b" . "\x08" );
		}
		// phpcs:enable
	}

	/**
	 * Check if the file is an SVG, if so handle appropriately and sanitize.
	 *
	 * @param array $file An array of data for a single file.
	 *
	 * @return void
	 */
	public function _check_svg_and_sanitize( $file ) {
		// Ensure we have a proper file path before processing.
		if ( ! isset( $file['tmp_name'] ) ) {
			return $file;
		}

		$file_name   = isset( $file['name'] ) ? $file['name'] : '';
		$wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file_name );
		$type        = ! empty( $wp_filetype['type'] ) ? $wp_filetype['type'] : '';

		if ( 'image/svg+xml' === $type ) {
			if ( ! current_user_can( 'upload_files' ) ) {
				$file['error'] = __(
					'Sorry, you are not allowed to upload files.',
					'icon-picker'
				);

				return $file;
			}

			if ( ! $this->sanitize_svg( $file['tmp_name'] ) ) {
				$file['error'] = __(
					"Sorry, this file couldn't be sanitized so for security reasons wasn't uploaded",
					'icon-picker'
				);
			}
		}

		return $file;
	}


	/**
	 * Add SVG support
	 *
	 * @since   0.1.0
	 * @wp_hook filter upload_mimes
	 * @link    https://codex.wordpress.org/Plugin_API/Filter_Reference/upload_mimes
	 * @author  Ethan Clevenger (GitHub: ethanclevenger91; email: ethan.c.clevenger@gmail.com)
	 *
	 * @return  array
	 */
	public function _add_mime_type( array $mimes ) {
		if ( ! isset( $mimes['svg'] ) ) {
			$mimes['svg'] = $this->mime_type;
		}

		return $mimes;
	}


	/**
	 * Get extra properties data
	 *
	 * @since  0.1.0
	 * @access protected
	 * @return array
	 */
	protected function get_props_data() {
		return array(
			'mimeTypes' => array( $this->mime_type ),
		);
	}


	/**
	 * Media templates
	 *
	 * @since  0.1.0
	 * @return array
	 */
	public function get_templates() {
		$templates = array(
			'icon' => '<img src="{{ data.url }}" class="_icon" />',
			'item' => sprintf(
				'<div class="attachment-preview js--select-attachment svg-icon">
					<div class="thumbnail">
						<div class="centered">
							<img src="{{ data.url }}" alt="{{ data.alt }}" class="_icon _{{data.type}}" />
						</div>
					</div>
				</div>
				<a class="check" href="#" title="%s"><div class="media-modal-icon"></div></a>',
				esc_attr__( 'Deselect', 'menu-icons' )
			),
		);

		/**
		 * Filter media templates
		 *
		 * @since 0.1.0
		 * @param array $templates Media templates.
		 */
		$templates = apply_filters( 'icon_picker_svg_media_templates', $templates );

		return $templates;
	}
}
