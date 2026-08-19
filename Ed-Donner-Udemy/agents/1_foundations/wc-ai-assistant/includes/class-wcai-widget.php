<?php
declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the storefront bubble and hands it its configuration.
 */
final class WCAI_Widget {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		if ( is_admin() || ! WCAI_Settings::get( 'enabled' ) ) {
			return;
		}

		if ( '' === (string) WCAI_Settings::get( 'api_key' ) ) {
			return;
		}

		wp_enqueue_script(
			'wcai-widget',
			WCAI_URL . 'assets/widget.js',
			[],
			WCAI_VERSION,
			true
		);

		$image = (string) WCAI_Settings::get( 'launcher_image' );
		$style = (string) WCAI_Settings::get( 'launcher_style' );

		wp_localize_script(
			'wcai-widget',
			'WCAI_CONFIG',
			[
				'launcher'    => [
					'style'    => $style,
					'text'     => (string) WCAI_Settings::get( 'launcher_text' ),
					'side'     => (string) WCAI_Settings::get( 'launcher_side' ),
					'offset'   => (int) WCAI_Settings::get( 'launcher_offset' ),
					// An uploaded image wins over the built-in set; the admin picker
					// keeps only one of the two set at a time.
					'imageUrl' => $image,
					'icon'     => '' === $image && 'text' !== $style
						? WCAI_Icons::svg( (string) WCAI_Settings::get( 'launcher_icon' ), 24 )
						: '',
				],
				'endpoint'    => esc_url_raw( rest_url( 'wc-ai/v1/chat' ) ),
				'name'        => (string) WCAI_Settings::get( 'bot_name' ),
				'greeting'    => (string) WCAI_Settings::get( 'greeting' ),
				'status'      => (string) WCAI_Settings::get( 'status_text' ),
				'placeholder' => (string) WCAI_Settings::get( 'placeholder' ),
				'footnote'    => (string) WCAI_Settings::get( 'footnote' ),
				'errorText'   => (string) WCAI_Settings::get( 'error_text' ),
				'accent'      => (string) WCAI_Settings::get( 'accent' ),
				// Versioned by hand: the shadow root loads this itself, so it
				// never passes through wp_enqueue_style's cache busting.
				'styles'      => add_query_arg( 'ver', WCAI_VERSION, WCAI_URL . 'assets/widget.css' ),
			]
		);
	}
}
