<?php
declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to Gemini's OpenAI-compatible chat completions endpoint.
 *
 * Port of _create_completion() in the Python twin: try each model in order and
 * fall through to the next one only when the failure looks like a quota problem.
 * Any other error aborts immediately, so genuine bugs surface instead of being
 * masked by a retry.
 */
final class WCAI_Gemini_Client {

	private const TIMEOUT = 45;

	private string $last_model = '';

	public function last_model(): string {
		return $this->last_model;
	}

	/**
	 * @param array $messages OpenAI-format message list.
	 * @param array $tools    OpenAI-format tool definitions.
	 * @return array|WP_Error Decoded response body, or an error.
	 */
	public function complete( array $messages, array $tools ): array|WP_Error {
		$models     = WCAI_Settings::models();
		$last_error = null;

		foreach ( $models as $model ) {
			$result = $this->request( $model, $messages, $tools );

			if ( ! is_wp_error( $result ) ) {
				$this->last_model = $model;
				return $result;
			}

			$last_error = $result;

			if ( ! self::is_quota_error( $result ) ) {
				return $result;
			}
		}

		return $last_error ?? new WP_Error( 'wcai_no_models', 'No models are configured.' );
	}

	private function request( string $model, array $messages, array $tools ): array|WP_Error {
		$key = (string) WCAI_Settings::get( 'api_key' );

		if ( '' === $key ) {
			return new WP_Error( 'wcai_no_key', 'No API key is configured.' );
		}

		$payload = [
			'model'    => $model,
			'messages' => $messages,
		];

		if ( ! empty( $tools ) ) {
			$payload['tools'] = $tools;
		}

		$response = wp_remote_post(
			$this->endpoint(),
			[
				'timeout' => self::TIMEOUT,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $key,
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: wp_remote_retrieve_response_message( $response );

			return new WP_Error(
				'wcai_api_error',
				sprintf( 'HTTP %d from %s: %s', $code, $model, $message ),
				[ 'status' => $code ]
			);
		}

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'wcai_bad_json', 'The API returned a response that could not be parsed.' );
		}

		return $body;
	}

	private function endpoint(): string {
		$base = trailingslashit( (string) WCAI_Settings::get( 'base_url' ) );
		return $base . 'chat/completions';
	}

	/**
	 * Mirrors _is_quota_error() in the Python twin.
	 */
	private static function is_quota_error( WP_Error $error ): bool {
		$status = (int) ( $error->get_error_data()['status'] ?? 0 );

		if ( 429 === $status ) {
			return true;
		}

		$message = strtolower( $error->get_error_message() );

		foreach ( [ 'quota', 'insufficient_quota', 'rate limit', 'too many requests', 'resource_exhausted' ] as $needle ) {
			if ( str_contains( $message, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
