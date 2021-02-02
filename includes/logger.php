<?php
if ( ! class_exists( 'Yoko_Logger' ) ) {
	/**
	 * Class for logging events and errors
	 *
	 * @package     Yoko Logger
	 * @link        https://github.com/tomhoag/wp-logging
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 */
	class Yoko_Logger {
		/**
		 * Class constructor.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'set_up_log_file_directory' ) );
		}

		/**
		 * Make sure our log directory exists and isn't browsable.
		 *
		 * @return void
		 */
		public function set_up_log_file_directory() {
			// Ensure the log file directory exists and is writable.
			$uploads_directory = wp_upload_dir();
			$log_directory     = $uploads_directory['basedir'] . '/logs/';

			if ( ! is_dir( $log_directory ) ) {
				mkdir( $log_directory, 0755, true );
			}

			// Make sure there's an index.php file in the log file's directory to prevent directory browsing.
			if ( ! file_exists( $log_directory . 'index.php' ) ) {
				$log_index_file = fopen( $log_directory . 'index.php', 'w' );
				fwrite( $log_index_file, '<?php' . PHP_EOL . '// Silence is golden.' . PHP_EOL );
				fclose( $log_index_file );
			}
		}

		/**
		 * Log types
		 *
		 * Sets up the default log types and allows for new ones to be created
		 *
		 * @return     array
		 */
		private static function log_types() {
			$terms = array(
				'error',
				'event',
			);

			return apply_filters( 'wp_log_types', $terms );
		}

		/**
		 * Check if a log type is valid
		 *
		 * Checks to see if the specified type is in the registered list of types
		 *
		 * @param      string $type The specified type.
		 * @return     array
		 */
		private static function valid_type( $type ) {
			return in_array( $type, self::log_types(), true );
		}

		/**
		 * Get details about the calling plugin and return a standard-but-non-obvious log file location.
		 *
		 * @param array $backtrace Backtrace data that helps us identify the calling plugin.
		 * @return mixed On success, returns the file path as a string. On failure, returns false.
		 */
		private static function get_log_path( $backtrace ) {
			// This class could be included in more than one plugin, so we have to jump through
			// a few hoops to get information about the specific plugin that called it.
			$path_to_calling_file = $backtrace[0]['file'];
			$plugin_basename      = plugin_basename( $path_to_calling_file );

			preg_match( '/.*?\//i', $plugin_basename, $plugin_name );
			if ( ! empty( $plugin_name[0] ) ) {
				$plugin_name = trim( $plugin_name[0], '/' );

				$plugin_path = substr( $path_to_calling_file, 0, strpos( $path_to_calling_file, $plugin_name ) ) . $plugin_name . '/' . $plugin_name . '.php';

				if ( file_exists( $plugin_path ) ) {
					$current_plugin_data = get_file_data(
						$plugin_path,
						array(
							'name'    => 'Plugin Name',
							'version' => 'Version',
							'text'    => 'Text Domain',
						)
					);

					$current_plugin_data['slug'] = str_replace( '-', '_', sanitize_title( $current_plugin_data['name'] ) );

					// Generate an apparently-random string to prevent scrapers/web searches from finding our log.
					// It's not actually random, because we want to be able to write to it repeatedly without storing it anywhere.
					$blog_name         = get_bloginfo( 'name' );
					$random_string     = md5( $current_plugin_data['slug'] . ':' . $blog_name );
					$uploads_directory = wp_upload_dir();
					$log_filename      = $current_plugin_data['slug'] . '_' . gmdate( 'Y-m' ) . '_' . substr( $random_string, 2, 8 ) . '.log';

					$log_file_path = $uploads_directory['basedir'] . '/logs/' . $log_filename;

					return $log_file_path;
				}
			}

			return false;
		}

		/**
		 * Create new log entry
		 *
		 * This is just a simple and fast way to log something. Use self::insert_log()
		 * if you need to store custom meta data
		 *
		 * @param       string $message A human-readable explanation of what's being logged.
		 * @param       string $variable An optional variable to include for debugging purposes.
		 * @param       string $type What type of message this is. Options are 'error' and 'event'.
		 * @return      void
		 */
		public static function add( $message = '', $variable = '__undefin_e_d__', $type = null ) {
			$log_data = array(
				'message'  => $message,
				'log_type' => $type,
			);

			if ( '__undefin_e_d__' !== $variable ) {
				$log_data['variable'] = $variable;
			}

			$log_file_path = self::get_log_path( debug_backtrace() );

			// Prepare the content to add to the log file.
			$log_line = '[' . date( 'D M d H:i:s Y' ) . '] ';
			if (
				$log_data['log_type'] &&
				self::valid_type( $log_data['log_type'] )
			) {
				$log_line .= '[' . $log_data['log_type'] . '] ';
			}

			$user_ip = self::get_user_ip_address();
			if ( ! empty( $user_ip ) ) {
				$log_line .= '[client ' . $user_ip . '] ';
			}

			if ( ! empty( $log_data['message'] ) ) {
				$log_line .= $log_data['message'] . ' ';

				if ( ! empty( $log_data['variable'] ) ) {
					$log_line .= '\n';
				}
			}

			if ( ! empty( $log_data['variable'] ) ) {
				$log_line .= print_r( $log_data['variable'], true ) . ' ';
			}

			// Write the record to the log file.
			$log_line = str_replace( PHP_EOL, '\n', $log_line );

			$log_file = fopen( $log_file_path, 'a' );
			fwrite( $log_file, $log_line . PHP_EOL );
			fclose( $log_file );
		}

		/**
		 * A fairly reliable IP getter.
		 *
		 * @return string
		 */
		public static function get_user_ip_address() {
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				// IP from shared internet.
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				// IP passed from proxy.
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
			return filter_var( $ip, FILTER_VALIDATE_IP );
		}
	}
	$GLOBALS['yoko_logger'] = new Yoko_Logger();
}
