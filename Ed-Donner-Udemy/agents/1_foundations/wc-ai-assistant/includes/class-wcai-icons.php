<?php
declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The launcher icon set.
 *
 * One source of truth: the admin picker draws the same markup the storefront
 * gets, so what the shop owner clicks is exactly what shoppers see. Every glyph
 * is drawn on a 24x24 grid with a 1.75 stroke inheriting currentColor.
 */
final class WCAI_Icons {

	/**
	 * @return array<string, array{label: string, note: string, paths: string}>
	 */
	public static function all(): array {
		return [
			'ai-bubble'    => [
				'label' => 'AI bubble',
				'note'  => 'Reads as AI at a glance and does not look like WhatsApp.',
				'paths' =>
					'<path d="M6.5 3.75h11a3.75 3.75 0 0 1 3.75 3.75v5a3.75 3.75 0 0 1-3.75 3.75h-5.35L8.4 19.4v-3.15H6.5A3.75 3.75 0 0 1 2.75 12.5v-5A3.75 3.75 0 0 1 6.5 3.75z"/>' .
					'<path class="wcai-spark wcai-spark--lg" fill="currentColor" stroke="none" d="M11.3 6.6c.38 2.02.98 2.62 3 3-2.02.38-2.62.98-3 3-.38-2.02-.98-2.62-3-3 2.02-.38 2.62-.98 3-3z"/>' .
					'<path class="wcai-spark wcai-spark--sm" fill="currentColor" stroke="none" d="M16.1 10.8c.22 1.18.57 1.53 1.75 1.75-1.18.22-1.53.57-1.75 1.75-.22-1.18-.57-1.53-1.75-1.75 1.18-.22 1.53-.57 1.75-1.75z"/>',
			],
			'round-bubble' => [
				'label' => 'Round bubble + spark',
				'note'  => 'Softer shape. Closer to a messaging app, so pair it with a label.',
				'paths' =>
					'<path d="M20.5 11.6a7.9 7.9 0 0 1-8.5 7.9 8.9 8.9 0 0 1-2.5-.4L4.5 20.5l1.4-4.2a7.7 7.7 0 0 1-1.4-4.5 7.9 7.9 0 0 1 8-7.9 7.9 7.9 0 0 1 8 7.7z"/>' .
					'<path class="wcai-spark wcai-spark--lg" fill="currentColor" stroke="none" d="M12.3 8.4c.41 2.15 1.05 2.79 3.2 3.2-2.15.41-2.79 1.05-3.2 3.2-.41-2.15-1.05-2.79-3.2-3.2 2.15-.41 2.79-1.05 3.2-3.2z"/>',
			],
			'sparkles'     => [
				'label' => 'Sparkles',
				'note'  => 'No bubble at all — the least confusable with a chat app.',
				'paths' =>
					'<path class="wcai-spark wcai-spark--lg" fill="currentColor" stroke="none" d="M10.8 5.3c.7 3.7 1.8 4.8 5.5 5.5-3.7.7-4.8 1.8-5.5 5.5-.7-3.7-1.8-4.8-5.5-5.5 3.7-.7 4.8-1.8 5.5-5.5z"/>' .
					'<path class="wcai-spark wcai-spark--sm" fill="currentColor" stroke="none" d="M18 15.3c.28 1.48.72 1.92 2.2 2.2-1.48.28-1.92.72-2.2 2.2-.28-1.48-.72-1.92-2.2-2.2 1.48-.28 1.92-.72 2.2-2.2z"/>',
			],
			'bot'          => [
				'label' => 'Robot',
				'note'  => 'Unmistakably a bot. Playful rather than premium.',
				'paths' =>
					'<path d="M12 3.4v2.1"/>' .
					'<circle cx="12" cy="2.3" r="1.1"/>' .
					'<path d="M7.25 5.5h9.5A3.25 3.25 0 0 1 20 8.75v6A3.25 3.25 0 0 1 16.75 18h-9.5A3.25 3.25 0 0 1 4 14.75v-6A3.25 3.25 0 0 1 7.25 5.5z"/>' .
					'<path d="M4 10.5H2.4v3H4M20 10.5h1.6v3H20"/>' .
					'<circle cx="9.3" cy="11" r="1.05" fill="currentColor" stroke="none"/>' .
					'<circle cx="14.7" cy="11" r="1.05" fill="currentColor" stroke="none"/>' .
					'<path d="M9.9 14.9h4.2"/>',
			],
			'bubble-dots'  => [
				'label' => 'Plain bubble',
				'note'  => 'The classic three dots. Says "chat", says nothing about AI.',
				'paths' =>
					'<path d="M20.5 11.6a7.9 7.9 0 0 1-8.5 7.9 8.9 8.9 0 0 1-2.5-.4L4.5 20.5l1.4-4.2a7.7 7.7 0 0 1-1.4-4.5 7.9 7.9 0 0 1 8-7.9 7.9 7.9 0 0 1 8 7.7z"/>' .
					'<circle cx="8.8" cy="11.8" r=".9" fill="currentColor" stroke="none"/>' .
					'<circle cx="12.3" cy="11.8" r=".9" fill="currentColor" stroke="none"/>' .
					'<circle cx="15.8" cy="11.8" r=".9" fill="currentColor" stroke="none"/>',
			],
		];
	}

	public static function default_key(): string {
		return 'ai-bubble';
	}

	public static function exists( string $key ): bool {
		return isset( self::all()[ $key ] );
	}

	/**
	 * Full <svg> markup for one icon, or an empty string for an unknown key.
	 */
	public static function svg( string $key, int $size = 24 ): string {
		$icon = self::all()[ $key ] ?? null;

		if ( null === $icon ) {
			return '';
		}

		return sprintf(
			'<svg class="wcai-i" viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="none" ' .
			'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" ' .
			'aria-hidden="true" focusable="false">%2$s</svg>',
			$size,
			$icon['paths']
		);
	}
}
