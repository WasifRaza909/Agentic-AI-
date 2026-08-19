<?php
declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores captured leads and unanswered questions, and shows them in wp-admin.
 *
 * The Python twin only fired a Pushover notification and kept nothing. For a shop
 * that is not much use, so everything is written to a table you can search later;
 * notifications are a convenience layer on top.
 */
final class WCAI_Leads {

	private const DB_VERSION = '1';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wcai_leads';
	}

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		add_action( 'admin_init', [ self::class, 'maybe_upgrade' ] );
	}

	public static function install_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				type varchar(32) NOT NULL DEFAULT 'lead',
				name varchar(191) NOT NULL DEFAULT '',
				email varchar(191) NOT NULL DEFAULT '',
				message text NOT NULL,
				page_url varchar(255) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY type (type),
				KEY created_at (created_at)
			) {$collate};"
		);

		update_option( 'wcai_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'wcai_db_version' ) !== self::DB_VERSION ) {
			self::install_table();
		}
	}

	public static function record( string $type, string $message, string $email = '', string $name = '', string $page_url = '' ): void {
		global $wpdb;

		$wpdb->insert(
			self::table(),
			[
				'type'       => $type,
				'name'       => $name,
				'email'      => $email,
				'message'    => $message,
				'page_url'   => $page_url,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		self::notify( $type, $message, $email, $name, $page_url );
	}

	private static function notify( string $type, string $message, string $email, string $name, string $page_url ): void {
		$store   = get_bloginfo( 'name' );
		$subject = 'lead' === $type
			? sprintf( '[%s] New chat lead%s', $store, $email ? ': ' . $email : '' )
			: sprintf( '[%s] Assistant could not answer a question', $store );

		$body = trim(
			implode(
				"\n",
				array_filter(
					[
						$name ? "Name: {$name}" : '',
						$email ? "Email: {$email}" : '',
						"Details: {$message}",
						$page_url ? "Page: {$page_url}" : '',
						'Time: ' . current_time( 'mysql' ),
					]
				)
			)
		);

		$to = (string) WCAI_Settings::get( 'notify_email' );
		if ( is_email( $to ) ) {
			wp_mail( $to, $subject, $body );
		}

		if ( ! WCAI_Settings::get( 'pushover_enabled' ) ) {
			return;
		}

		$user  = (string) WCAI_Settings::get( 'pushover_user' );
		$token = (string) WCAI_Settings::get( 'pushover_token' );

		if ( '' === $user || '' === $token ) {
			return;
		}

		wp_remote_post(
			'https://api.pushover.net/1/messages.json',
			[
				'timeout' => 10,
				'body'    => [
					'token'   => $token,
					'user'    => $user,
					'title'   => $subject,
					'message' => $body,
				],
			]
		);
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			'AI Chat Leads',
			'AI Chat Leads',
			'manage_woocommerce',
			'wc-ai-leads',
			[ self::class, 'render' ]
		);
	}

	public static function render(): void {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
		?>
		<div class="wrap">
			<h1>AI Chat Leads</h1>
			<p>Contact details and unanswered questions captured by the chat assistant. Showing the 200 most recent.</p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:150px">When</th>
						<th style="width:110px">Type</th>
						<th style="width:180px">Name</th>
						<th style="width:220px">Email</th>
						<th>Details</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5">Nothing captured yet.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><?php echo 'lead' === $row->type ? '💬 Lead' : '❓ Unanswered'; ?></td>
							<td><?php echo esc_html( $row->name ); ?></td>
							<td>
								<?php if ( $row->email ) : ?>
									<a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $row->message ); ?>
								<?php if ( $row->page_url ) : ?>
									<br><a href="<?php echo esc_url( $row->page_url ); ?>" target="_blank" rel="noopener"><small><?php echo esc_html( $row->page_url ); ?></small></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
