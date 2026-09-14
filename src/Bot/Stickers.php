<?php
/**
 * The stickers a bot is allowed to send.
 *
 * LINE only accepts stickers from the packs it documents; anything else comes
 * back rejected, and the error does not say why. So the packs are listed here
 * rather than left to whoever is filling in the form, and the three below are
 * the classic forty-sticker packs from that page, whose ids run consecutively.
 *
 * https://developers.line.biz/en/docs/messaging-api/sticker-list/
 *
 * The list is deliberately not exhaustive -- LINE documents fifteen packs, and
 * the rest have irregular id ranges that cannot be expressed as a span. Anyone
 * who wants one of those can still type the two numbers in by hand, which is
 * what the whole product did before this existed.
 *
 * The preview images come from LINE's own sticker CDN, so nothing is stored
 * here. Note that the CDN serves every sticker, including ones a bot may not
 * send, so an image loading is not proof that LINE will accept it -- only the
 * documented list is.
 *
 * @package Mofoline
 */

namespace Mofoline\Bot;

defined( 'ABSPATH' ) || exit;

class Stickers {

	/**
	 * Packs offered in the picker.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function packs(): array {
		$packs = array(
			array(
				'package_id' => '446',
				'label'      => __( 'Brown & Cony', 'moksa-for-line' ),
				'from'       => 1988,
				'to'         => 2027,
			),
			array(
				'package_id' => '789',
				'label'      => __( 'Sally', 'moksa-for-line' ),
				'from'       => 10855,
				'to'         => 10894,
			),
			array(
				'package_id' => '1070',
				'label'      => __( 'Brown & Cony, second set', 'moksa-for-line' ),
				'from'       => 17839,
				'to'         => 17878,
			),
		);

		/**
		 * Filter the sticker packs offered in the picker.
		 *
		 * Anything added here must appear in LINE's sticker list, or the send
		 * will be refused with an error that does not explain itself.
		 *
		 * @param array $packs Packs, each with package_id, label, from and to.
		 */
		return (array) apply_filters( 'mofoline_sticker_packs', $packs );
	}

	/**
	 * Every offered sticker, flattened, for the picker.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function all(): array {
		$out = array();

		foreach ( self::packs() as $pack ) {
			for ( $id = (int) $pack['from']; $id <= (int) $pack['to']; $id++ ) {
				$out[] = array(
					'package_id' => (string) $pack['package_id'],
					'sticker_id' => (string) $id,
					'url'        => self::image_url( (string) $id ),
				);
			}
		}

		return $out;
	}

	/**
	 * Where LINE serves a sticker's picture.
	 *
	 * @param string $sticker_id Sticker id.
	 */
	public static function image_url( string $sticker_id ): string {
		return 'https://stickershop.line-scdn.net/stickershop/v1/sticker/'
			. rawurlencode( $sticker_id ) . '/android/sticker.png';
	}

	/**
	 * Whether a pair is one this plugin offers.
	 *
	 * Used to decide whether a stored value can be shown in the picker, not to
	 * refuse a send: someone may legitimately have typed in a pack from the
	 * part of LINE's list that is not modelled here.
	 *
	 * @param string $package_id Package id.
	 * @param string $sticker_id Sticker id.
	 */
	public static function known( string $package_id, string $sticker_id ): bool {
		$sticker = (int) $sticker_id;

		foreach ( self::packs() as $pack ) {
			if ( (string) $pack['package_id'] !== $package_id ) {
				continue;
			}

			return $sticker >= (int) $pack['from'] && $sticker <= (int) $pack['to'];
		}

		return false;
	}
}
