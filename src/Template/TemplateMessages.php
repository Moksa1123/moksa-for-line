<?php
/**
 * LINE template messages: buttons, confirm, carousel and image carousel.
 *
 * These are the fixed-layout counterpart to Flex. A shop that wants a card
 * with two buttons should not have to learn a layout language to get one, and
 * that is what these are for; Flex remains for anything the fixed layouts
 * cannot express.
 *
 * Every limit below was confirmed against LINE's own /message/validate/push
 * endpoint rather than taken from memory, including one that the reference
 * documentation does not spell out and which is the easiest way to build a
 * carousel that will not send:
 *
 *   "The use of image, title and the number of actions should be consistent
 *    for all columns."
 *
 * So a carousel's columns cannot disagree about whether they have an image,
 * whether they have a title, or how many buttons they carry. The editor models
 * that as one set of switches for the whole carousel rather than per column,
 * because the alternative is letting someone build something and only then
 * being told it is impossible.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Template;

defined( 'ABSPATH' ) || exit;

class TemplateMessages {

	/** Confirmed against LINE, not assumed. */
	const MAX_COLUMNS          = 10;
	const MAX_BUTTON_ACTIONS   = 4;
	const MAX_CAROUSEL_ACTIONS = 3;
	const CONFIRM_ACTIONS      = 2;

	/** Text limits shrink once a column carries an image or a title. */
	const BUTTONS_TEXT          = 160;
	const BUTTONS_TEXT_ADORNED  = 60;
	const CAROUSEL_TEXT         = 120;
	const CAROUSEL_TEXT_ADORNED = 60;
	const CONFIRM_TEXT          = 240;
	const TITLE                 = 40;
	const LABEL                 = 20;

	/**
	 * The kinds a shop can choose, with a plain description of each.
	 *
	 * @return array<string,string>
	 */
	public static function kinds(): array {
		return array(
			'buttons'        => __( 'One card with up to four buttons', 'moksa-line' ),
			'confirm'        => __( 'A question with exactly two answers', 'moksa-line' ),
			'carousel'       => __( 'Up to ten cards side by side, each with buttons', 'moksa-line' ),
			'image_carousel' => __( 'Up to ten images side by side, each tappable', 'moksa-line' ),
		);
	}

	/**
	 * Wrap a template object into a sendable message.
	 *
	 * @param string $alt_text Fallback text.
	 * @param array  $template Template object.
	 * @return array
	 */
	public static function message( string $alt_text, array $template ): array {
		return array(
			'type'     => 'template',
			// The same 1500 LINE allows on any altText.
			'altText'  => mb_substr( '' !== trim( $alt_text ) ? $alt_text : 'Message', 0, 1500 ),
			'template' => $template,
		);
	}

	/**
	 * Problems LINE would refuse the message for.
	 *
	 * Checked here as well as by LINE so the shop hears about it while editing
	 * rather than when a send fails, and in words that say what to do.
	 *
	 * @param array $template Template object.
	 * @return string[]
	 */
	public static function check( array $template ): array {
		$kind     = isset( $template['type'] ) ? (string) $template['type'] : '';
		$problems = array();

		if ( ! isset( self::kinds()[ $kind ] ) ) {
			return array( __( 'Choose what kind of template this is.', 'moksa-line' ) );
		}

		if ( 'confirm' === $kind ) {
			return self::check_confirm( $template );
		}

		if ( 'buttons' === $kind ) {
			return self::check_buttons( $template );
		}

		if ( 'image_carousel' === $kind ) {
			return self::check_image_carousel( $template );
		}

		return self::check_carousel( $template );
	}

	/**
	 * @param array $template Template object.
	 * @return string[]
	 */
	private static function check_confirm( array $template ): array {
		$problems = array();
		$text     = isset( $template['text'] ) ? (string) $template['text'] : '';
		$actions  = isset( $template['actions'] ) ? (array) $template['actions'] : array();

		if ( '' === trim( $text ) ) {
			$problems[] = __( 'A confirm template needs a question.', 'moksa-line' );
		} elseif ( mb_strlen( $text ) > self::CONFIRM_TEXT ) {
			$problems[] = sprintf(
				/* translators: %d: character limit. */
				__( 'The question is limited to %d characters.', 'moksa-line' ),
				self::CONFIRM_TEXT
			);
		}

		if ( count( $actions ) !== self::CONFIRM_ACTIONS ) {
			$problems[] = __( 'A confirm template must have exactly two buttons -- LINE refuses one or three.', 'moksa-line' );
		}

		return array_merge( $problems, self::check_actions( $actions ) );
	}

	/**
	 * @param array $template Template object.
	 * @return string[]
	 */
	private static function check_buttons( array $template ): array {
		$problems = array();
		$text     = isset( $template['text'] ) ? (string) $template['text'] : '';
		$actions  = isset( $template['actions'] ) ? (array) $template['actions'] : array();
		$adorned  = ! empty( $template['thumbnailImageUrl'] ) || ! empty( $template['title'] );
		$limit    = $adorned ? self::BUTTONS_TEXT_ADORNED : self::BUTTONS_TEXT;

		if ( '' === trim( $text ) ) {
			$problems[] = __( 'A buttons template needs some text.', 'moksa-line' );
		} elseif ( mb_strlen( $text ) > $limit ) {
			$problems[] = $adorned
				? sprintf(
					/* translators: %d: character limit. */
					__( 'With an image or a title, the text is limited to %d characters.', 'moksa-line' ),
					$limit
				)
				: sprintf(
					/* translators: %d: character limit. */
					__( 'The text is limited to %d characters.', 'moksa-line' ),
					$limit
				);
		}

		if ( isset( $template['title'] ) && mb_strlen( (string) $template['title'] ) > self::TITLE ) {
			$problems[] = sprintf(
				/* translators: %d: character limit. */
				__( 'The title is limited to %d characters.', 'moksa-line' ),
				self::TITLE
			);
		}

		if ( empty( $actions ) ) {
			$problems[] = __( 'Add at least one button.', 'moksa-line' );
		} elseif ( count( $actions ) > self::MAX_BUTTON_ACTIONS ) {
			$problems[] = sprintf(
				/* translators: %d: the button limit. */
				__( 'A buttons template holds at most %d buttons.', 'moksa-line' ),
				self::MAX_BUTTON_ACTIONS
			);
		}

		$problems = array_merge( $problems, self::check_actions( $actions ) );
		$problems = array_merge( $problems, self::check_image( $template['thumbnailImageUrl'] ?? '' ) );

		return $problems;
	}

	/**
	 * @param array $template Template object.
	 * @return string[]
	 */
	private static function check_carousel( array $template ): array {
		$problems = array();
		$columns  = isset( $template['columns'] ) ? (array) $template['columns'] : array();

		if ( empty( $columns ) ) {
			return array( __( 'A carousel needs at least one card.', 'moksa-line' ) );
		}

		if ( count( $columns ) > self::MAX_COLUMNS ) {
			$problems[] = sprintf(
				/* translators: %d: the column limit. */
				__( 'A carousel holds at most %d cards.', 'moksa-line' ),
				self::MAX_COLUMNS
			);
		}

		// The rule LINE enforces but the reference does not spell out.
		$shapes = array();

		foreach ( $columns as $index => $column ) {
			$column   = (array) $column;
			$actions  = isset( $column['actions'] ) ? (array) $column['actions'] : array();
			$adorned  = ! empty( $column['thumbnailImageUrl'] ) || ! empty( $column['title'] );
			$limit    = $adorned ? self::CAROUSEL_TEXT_ADORNED : self::CAROUSEL_TEXT;
			$number   = $index + 1;
			$shapes[] = ( ! empty( $column['thumbnailImageUrl'] ) ? 'i' : '-' )
				. ( ! empty( $column['title'] ) ? 't' : '-' )
				. count( $actions );

			$text = isset( $column['text'] ) ? (string) $column['text'] : '';

			if ( '' === trim( $text ) ) {
				$problems[] = sprintf(
					/* translators: %d: card number. */
					__( 'Card %d has no text.', 'moksa-line' ),
					$number
				);
			} elseif ( mb_strlen( $text ) > $limit ) {
				$problems[] = sprintf(
					/* translators: 1: card number, 2: character limit. */
					__( 'Card %1$d has more than the %2$d characters LINE allows here.', 'moksa-line' ),
					$number,
					$limit
				);
			}

			if ( isset( $column['title'] ) && mb_strlen( (string) $column['title'] ) > self::TITLE ) {
				$problems[] = sprintf(
					/* translators: 1: card number, 2: character limit. */
					__( 'The title on card %1$d is longer than %2$d characters.', 'moksa-line' ),
					$number,
					self::TITLE
				);
			}

			if ( empty( $actions ) ) {
				$problems[] = sprintf(
					/* translators: %d: card number. */
					__( 'Card %d has no buttons.', 'moksa-line' ),
					$number
				);
			} elseif ( count( $actions ) > self::MAX_CAROUSEL_ACTIONS ) {
				$problems[] = sprintf(
					/* translators: 1: card number, 2: the button limit. */
					__( 'Card %1$d has more than the %2$d buttons a carousel card allows.', 'moksa-line' ),
					$number,
					self::MAX_CAROUSEL_ACTIONS
				);
			}

			$problems = array_merge( $problems, self::check_actions( $actions, $number ) );
			$problems = array_merge( $problems, self::check_image( $column['thumbnailImageUrl'] ?? '', $number ) );
		}

		if ( count( array_unique( $shapes ) ) > 1 ) {
			$problems[] = __( 'Every card must be built the same way: all with an image or none, all with a title or none, and the same number of buttons. LINE refuses a carousel whose cards disagree.', 'moksa-line' );
		}

		return $problems;
	}

	/**
	 * @param array $template Template object.
	 * @return string[]
	 */
	private static function check_image_carousel( array $template ): array {
		$problems = array();
		$columns  = isset( $template['columns'] ) ? (array) $template['columns'] : array();

		if ( empty( $columns ) ) {
			return array( __( 'An image carousel needs at least one image.', 'moksa-line' ) );
		}

		if ( count( $columns ) > self::MAX_COLUMNS ) {
			$problems[] = sprintf(
				/* translators: %d: the column limit. */
				__( 'An image carousel holds at most %d images.', 'moksa-line' ),
				self::MAX_COLUMNS
			);
		}

		foreach ( $columns as $index => $column ) {
			$column = (array) $column;
			$number = $index + 1;

			if ( empty( $column['imageUrl'] ) ) {
				$problems[] = sprintf(
					/* translators: %d: image number. */
					__( 'Image %d has no picture.', 'moksa-line' ),
					$number
				);
			} else {
				$problems = array_merge( $problems, self::check_image( $column['imageUrl'], $number ) );
			}

			if ( empty( $column['action'] ) ) {
				$problems[] = sprintf(
					/* translators: %d: image number. */
					__( 'Image %d does nothing when tapped.', 'moksa-line' ),
					$number
				);
			} else {
				$problems = array_merge( $problems, self::check_actions( array( $column['action'] ), $number ) );
			}
		}

		return $problems;
	}

	/**
	 * @param array    $actions Action objects.
	 * @param int|null $card    Card number, when the actions belong to one.
	 * @return string[]
	 */
	private static function check_actions( array $actions, ?int $card = null ): array {
		$problems = array();

		foreach ( $actions as $action ) {
			$action = (array) $action;
			$label  = isset( $action['label'] ) ? (string) $action['label'] : '';
			$type   = isset( $action['type'] ) ? (string) $action['type'] : '';

			if ( '' === trim( $label ) ) {
				$problems[] = null === $card
					? __( 'Every button needs a label.', 'moksa-line' )
					: sprintf(
						/* translators: %d: card number. */
						__( 'A button on card %d has no label.', 'moksa-line' ),
						$card
					);
			} elseif ( mb_strlen( $label ) > self::LABEL ) {
				$problems[] = sprintf(
					/* translators: 1: the label, 2: character limit. */
					__( 'The button "%1$s" is longer than the %2$d characters LINE shows.', 'moksa-line' ),
					$label,
					self::LABEL
				);
			}

			if ( 'uri' === $type ) {
				$uri = isset( $action['uri'] ) ? (string) $action['uri'] : '';

				if ( '' === trim( $uri ) ) {
					$problems[] = sprintf(
						/* translators: %s: the button label. */
						__( 'The button "%s" opens a link but has none.', 'moksa-line' ),
						$label
					);
				} elseif ( ! preg_match( '#^(https://|tel:|line://|https?://line\.me/)#i', $uri ) ) {
					$problems[] = sprintf(
						/* translators: %s: the button label. */
						__( 'The link on "%s" must start with https://, tel: or a LINE URL.', 'moksa-line' ),
						$label
					);
				}
			} elseif ( 'message' === $type && '' === trim( (string) ( $action['text'] ?? '' ) ) ) {
				$problems[] = sprintf(
					/* translators: %s: the button label. */
					__( 'The button "%s" sends a message but has no text.', 'moksa-line' ),
					$label
				);
			} elseif ( 'postback' === $type && '' === trim( (string) ( $action['data'] ?? '' ) ) ) {
				$problems[] = sprintf(
					/* translators: %s: the button label. */
					__( 'The button "%s" is a postback but carries no data.', 'moksa-line' ),
					$label
				);
			}
		}

		return $problems;
	}

	/**
	 * @param string   $url  Image URL.
	 * @param int|null $card Card number, when the image belongs to one.
	 * @return string[]
	 */
	private static function check_image( string $url, ?int $card = null ): array {
		if ( '' === trim( $url ) ) {
			return array();
		}

		if ( 0 === stripos( $url, 'https://' ) ) {
			return array();
		}

		return array(
			null === $card
				? __( 'The image must be served over HTTPS. LINE refuses the whole message otherwise, not just the picture.', 'moksa-line' )
				: sprintf(
					/* translators: %d: card number. */
					__( 'The image on card %d must be served over HTTPS.', 'moksa-line' ),
					$card
				),
		);
	}
}
