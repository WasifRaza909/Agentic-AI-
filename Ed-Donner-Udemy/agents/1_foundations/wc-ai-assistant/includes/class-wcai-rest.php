<?php
declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/wc-ai/v1/chat
 *
 * Runs the same loop as chat() in the Python twin: call the model, execute any
 * tool calls, feed the results back, repeat until it returns prose.
 */
final class WCAI_REST {

	private const NAMESPACE      = 'wc-ai/v1';
	private const MAX_TOOL_LOOPS = 5;
	private const MAX_HISTORY    = 12;
	private const MAX_MESSAGE    = 1000;

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/chat',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'chat' ],
				// Public by design: shoppers are not logged in. Abuse is handled by
				// the same-origin check and the per-IP rate limit below.
				'permission_callback' => '__return_true',
				'args'                => [
					'message' => [
						'required' => true,
						'type'     => 'string',
					],
					'history' => [
						'required' => false,
						'type'     => 'array',
					],
					'page'    => [
						'required' => false,
						'type'     => 'string',
					],
				],
			]
		);
	}

	public static function chat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! WCAI_Settings::get( 'enabled' ) ) {
			return new WP_Error( 'wcai_disabled', 'The assistant is currently switched off.', [ 'status' => 503 ] );
		}

		if ( ! self::is_same_origin( $request ) ) {
			return new WP_Error( 'wcai_bad_origin', 'Requests must come from this site.', [ 'status' => 403 ] );
		}

		if ( ! self::within_rate_limit() ) {
			return new WP_Error(
				'wcai_rate_limited',
				'You have sent a lot of messages. Please try again in a little while.',
				[ 'status' => 429 ]
			);
		}

		$message = trim( (string) $request->get_param( 'message' ) );

		if ( '' === $message ) {
			return new WP_Error( 'wcai_empty', 'Please type a message.', [ 'status' => 400 ] );
		}

		$message  = mb_substr( $message, 0, self::MAX_MESSAGE );
		$page_url = esc_url_raw( (string) $request->get_param( 'page' ) );

		$messages = array_merge(
			[
				[ 'role' => 'system', 'content' => WCAI_Context::system_prompt() ],
			],
			self::sanitize_history( (array) $request->get_param( 'history' ) ),
			[
				[ 'role' => 'user', 'content' => $message ],
			]
		);

		$client = new WCAI_Gemini_Client();
		$tools  = WCAI_Tools::definitions();

		for ( $loop = 0; $loop <= self::MAX_TOOL_LOOPS; $loop++ ) {
			$response = $client->complete( $messages, $tools );

			if ( is_wp_error( $response ) ) {
				return self::friendly_error( $response );
			}

			$choice = $response['choices'][0] ?? null;

			if ( ! is_array( $choice ) ) {
				return new WP_Error( 'wcai_no_choice', 'The assistant returned an empty response.', [ 'status' => 502 ] );
			}

			$assistant = $choice['message'] ?? [];

			if ( 'tool_calls' !== ( $choice['finish_reason'] ?? '' ) || empty( $assistant['tool_calls'] ) ) {
				return new WP_REST_Response(
					[
						'reply' => (string) ( $assistant['content'] ?? '' ),
						'model' => $client->last_model(),
					],
					200
				);
			}

			$messages[] = $assistant;
			$messages   = array_merge( $messages, WCAI_Tools::handle( $assistant['tool_calls'], $page_url ) );
		}

		return new WP_Error(
			'wcai_loop_limit',
			'The assistant got stuck. Please rephrase your question.',
			[ 'status' => 500 ]
		);
	}

	/**
	 * Keeps only well-formed user/assistant turns from the client-supplied history.
	 *
	 * The browser controls this array, so a visitor could otherwise inject a fake
	 * "system" turn and rewrite the assistant's instructions.
	 */
	private static function sanitize_history( array $history ): array {
		$clean = [];

		foreach ( $history as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}

			$role    = (string) ( $turn['role'] ?? '' );
			$content = trim( (string) ( $turn['content'] ?? '' ) );

			if ( ! in_array( $role, [ 'user', 'assistant' ], true ) || '' === $content ) {
				continue;
			}

			$clean[] = [
				'role'    => $role,
				'content' => mb_substr( $content, 0, self::MAX_MESSAGE ),
			];
		}

		return array_slice( $clean, -self::MAX_HISTORY );
	}

	private static function is_same_origin( WP_REST_Request $request ): bool {
		$site   = wp_parse_url( home_url(), PHP_URL_HOST );
		$origin = $request->get_header( 'origin' ) ?: $request->get_header( 'referer' );

		if ( ! $origin ) {
			// Some privacy tools strip both headers; do not lock those visitors out.
			return true;
		}

		return wp_parse_url( $origin, PHP_URL_HOST ) === $site;
	}

	private static function within_rate_limit(): bool {
		$limit = (int) WCAI_Settings::get( 'rate_limit' );
		$key   = 'wcai_rl_' . md5( self::client_ip() );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return true;
	}

	private static function client_ip(): string {
		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) );
			$ip    = trim( explode( ',', $value )[0] );

			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return 'unknown';
	}

	/**
	 * Logs the real cause, returns something a shopper can read.
	 */
	private static function friendly_error( WP_Error $error ): WP_Error {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[WC AI Assistant] ' . $error->get_error_message() );
		}

		$status = (int) ( $error->get_error_data()['status'] ?? 500 );

		if ( 429 === $status ) {
			return new WP_Error(
				'wcai_upstream_busy',
				'We are getting a lot of questions right now. Please try again shortly.',
				[ 'status' => 429 ]
			);
		}

		return new WP_Error(
			'wcai_upstream',
			'Sorry, I could not reach the assistant just now. Please try again in a moment.',
			[ 'status' => 502 ]
		);
	}
}
