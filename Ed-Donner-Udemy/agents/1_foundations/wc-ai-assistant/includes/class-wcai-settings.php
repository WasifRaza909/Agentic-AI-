<?php
declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen, option accessors, and the connection tester.
 */
final class WCAI_Settings {

	public const OPTION = 'wcai_settings';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		add_action( 'admin_init', [ self::class, 'register' ] );
		add_action( 'wp_ajax_wcai_test_connection', [ self::class, 'ajax_test_connection' ] );
	}

	public static function defaults(): array {
		return [
			'enabled'          => 1,
			'api_key'          => '',
			'base_url'         => 'https://generativelanguage.googleapis.com/v1beta/openai/',
			'models'           => "gemini-3.1-flash-lite\ngemini-2.0-flash",
			'bot_name'         => 'Destiny Assistant',
			'greeting'         => 'Hi! 👋 Ask me anything about our hair extensions, prices, or delivery.',
			'status_text'      => 'Online now',
			'placeholder'      => 'Ask about products, sizes, delivery…',
			'footnote'         => 'AI assistant — replies may not always be exact.',
			'error_text'       => 'I could not reach the store just now. Please check your connection and try again.',
			'accent'           => '#5A2612',
			'launcher_style'   => 'icon',
			'launcher_icon'    => WCAI_Icons::default_key(),
			'launcher_image'   => '',
			'launcher_text'    => 'Ask AI',
			'launcher_side'    => 'right',
			'launcher_offset'  => 24,
			'persona'          => '',
			'rules'            => '',
			'policies'         => '',
			'notify_email'     => get_option( 'admin_email' ),
			'pushover_enabled' => 0,
			'pushover_user'    => '',
			'pushover_token'   => '',
			'rate_limit'       => 25,
		];
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, [] );
		return wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Model candidates, in preference order. Mirrors MODEL_CANDIDATES in the Python twin.
	 *
	 * @return string[]
	 */
	public static function models(): array {
		$raw    = (string) self::get( 'models' );
		$models = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $raw ) ?: [] ) ) );

		return $models ?: [ 'gemini-2.0-flash' ];
	}

	public static function add_menu(): void {
		$hook = add_submenu_page(
			'woocommerce',
			'AI Assistant',
			'AI Assistant',
			'manage_woocommerce',
			'wc-ai-assistant',
			[ self::class, 'render' ]
		);

		// The launcher picker offers a custom image, which needs the media modal.
		if ( is_string( $hook ) ) {
			add_action(
				'admin_enqueue_scripts',
				static function ( string $screen ) use ( $hook ): void {
					if ( $screen === $hook ) {
						wp_enqueue_media();
					}
				}
			);
		}
	}

	public static function register(): void {
		register_setting(
			'wcai_settings_group',
			self::OPTION,
			[ 'sanitize_callback' => [ self::class, 'sanitize' ] ]
		);
	}

	/**
	 * How the launcher is put together: glyph, wording, or both.
	 *
	 * @return array<string, array{label: string, note: string}>
	 */
	public static function launcher_styles(): array {
		return [
			'icon' => [
				'label' => 'Icon only',
				'note'  => 'A round button. Smallest footprint — best when another floating button already sits nearby.',
			],
			'both' => [
				'label' => 'Icon and text',
				'note'  => 'A pill. Clearest of the three: shoppers can tell it apart from your WhatsApp button without hovering.',
			],
			'text' => [
				'label' => 'Text only',
				'note'  => 'A pill with wording alone. Quietest option, and leans on the copy to do the work.',
			],
		];
	}

	public static function sanitize( mixed $input ): array {
		$input   = is_array( $input ) ? $input : [];
		$current = self::all();

		$key = trim( (string) ( $input['api_key'] ?? '' ) );

		$style = (string) ( $input['launcher_style'] ?? 'icon' );
		$style = isset( self::launcher_styles()[ $style ] ) ? $style : 'icon';

		$icon = (string) ( $input['launcher_icon'] ?? '' );
		$icon = WCAI_Icons::exists( $icon ) ? $icon : WCAI_Icons::default_key();

		$text = sanitize_text_field( (string) ( $input['launcher_text'] ?? '' ) );

		// Text-only and pill modes are meaningless with no wording, so fall back
		// rather than shipping an empty button to the storefront.
		if ( 'icon' !== $style && '' === trim( $text ) ) {
			$text = 'Ask AI';
		}

		return [
			'enabled'          => empty( $input['enabled'] ) ? 0 : 1,
			// An empty field means "leave the stored key alone" rather than "wipe it",
			// because the field renders masked.
			'api_key'          => '' === $key ? $current['api_key'] : $key,
			'base_url'         => esc_url_raw( trim( (string) ( $input['base_url'] ?? '' ) ) ),
			'models'           => sanitize_textarea_field( (string) ( $input['models'] ?? '' ) ),
			'bot_name'         => sanitize_text_field( (string) ( $input['bot_name'] ?? '' ) ),
			'greeting'         => sanitize_text_field( (string) ( $input['greeting'] ?? '' ) ),
			'status_text'      => sanitize_text_field( (string) ( $input['status_text'] ?? '' ) ),
			'placeholder'      => sanitize_text_field( (string) ( $input['placeholder'] ?? '' ) ),
			'footnote'         => sanitize_text_field( (string) ( $input['footnote'] ?? '' ) ),
			'error_text'       => sanitize_text_field( (string) ( $input['error_text'] ?? '' ) ),
			'accent'           => sanitize_hex_color( (string) ( $input['accent'] ?? '' ) ) ?: '#5A2612',
			'launcher_style'   => $style,
			'launcher_icon'    => $icon,
			'launcher_image'   => esc_url_raw( trim( (string) ( $input['launcher_image'] ?? '' ) ) ),
			'launcher_text'    => $text,
			'launcher_side'    => 'left' === ( $input['launcher_side'] ?? '' ) ? 'left' : 'right',
			'launcher_offset'  => min( 400, absint( $input['launcher_offset'] ?? 24 ) ),
			'persona'          => sanitize_textarea_field( (string) ( $input['persona'] ?? '' ) ),
			'rules'            => sanitize_textarea_field( (string) ( $input['rules'] ?? '' ) ),
			'policies'         => sanitize_textarea_field( (string) ( $input['policies'] ?? '' ) ),
			'notify_email'     => sanitize_email( (string) ( $input['notify_email'] ?? '' ) ),
			'pushover_enabled' => empty( $input['pushover_enabled'] ) ? 0 : 1,
			'pushover_user'    => sanitize_text_field( (string) ( $input['pushover_user'] ?? '' ) ),
			'pushover_token'   => sanitize_text_field( (string) ( $input['pushover_token'] ?? '' ) ),
			'rate_limit'       => max( 1, absint( $input['rate_limit'] ?? 25 ) ),
		];
	}

	/**
	 * The launcher builder: shape, glyph, wording and placement, with a live
	 * preview so the choice is made by looking rather than by saving and
	 * hopping to the storefront.
	 *
	 * @param array<string, mixed> $o Saved settings.
	 * @param string               $n Option name, used as the field name prefix.
	 */
	private static function render_launcher_section( array $o, string $n ): void {
		$icons  = WCAI_Icons::all();
		$styles = self::launcher_styles();
		?>
		<h2 class="title">Launcher button</h2>
		<p style="max-width:760px">
			The floating button shoppers tap to open the assistant. If your store already has a
			WhatsApp button, give this one a different shape or a label so the two are never
			mistaken for each other.
		</p>

		<style>
			.wcai-adm { display:flex; flex-wrap:wrap; gap:28px; align-items:flex-start; margin:16px 0 8px; }
			.wcai-adm-main { flex:1 1 460px; min-width:min(100%,460px); }
			.wcai-adm-field { margin-bottom:26px; }
			.wcai-adm-field > h3 { margin:0 0 4px; font-size:13px; text-transform:uppercase; letter-spacing:.04em; color:#50575e; }
			.wcai-adm-field > .description { margin:0 0 10px; }
			.wcai-adm-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:10px; }
			.wcai-adm-card { position:relative; display:block; padding:12px 14px; border:1px solid #dcdcde; border-radius:8px;
				background:#fff; cursor:pointer; transition:border-color .15s, box-shadow .15s, background-color .15s; }
			.wcai-adm-card:hover { border-color:#8c8f94; }
			.wcai-adm-card input { position:absolute; opacity:0; pointer-events:none; }
			.wcai-adm-card b { display:block; font-size:13px; }
			.wcai-adm-cards .wcai-adm-card span { display:block; margin-top:3px; font-size:12px; line-height:1.5; color:#646970; }
			.wcai-adm-card.is-on { border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; background:#f0f6fc; }
			.wcai-adm-glyphs { display:grid; grid-template-columns:repeat(auto-fill,minmax(104px,1fr)); gap:10px; max-width:660px; }
			.wcai-adm-glyph { display:flex; flex-direction:column; align-items:center; gap:8px; padding:14px 8px 10px; text-align:center; }
			.wcai-adm-glyph .wcai-adm-chip { display:grid; place-items:center; width:44px; height:44px; border-radius:50%; color:#fff; }
			.wcai-adm-glyph em { font-style:normal; font-size:11px; line-height:1.4; color:#50575e; }
			.wcai-adm-glyph img { max-width:24px; max-height:24px; }
			.wcai-adm-note { margin:8px 0 0; min-height:1.4em; font-size:12px; color:#646970; }
			.wcai-adm-side { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
			.wcai-adm-side label.wcai-adm-card { flex:0 0 auto; padding:8px 14px; }
			.wcai-adm-preview { flex:0 0 320px; position:sticky; top:46px; }
			.wcai-adm-stage { position:relative; height:230px; border:1px solid #dcdcde; border-radius:10px; overflow:hidden;
				background:linear-gradient(#fff,#f6f7f7); }
			.wcai-adm-stage::before { content:''; position:absolute; inset:0 0 auto; height:26px; background:#f0f0f1; border-bottom:1px solid #e3e3e4; }
			.wcai-adm-stage-lines { position:absolute; left:18px; right:18px; top:52px; }
			.wcai-adm-stage-lines i { display:block; height:8px; border-radius:4px; background:#e8e8ea; margin-bottom:9px; }
			.wcai-adm-stage-lines i:nth-child(2) { width:80%; } .wcai-adm-stage-lines i:nth-child(3) { width:55%; }
			.wcai-adm-btn { position:absolute; bottom:18px; display:inline-flex; align-items:center; gap:9px; height:52px;
				padding:0; width:52px; justify-content:center; border-radius:999px; color:#fff; font:600 14px/1 -apple-system,"Segoe UI",sans-serif;
				box-shadow:0 2px 6px rgba(42,20,9,.18),0 8px 20px rgba(90,38,18,.28); }
			.wcai-adm-btn.has-text { width:auto; padding:0 20px; }
			.wcai-adm-btn img, .wcai-adm-btn svg, .wcai-adm-chip svg { display:block; }
			.wcai-adm-btn img { max-width:24px; max-height:24px; }
			.wcai-adm-wa { position:absolute; bottom:18px; display:grid; place-items:center; width:52px; height:52px;
				border-radius:50%; background:#25d366; color:#fff; opacity:.9; }
			.wcai-adm-legend { margin:8px 0 0; font-size:12px; color:#646970; text-align:center; }
		</style>

		<div class="wcai-adm">
			<div class="wcai-adm-main">

				<div class="wcai-adm-field">
					<h3>What the button shows</h3>
					<div class="wcai-adm-cards" id="wcai-style-cards">
						<?php foreach ( $styles as $key => $style ) : ?>
							<label class="wcai-adm-card<?php echo $o['launcher_style'] === $key ? ' is-on' : ''; ?>">
								<input type="radio" name="<?php echo esc_attr( $n ); ?>[launcher_style]" value="<?php echo esc_attr( $key ); ?>"
									<?php checked( $o['launcher_style'], $key ); ?>>
								<b><?php echo esc_html( $style['label'] ); ?></b>
								<span><?php echo esc_html( $style['note'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wcai-adm-field" id="wcai-text-field">
					<h3><label for="wcai-launcher-text">Button text</label></h3>
					<input type="text" id="wcai-launcher-text" class="regular-text" maxlength="28"
						name="<?php echo esc_attr( $n ); ?>[launcher_text]" value="<?php echo esc_attr( $o['launcher_text'] ); ?>">
					<p class="description">Two or three words work best — “Ask AI”, “Need help?”, “Chat with us”.</p>
				</div>

				<div class="wcai-adm-field" id="wcai-icon-field">
					<h3>Icon</h3>
					<div class="wcai-adm-glyphs" id="wcai-icon-cards">
						<?php foreach ( $icons as $key => $icon ) : ?>
							<label class="wcai-adm-card wcai-adm-glyph<?php echo $o['launcher_icon'] === $key ? ' is-on' : ''; ?>"
								data-note="<?php echo esc_attr( $icon['note'] ); ?>">
								<input type="radio" name="<?php echo esc_attr( $n ); ?>[launcher_icon]" value="<?php echo esc_attr( $key ); ?>"
									<?php checked( $o['launcher_icon'], $key ); ?>>
								<span class="wcai-adm-chip" style="background:<?php echo esc_attr( $o['accent'] ); ?>">
									<?php echo WCAI_Icons::svg( $key, 24 ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed plugin markup. ?>
								</span>
								<em><?php echo esc_html( $icon['label'] ); ?></em>
							</label>
						<?php endforeach; ?>
						<label class="wcai-adm-card wcai-adm-glyph<?php echo '' !== $o['launcher_image'] ? ' is-on' : ''; ?>"
							id="wcai-icon-custom" data-note="Your own image. Use a square PNG or SVG, at least 96&times;96, with a transparent background.">
							<input type="radio" name="wcai_launcher_icon_mode" value="custom" <?php checked( '' !== $o['launcher_image'] ); ?>>
							<span class="wcai-adm-chip" style="background:<?php echo esc_attr( $o['accent'] ); ?>">
								<?php if ( '' !== $o['launcher_image'] ) : ?>
									<img src="<?php echo esc_url( $o['launcher_image'] ); ?>" alt="">
								<?php else : ?>
									<span style="font-size:18px;line-height:1">+</span>
								<?php endif; ?>
							</span>
							<em>Custom image</em>
						</label>
					</div>
					<p class="wcai-adm-note" id="wcai-icon-note"></p>
					<p style="margin-top:10px">
						<input type="hidden" id="wcai-launcher-image" name="<?php echo esc_attr( $n ); ?>[launcher_image]"
							value="<?php echo esc_attr( $o['launcher_image'] ); ?>">
						<button type="button" class="button button-secondary" id="wcai-pick-image">Choose image…</button>
						<button type="button" class="button-link" id="wcai-clear-image"
							style="margin-left:10px;color:#b32d2e;<?php echo '' === $o['launcher_image'] ? 'display:none' : ''; ?>">Remove image</button>
					</p>
				</div>

				<div class="wcai-adm-field">
					<h3>Placement</h3>
					<div class="wcai-adm-side">
						<?php foreach ( [ 'right' => 'Bottom right', 'left' => 'Bottom left' ] as $side => $label ) : ?>
							<label class="wcai-adm-card<?php echo $o['launcher_side'] === $side ? ' is-on' : ''; ?>">
								<input type="radio" name="<?php echo esc_attr( $n ); ?>[launcher_side]" value="<?php echo esc_attr( $side ); ?>"
									<?php checked( $o['launcher_side'], $side ); ?>>
								<b><?php echo esc_html( $label ); ?></b>
							</label>
						<?php endforeach; ?>
						<label style="margin-left:6px">
							Lift off the bottom
							<input type="number" id="wcai-launcher-offset" min="0" max="400" step="4" style="width:80px;margin-left:6px"
								name="<?php echo esc_attr( $n ); ?>[launcher_offset]" value="<?php echo esc_attr( (string) $o['launcher_offset'] ); ?>"> px
						</label>
					</div>
					<p class="description" style="margin-top:8px">
						Raise it above an existing WhatsApp or back-to-top button — 96px clears a typical one.
					</p>
				</div>
			</div>

			<div class="wcai-adm-preview">
				<div class="wcai-adm-stage" id="wcai-stage">
					<div class="wcai-adm-stage-lines"><i></i><i></i><i></i></div>
					<span class="wcai-adm-wa" id="wcai-stage-wa" title="Stand-in for your WhatsApp button">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.6 14.1c-.2.7-1.2 1.3-1.9 1.4-.5.1-1.1.1-1.8-.1a15 15 0 0 1-6.4-5.6c-.5-.7-.8-1.6-.8-2.4 0-.9.5-1.4.7-1.6.2-.2.5-.3.6-.3h.5c.2 0 .4 0 .5.4l.8 1.9c.1.2 0 .4-.1.5l-.4.5c-.1.1-.3.3-.1.6.2.3.7 1.1 1.4 1.8.9.8 1.6 1 1.9 1.2.2.1.4.1.5-.1l.7-.8c.2-.2.3-.2.6-.1l1.8.9c.3.1.4.2.5.3v1z"/></svg>
					</span>
					<span class="wcai-adm-btn" id="wcai-stage-btn"></span>
				</div>
				<p class="wcai-adm-legend">Live preview — the green button stands in for your WhatsApp one.</p>
				<p class="wcai-adm-legend" id="wcai-stage-warn" style="display:none;color:#b26200">
					These two would overlap. Lift the assistant to 96px, or move it to the other side.
				</p>
			</div>
		</div>

		<script>
		(function () {
			var ICONS = <?php echo wp_json_encode( array_values( array_map( static fn( string $k ): string => WCAI_Icons::svg( $k, 24 ), array_keys( $icons ) ) ) ); ?>;
			var KEYS  = <?php echo wp_json_encode( array_keys( $icons ) ); ?>;
			var map   = {};
			KEYS.forEach(function (k, i) { map[k] = ICONS[i]; });

			var btn      = document.getElementById('wcai-stage-btn');
			var wa       = document.getElementById('wcai-stage-wa');
			var textIn   = document.getElementById('wcai-launcher-text');
			var imageIn  = document.getElementById('wcai-launcher-image');
			var offsetIn = document.getElementById('wcai-launcher-offset');
			var accentIn = document.getElementById('wcai-accent');
			var note     = document.getElementById('wcai-icon-note');
			var custom   = document.getElementById('wcai-icon-custom');
			var clearBtn = document.getElementById('wcai-clear-image');
			var warn     = document.getElementById('wcai-stage-warn');

			function checked(name) {
				var el = document.querySelector('input[name="' + name + '"]:checked');
				return el ? el.value : '';
			}

			function markCards() {
				document.querySelectorAll('.wcai-adm-card').forEach(function (card) {
					var input = card.querySelector('input');
					card.classList.toggle('is-on', !!input && input.checked);
				});
			}

			function render() {
				var style  = checked('<?php echo esc_js( $n ); ?>[launcher_style]') || 'icon';
				var side   = checked('<?php echo esc_js( $n ); ?>[launcher_side]') || 'right';
				var usesCustom = document.querySelector('#wcai-icon-custom input').checked && imageIn.value;
				var iconKey = checked('<?php echo esc_js( $n ); ?>[launcher_icon]') || KEYS[0];
				var accent = accentIn ? accentIn.value : '#5A2612';
				var text   = (textIn.value || 'Ask AI').trim();
				var offset = Math.max(0, parseInt(offsetIn.value, 10) || 0);

				document.getElementById('wcai-icon-field').style.display = 'text' === style ? 'none' : '';
				document.getElementById('wcai-text-field').style.display = 'icon' === style ? 'none' : '';

				var glyph = usesCustom
					? '<img src="' + imageIn.value.replace(/"/g, '&quot;') + '" alt="">'
					: (map[iconKey] || '');

				btn.innerHTML = ('text' === style ? '' : glyph) +
					('icon' === style ? '' : '<span>' + text.replace(/</g, '&lt;') + '</span>');
				btn.classList.toggle('has-text', 'icon' !== style);
				btn.style.background = accent;

				// The stage is 230px tall against a ~700px viewport, so scale the
				// offset down rather than letting a 200px lift shoot off the top.
				var scaled = Math.min(120, Math.round(offset * 0.32));
				btn.style.bottom = (18 + scaled) + 'px';
				btn.style.left = btn.style.right = '';
				btn.style[side] = '18px';
				wa.style.left = wa.style.right = '';
				wa.style[side] = '18px';

				// 70px is the stand-in's height plus a hair of breathing room, so
				// anything under it means the two buttons would be sitting on top
				// of each other on a real storefront.
				warn.style.display = offset < 70 ? '' : 'none';

				document.querySelectorAll('.wcai-adm-chip').forEach(function (chip) { chip.style.background = accent; });

				var on = document.querySelector('.wcai-adm-glyph.is-on');
				note.textContent = on ? on.getAttribute('data-note') : '';
			}

			// A built-in glyph and a custom upload are the same choice, so picking
			// one has to drop the other — otherwise a stale image URL stays saved
			// and quietly wins on the storefront.
			document.querySelectorAll('input[name="<?php echo esc_js( $n ); ?>[launcher_icon]"]').forEach(function (radio) {
				radio.addEventListener('change', function () {
					if (radio.checked) { clearCustomImage(); }
				});
			});

			// The custom tile sits in its own radio group so it can be cleared,
			// which means unchecking the built-ins is on us.
			custom.querySelector('input').addEventListener('change', function () {
				document.querySelectorAll('input[name="<?php echo esc_js( $n ); ?>[launcher_icon]"]').forEach(function (radio) {
					radio.checked = false;
				});
				if (!imageIn.value) { document.getElementById('wcai-pick-image').click(); }
			});

			document.querySelector('.wcai-adm').addEventListener('change', function () { markCards(); render(); });
			document.querySelector('.wcai-adm').addEventListener('input', render);
			if (accentIn) { accentIn.addEventListener('input', render); }

			document.getElementById('wcai-pick-image').addEventListener('click', function () {
				var frame = wp.media({ title: 'Choose a launcher icon', button: { text: 'Use this image' }, multiple: false });
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					imageIn.value = att.url;
					custom.querySelector('.wcai-adm-chip').innerHTML = '<img src="' + att.url.replace(/"/g, '&quot;') + '" alt="">';
					custom.querySelector('input').checked = true;
					clearBtn.style.display = '';
					markCards();
					render();
				});
				frame.open();
			});

			function clearCustomImage() {
				imageIn.value = '';
				custom.querySelector('.wcai-adm-chip').innerHTML = '<span style="font-size:18px;line-height:1">+</span>';
				custom.querySelector('input').checked = false;
				clearBtn.style.display = 'none';
			}

			clearBtn.addEventListener('click', function () {
				clearCustomImage();
				var first = document.querySelector('input[name="<?php echo esc_js( $n ); ?>[launcher_icon]"]:checked')
					|| document.querySelector('input[name="<?php echo esc_js( $n ); ?>[launcher_icon]"]');
				if (first) { first.checked = true; }
				markCards();
				render();
			});

			markCards();
			render();
		})();
		</script>
		<?php
	}

	public static function render(): void {
		$o = self::all();
		?>
		<div class="wrap">
			<h1>AI Assistant</h1>

			<h2 class="title">Connection test</h2>
			<p>Confirms this server can reach the Gemini API and that your key works. Save your key first.</p>
			<p>
				<button type="button" class="button button-secondary" id="wcai-test">Test connection</button>
				<span id="wcai-test-result" style="margin-left:10px;"></span>
			</p>
			<script>
			document.getElementById('wcai-test').addEventListener('click', function () {
				var out = document.getElementById('wcai-test-result');
				var btn = this;
				btn.disabled = true;
				out.textContent = 'Testing…';
				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=wcai_test_connection&_wpnonce=<?php echo esc_js( wp_create_nonce( 'wcai_test' ) ); ?>'
				})
					.then(function (r) { return r.json(); })
					.then(function (r) {
						out.innerHTML = r.success
							? '<span style="color:#1a7f37">✔ ' + r.data + '</span>'
							: '<span style="color:#b32d2e">✘ ' + r.data + '</span>';
					})
					.catch(function (e) { out.textContent = 'Request failed: ' + e.message; })
					.finally(function () { btn.disabled = false; });
			});
			</script>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcai_settings_group' ); ?>
				<?php $n = self::OPTION; ?>

				<h2 class="title">General</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Enable chat bubble</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $o['enabled'], 1 ); ?>>
								Show the assistant on the storefront
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-key">Gemini API key</label></th>
						<td>
							<input type="password" id="wcai-key" class="regular-text" name="<?php echo esc_attr( $n ); ?>[api_key]"
								value="" autocomplete="new-password"
								placeholder="<?php echo $o['api_key'] ? 'Saved — leave blank to keep' : 'Paste your key'; ?>">
							<p class="description">Stored in the database and never sent to the browser.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-base">API base URL</label></th>
						<td><input type="url" id="wcai-base" class="regular-text" name="<?php echo esc_attr( $n ); ?>[base_url]" value="<?php echo esc_attr( $o['base_url'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-models">Models</label></th>
						<td>
							<textarea id="wcai-models" class="large-text code" rows="3" name="<?php echo esc_attr( $n ); ?>[models]"><?php echo esc_textarea( $o['models'] ); ?></textarea>
							<p class="description">One per line, in preference order. If one hits its quota, the next is tried automatically.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Appearance</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wcai-name">Assistant name</label></th>
						<td><input type="text" id="wcai-name" class="regular-text" name="<?php echo esc_attr( $n ); ?>[bot_name]" value="<?php echo esc_attr( $o['bot_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-greeting">Greeting</label></th>
						<td>
							<input type="text" id="wcai-greeting" class="large-text" name="<?php echo esc_attr( $n ); ?>[greeting]" value="<?php echo esc_attr( $o['greeting'] ); ?>">
							<p class="description">The assistant's opening message, shown before the shopper types anything.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-status">Status line</label></th>
						<td>
							<input type="text" id="wcai-status" class="regular-text" name="<?php echo esc_attr( $n ); ?>[status_text]" value="<?php echo esc_attr( $o['status_text'] ); ?>">
							<p class="description">Sits under the name with the green dot. Leave blank to hide both.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-placeholder">Input placeholder</label></th>
						<td>
							<input type="text" id="wcai-placeholder" class="large-text" name="<?php echo esc_attr( $n ); ?>[placeholder]" value="<?php echo esc_attr( $o['placeholder'] ); ?>">
							<p class="description">Grey hint text in the message box.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-footnote">Footnote</label></th>
						<td>
							<input type="text" id="wcai-footnote" class="large-text" name="<?php echo esc_attr( $n ); ?>[footnote]" value="<?php echo esc_attr( $o['footnote'] ); ?>">
							<p class="description">
								Small print under the message box. Leave blank to hide it —
								though telling shoppers they're talking to an AI is good practice.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-error">Connection error message</label></th>
						<td>
							<input type="text" id="wcai-error" class="large-text" name="<?php echo esc_attr( $n ); ?>[error_text]" value="<?php echo esc_attr( $o['error_text'] ); ?>">
							<p class="description">Shown if the shopper's browser can't reach your site.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcai-accent">Brand colour</label></th>
						<td>
							<input type="color" id="wcai-accent" name="<?php echo esc_attr( $n ); ?>[accent]" value="<?php echo esc_attr( $o['accent'] ); ?>" style="width:56px;height:34px;padding:2px;vertical-align:middle">
							<code style="margin-left:8px"><?php echo esc_html( strtoupper( $o['accent'] ) ); ?></code>
							<p class="description">Used for the launcher, header, customer messages and links. Defaults to #5A2612.</p>
						</td>
					</tr>
				</table>

				<?php self::render_launcher_section( $o, $n ); ?>

				<?php
				$persona_value = '' !== trim( $o['persona'] ) ? $o['persona'] : WCAI_Context::default_persona();
				$rules_value   = '' !== trim( $o['rules'] ) ? $o['rules'] : WCAI_Context::default_rules();
				?>

				<h2 class="title">What the assistant is told</h2>
				<p>
					These three boxes are everything the AI knows, apart from your products —
					those are read from WooCommerce automatically and never need to be typed here.
					You can use the placeholders
					<code>{store}</code>, <code>{tagline}</code>, <code>{assistant}</code> and
					<code>{currency}</code> in any of them.
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wcai-persona">Role</label>
							<p class="description" style="font-weight:400">Who the assistant is and what it is for.</p>
						</th>
						<td>
							<textarea id="wcai-persona" class="large-text code" rows="9" name="<?php echo esc_attr( $n ); ?>[persona]"><?php echo esc_textarea( $persona_value ); ?></textarea>
							<p>
								<button type="button" class="button button-small wcai-reset-field" data-target="wcai-persona">Reset to default</button>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wcai-policies">Shipping &amp; returns</label>
							<p class="description" style="font-weight:400">Facts that aren't in your product data.</p>
						</th>
						<td>
							<textarea id="wcai-policies" class="large-text" rows="8" name="<?php echo esc_attr( $n ); ?>[policies]" placeholder="Delivery: 2–4 working days nationwide, Rs. 200 flat, free over Rs. 3,000.&#10;Cash on delivery available.&#10;Exchanges within 7 days if unused and in original packaging.&#10;Care: wash with mild shampoo in cold water, air dry."><?php echo esc_textarea( $o['policies'] ); ?></textarea>
							<p class="description">
								Delivery times, charges, COD, exchange policy, care instructions.
								Write facts, not instructions. <strong>Never repeat prices here</strong> —
								they come from WooCommerce, and a second copy will go stale.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wcai-rules">Behaviour rules</label>
							<p class="description" style="font-weight:400">Tone, length, what it may discuss.</p>
						</th>
						<td>
							<textarea id="wcai-rules" class="large-text code" rows="16" name="<?php echo esc_attr( $n ); ?>[rules]"><?php echo esc_textarea( $rules_value ); ?></textarea>
							<p>
								<button type="button" class="button button-small wcai-reset-field" data-target="wcai-rules">Reset to default</button>
							</p>
							<p class="description">
								Edit freely — but the rules about using the <code>record_user_details</code> and
								<code>record_unknown_question</code> tools are what fill your Leads screen, so
								removing them switches lead capture off.
							</p>
							<p class="description" style="margin-top:8px">
								<strong>Always applied, and not editable:</strong><br>
								<em>“Only state prices, stock levels, sizes and product details that appear in the
								catalogue above. If something is not listed there, say you are not sure and offer to
								check — never invent it.”</em><br>
								This is the one guardrail that stops the assistant quoting a made-up price to a
								paying customer.
							</p>
						</td>
					</tr>
				</table>

				<details style="margin:16px 0;max-width:900px">
					<summary style="cursor:pointer;font-weight:600;padding:8px 0">
						Preview the exact prompt sent to the AI
					</summary>
					<p class="description">Reflects what is currently <strong>saved</strong>, including your live product catalogue. Save changes first to see them here.</p>
					<pre style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:14px;max-height:460px;overflow:auto;white-space:pre-wrap;font-size:12px;line-height:1.6"><?php echo esc_html( WCAI_Context::preview() ); ?></pre>
				</details>

				<script>
				(function () {
					var defaults = {
						'wcai-persona': <?php echo wp_json_encode( WCAI_Context::default_persona() ); ?>,
						'wcai-rules': <?php echo wp_json_encode( WCAI_Context::default_rules() ); ?>
					};
					document.querySelectorAll('.wcai-reset-field').forEach(function (btn) {
						btn.addEventListener('click', function () {
							var id = btn.getAttribute('data-target');
							if (window.confirm('Replace this box with the default text? Your current text will be lost.')) {
								document.getElementById(id).value = defaults[id];
							}
						});
					});
				})();
				</script>

				<h2 class="title">Notifications</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wcai-notify">Notify email</label></th>
						<td>
							<input type="email" id="wcai-notify" class="regular-text" name="<?php echo esc_attr( $n ); ?>[notify_email]" value="<?php echo esc_attr( $o['notify_email'] ); ?>">
							<p class="description">Emailed when a customer leaves contact details or asks something the bot can't answer. Blank disables email.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Pushover</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $n ); ?>[pushover_enabled]" value="1" <?php checked( $o['pushover_enabled'], 1 ); ?>>
								Also send push notifications
							</label>
							<p style="margin-top:8px">
								<input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[pushover_user]" value="<?php echo esc_attr( $o['pushover_user'] ); ?>" placeholder="Pushover user key"><br>
								<input type="text" class="regular-text" style="margin-top:4px" name="<?php echo esc_attr( $n ); ?>[pushover_token]" value="<?php echo esc_attr( $o['pushover_token'] ); ?>" placeholder="Pushover app token">
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Abuse protection</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wcai-rate">Messages per hour</label></th>
						<td>
							<input type="number" id="wcai-rate" min="1" name="<?php echo esc_attr( $n ); ?>[rate_limit]" value="<?php echo esc_attr( (string) $o['rate_limit'] ); ?>">
							<p class="description">Per visitor IP. Protects your API quota from scripted abuse.</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function ajax_test_connection(): void {
		check_ajax_referer( 'wcai_test' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		if ( '' === (string) self::get( 'api_key' ) ) {
			wp_send_json_error( 'No API key saved yet.' );
		}

		$client = new WCAI_Gemini_Client();
		$result = $client->complete(
			[
				[ 'role' => 'user', 'content' => 'Reply with the single word: ok' ],
			],
			[]
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( esc_html( $result->get_error_message() ) );
		}

		$reply = trim( (string) ( $result['choices'][0]['message']['content'] ?? '' ) );

		wp_send_json_success(
			sprintf(
				'Connected. Model "%s" replied: %s',
				esc_html( $client->last_model() ),
				esc_html( $reply !== '' ? $reply : '(empty)' )
			)
		);
	}
}
