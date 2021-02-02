<?php
/**
 * A custom wrapper for error_log, to provide more details about the error or debug label.
 *
 * @package Utility
 */

if ( ! function_exists( 'inspect' ) ) {
	/**
	 * The one and only function, which triggers the logging.
	 *
	 * @param  string $label Basic debug label or description of the data.
	 * @param  mixed  $data Array or string for additional info.
	 */
	function inspect( $label, $data = '__undefin_e_d__' ) {
		$val       = $label;
		$backtrace = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		if ( '__undefin_e_d__' !== $data ) {
			$output = PHP_EOL . $label . PHP_EOL . print_r( $data, true ) . PHP_EOL; // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		} else {
			$data = $label;

			$src  = file( $backtrace[0]['file'] );
			$line = $src[ $backtrace[0]['line'] - 1 ];

			// Let's match the function call and the last closing bracket.
			preg_match( '#inspect\((.+)\)#', $line, $match );

			/**
			 * Let's count brackets to see how many of them actually belong to the var name.
			 * Eg:   die(inspect($this->getUser()->hasCredential("delete")));
			 * We want:   $this->getUser()->hasCredential("delete")
			*/
			$max     = strlen( $match[1] );
			$varname = '';
			$c       = 0;
			for ( $i = 0; $i < $max; $i++ ) {
				if ( '(' === $match[1][ $i ] ) {
					$c++;
				} elseif ( ')' === $match[1][ $i ] ) {
					$c--;
				}
				if ( $c < 0 ) {
					break;
				}
				$varname .= $match[1][ $i ];
			}
			$label = $varname;

			if ( false === strpos( $label, '$' ) ) {
				$output = $data . ' ';
			} else {
				$output = PHP_EOL . 'Variable: ' . trim( $label ) . PHP_EOL . print_r( $data, true ) . PHP_EOL; // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}

		// Get the calling function details.
		if ( ! empty( $backtrace[1]['function'] ) ) {
			$output .= '[from ' . $backtrace[1]['function'] . '()]';
		}

		error_log( $output ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}
