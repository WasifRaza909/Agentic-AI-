<?php
declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tool definitions and dispatch. Port of tools.py.
 */
final class WCAI_Tools {

	/**
	 * OpenAI-format tool schemas sent with every request.
	 */
	public static function definitions(): array {
		return [
			[
				'type'     => 'function',
				'function' => [
					'name'        => 'record_user_details',
					'description' => 'Record that a shopper wants to be contacted and has provided an email address.',
					'parameters'  => [
						'type'       => 'object',
						'properties' => [
							'email' => [
								'type'        => 'string',
								'description' => 'The shopper\'s email address',
							],
							'name'  => [
								'type'        => 'string',
								'description' => 'The shopper\'s name, if they gave it',
							],
							'notes' => [
								'type'        => 'string',
								'description' => 'What they were asking about, and anything else worth passing to the store owner',
							],
						],
						'required'   => [ 'email' ],
					],
				],
			],
			[
				'type'     => 'function',
				'function' => [
					'name'        => 'record_unknown_question',
					'description' => 'Always use this to record a question you could not answer, so the store owner can follow up.',
					'parameters'  => [
						'type'       => 'object',
						'properties' => [
							'question' => [
								'type'        => 'string',
								'description' => 'The question that could not be answered',
							],
						],
						'required'   => [ 'question' ],
					],
				],
			],
		];
	}

	/**
	 * Runs the tool calls from one assistant turn and returns tool-result messages.
	 *
	 * @param array  $tool_calls Raw tool_calls array from the API response.
	 * @param string $page_url   The page the shopper is chatting from, for context.
	 */
	public static function handle( array $tool_calls, string $page_url = '' ): array {
		$results = [];

		foreach ( $tool_calls as $call ) {
			$name = (string) ( $call['function']['name'] ?? '' );
			$args = json_decode( (string) ( $call['function']['arguments'] ?? '{}' ), true );
			$args = is_array( $args ) ? $args : [];

			$result = match ( $name ) {
				'record_user_details'     => self::record_user_details( $args, $page_url ),
				'record_unknown_question' => self::record_unknown_question( $args, $page_url ),
				default                   => 'Unknown tool: ' . $name,
			};

			$results[] = [
				'role'         => 'tool',
				'tool_call_id' => (string) ( $call['id'] ?? '' ),
				'content'      => wp_json_encode( $result ),
			];
		}

		return $results;
	}

	private static function record_user_details( array $args, string $page_url ): string {
		$email = sanitize_email( (string) ( $args['email'] ?? '' ) );

		if ( ! is_email( $email ) ) {
			return 'That email address did not look valid. Ask the shopper to confirm it.';
		}

		WCAI_Leads::record(
			'lead',
			sanitize_text_field( (string) ( $args['notes'] ?? 'No notes provided' ) ),
			$email,
			sanitize_text_field( (string) ( $args['name'] ?? '' ) ),
			$page_url
		);

		return 'OK, recorded. Confirm to the shopper that someone will be in touch.';
	}

	private static function record_unknown_question( array $args, string $page_url ): string {
		$question = sanitize_text_field( (string) ( $args['question'] ?? '' ) );

		if ( '' === $question ) {
			return 'No question was supplied.';
		}

		WCAI_Leads::record( 'unknown', $question, '', '', $page_url );

		return 'OK, recorded. Tell the shopper you do not know, and offer to take their email for a follow-up.';
	}
}
