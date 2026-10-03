<?php
/**
 * Bojaghi Template
 *
 * @package Bojaghi\Template
 */

declare( strict_types=1 );

namespace Bojaghi\Template;

use BadMethodCallException;
use Bojaghi\Helper\Helper;
use InvalidArgumentException;

/**
 * Template Class
 */
final class Template {
	/**
	 * List of start() method call arguments.
	 * It should be empty before template() ends.
	 *
	 * @var array
	 */
	private array $buffers;

	/**
	 * Context variables as associative array.
	 *
	 * @var array
	 */
	private array $context;

	/**
	 * Parent template name.
	 *
	 * @var string
	 */
	private string $extends;

	/**
	 * Supported file extensions.
	 *
	 * @var array
	 */
	private array $extensions;

	/**
	 * Supported file infix, e.g. my-template.infix.php
	 *
	 * @var string
	 */
	private string $infix;

	/**
	 * Absolute paths for retrieving template path
	 *
	 * @var array
	 */
	private array $scopes;

	/**
	 * Array of start(), end() call results.
	 *
	 * @var array
	 */
	private array $store;

	/**
	 * Constructor
	 *
	 * @param array|string $args Config for the class.
	 *
	 * @throws InvalidArgumentException When extension value is valid.
	 */
	public function __construct( array|string $args = '' ) {
		$args = Helper::load_config( $args );
		$args = wp_parse_args(
			$args,
			array(
				'extensions' => array( 'html', 'php' ),
				'infix'      => '',
				'scopes'     => array(),
			),
		);

		$this->extensions = array_filter(
			array_unique(
				array_map(
					fn( $e ) => ltrim( $e, '.' ),
					(array) $args['extensions'],
				),
			),
		);
		if ( empty( $this->extensions ) ) {
			throw new InvalidArgumentException( 'Extensions cannot be empty.' );
		}

		$infix       = '.' . trim( $args['infix'], '.' );
		$this->infix = '.' === $infix ? '' : $infix;

		$this->scopes = array_filter(
			array_unique(
				array_map(
					'untrailingslashit',
					(array) $args['scopes'],
				),
			),
		);
		if ( empty( $this->scopes ) ) {
			throw new InvalidArgumentException( 'Template scopes cannot be empty.' );
		}

		$this->reset();
	}

	/**
	 * Assign context value
	 *
	 * @param string $key   Context name.
	 * @param mixed  $value Context value.
	 *
	 * @return $this
	 */
	public function assign( string $key, mixed $value ): self {
		$this->store[ $key ] = $value;

		return $this;
	}

	/**
	 * Declare the end of inline sub-template.
	 *
	 * @return void
	 */
	public function end(): void {
		$content = ob_get_clean();
		$name    = (string) array_pop( $this->buffers );

		$this->store[ $name ] = $content;
	}

	/**
	 * Extend a parent template.
	 *
	 * @param string $parent_name Parent template name to find.
	 *
	 * @return $this
	 */
	public function extends( string $parent_name ): self {
		$this->extends = $parent_name;

		return $this;
	}

	/**
	 * Get a inline sub-template.
	 *
	 * @param string $name     Identif string to find.
	 * @param mixed  $fallback Default value when not found.
	 *
	 * @return mixed
	 */
	public function fetch( string $name, mixed $fallback = '' ): mixed {
		return $this->store[ $name ] ?? $fallback;
	}

	/**
	 * Get a context value.
	 *
	 * @param string $key      Context name.
	 * @param mixed  $fallback Default value when not found.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $fallback = '' ): mixed {
		return $this->context[ $key ] ?? $fallback;
	}

	/**
	 * Declare the beginning of inline sub-template.
	 *
	 * @param string $name Identifier string.
	 *
	 * @return void
	 */
	public function start( string $name ): void {
		ob_start();

		/**
		 * Function array_push() gives warning in PhpStorm.
		 *
		 * @noinspection PhpArrayPushWithOneElementInspection
		 */
		array_push( $this->buffers, $name );
	}

	/**
	 * Get and render a template.
	 *
	 * @param string $tmpl_name Template name to render.
	 * @param array  $context   Context variables.
	 *
	 * @return string
	 *
	 * @throws BadMethodCallException When start, end pairs unmatch.
	 */
	public function template( string $tmpl_name, array $context = array() ): string {
		$output = '';

		// Context.
		if ( $context ) {
			$this->context = array_merge( $this->context, $context );
		}
		unset( $context );

		// 1st pass.
		$template_path = $this->get_template_path( trim( $tmpl_name, '\\/' ) );
		if ( $template_path ) {
			ob_start();
			include $template_path;
			$output = $output . ob_get_clean();
		}

		// 2nd pass.
		$parent_template_path = $this->get_template_path( trim( $this->extends, '\\/' ) );
		if ( $parent_template_path ) {
			ob_start();
			include $parent_template_path;
			$output = ob_get_clean() . $output;
		}

		// Buffer check.
		if ( ! empty( $this->buffers ) ) {
			throw new BadMethodCallException( 'start(), and end() method pairs do not match.' );
		}

		$this->reset();

		return $output;
	}

	/**
	 * Get a fragment
	 *
	 * @param string $fragment_name Fragment name.
	 *
	 * @return string
	 */
	public function fragment( string $fragment_name ): string {
		$output        = '';
		$fragment_path = $this->get_template_path( trim( $fragment_name, '\\/' ) );

		if ( $fragment_path ) {
			ob_start();
			include $fragment_path;
			$output = ob_get_clean() . $output;
		}

		return $output;
	}

	/**
	 * Retrieve template path by name.
	 *
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function get_template_path( string $template_name ): string {
		$output = '';

		foreach ( $this->scopes as $scope ) {
			foreach ( $this->extensions as $ext ) {
				$path = "$scope/$template_name$this->infix.$ext";
				if ( self::is_valid_file( $path ) ) {
					$output = $path;
					break 2;
				}
			}
		}

		return $output;
	}

	/**
	 * Initialize private variables.
	 *
	 * @return void
	 */
	private function reset(): void {
		$this->buffers = array();
		$this->context = array();
		$this->extends = '';
		$this->store   = array();
	}

	/**
	 * Check the file is valid.
	 *
	 * @param string $path Path to check.
	 *
	 * @return bool
	 */
	private static function is_valid_file( string $path ): bool {
		return $path && file_exists( $path ) && is_file( $path ) && is_readable( $path );
	}
}
