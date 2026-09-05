<?php
/**
 * Local Flex Message checks.
 *
 * This is deliberately not a full schema implementation -- LINE's own
 * /v2/bot/message/validate/push endpoint is the authority, and the editor
 * calls it. What this covers is the structural mistakes that are worth
 * catching without a network round trip, and that 1.x shipped to production:
 * a bubble with no body, a carousel over the bubble limit, the removed
 * "filler" component, and enum typos on the properties people actually touch.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Flex;

defined( 'ABSPATH' ) || exit;

class Validator {

	/** A single bubble may not exceed 30 KB of JSON. */
	const BUBBLE_MAX_BYTES = 30720;
	/** A carousel may not exceed 50 KB of JSON. */
	const CAROUSEL_MAX_BYTES = 51200;
	/** A carousel holds at most 12 bubbles. */
	const CAROUSEL_MAX_BUBBLES = 12;

	/**
	 * Enum values for the properties that are most often mistyped.
	 *
	 * @var array<string,string[]>
	 */
	private static $enums = array(
		'layout'         => array( 'horizontal', 'vertical', 'baseline' ),
		'weight'         => array( 'regular', 'bold' ),
		'align'          => array( 'start', 'end', 'center' ),
		'gravity'        => array( 'top', 'bottom', 'center' ),
		'aspectMode'     => array( 'cover', 'fit' ),
		'position'       => array( 'relative', 'absolute' ),
		'style'          => array( 'primary', 'secondary', 'link' ),
		'height'         => array( 'sm', 'md' ),
		'direction'      => array( 'ltr', 'rtl' ),
		'adjustMode'     => array( 'shrink-to-fit' ),
		'decoration'     => array( 'none', 'underline', 'line-through' ),
		'wrap'           => array(),
	);

	/**
	 * Components allowed directly inside each bubble section.
	 *
	 * @var array<string,string[]>
	 */
	private static $section_types = array(
		'header' => array( 'box' ),
		'hero'   => array( 'box', 'image', 'video' ),
		'body'   => array( 'box' ),
		'footer' => array( 'box' ),
	);

	/**
	 * Component types that may appear inside a box.
	 *
	 * @var string[]
	 */
	private static $box_children = array(
		'box', 'button', 'image', 'icon', 'text', 'span', 'separator', 'video',
	);

	/**
	 * Check a complete flex message object.
	 *
	 * @param array $message Message with type=flex.
	 * @return string[] Human-readable problems; empty when the message looks sane.
	 */
	public static function check_message( array $message ): array {
		$problems = array();

		if ( empty( $message['altText'] ) || ! is_string( $message['altText'] ) ) {
			$problems[] = __( 'A Flex message needs altText (shown in the chat list and push notification).', 'moksa-line-login' );
		} elseif ( mb_strlen( $message['altText'] ) > 400 ) {
			$problems[] = __( 'altText is limited to 400 characters.', 'moksa-line-login' );
		}

		if ( empty( $message['contents'] ) || ! is_array( $message['contents'] ) ) {
			$problems[] = __( 'A Flex message needs a contents container.', 'moksa-line-login' );

			return $problems;
		}

		return array_merge( $problems, self::check_container( $message['contents'] ) );
	}

	/**
	 * Check a bubble or carousel container.
	 *
	 * @param array $container Container object.
	 * @return string[]
	 */
	public static function check_container( array $container ): array {
		$problems = array();
		$type     = isset( $container['type'] ) ? $container['type'] : '';

		if ( 'carousel' === $type ) {
			if ( empty( $container['contents'] ) || ! is_array( $container['contents'] ) ) {
				$problems[] = __( 'A carousel needs at least one bubble in contents.', 'moksa-line-login' );

				return $problems;
			}

			if ( count( $container['contents'] ) > self::CAROUSEL_MAX_BUBBLES ) {
				$problems[] = sprintf(
					/* translators: %d: maximum number of bubbles. */
					__( 'A carousel holds at most %d bubbles.', 'moksa-line-login' ),
					self::CAROUSEL_MAX_BUBBLES
				);
			}

			if ( self::byte_size( $container ) > self::CAROUSEL_MAX_BYTES ) {
				$problems[] = __( 'This carousel exceeds the 50 KB limit. Remove bubbles or shorten the content.', 'moksa-line-login' );
			}

			foreach ( $container['contents'] as $index => $bubble ) {
				foreach ( self::check_bubble( (array) $bubble ) as $problem ) {
					$problems[] = sprintf(
						/* translators: 1: bubble number, 2: the problem. */
						__( 'Bubble %1$d: %2$s', 'moksa-line-login' ),
						(int) $index + 1,
						$problem
					);
				}
			}

			return $problems;
		}

		if ( 'bubble' === $type ) {
			return self::check_bubble( $container );
		}

		$problems[] = __( 'contents must be a bubble or a carousel.', 'moksa-line-login' );

		return $problems;
	}

	/**
	 * Check one bubble.
	 *
	 * @param array $bubble Bubble object.
	 * @return string[]
	 */
	public static function check_bubble( array $bubble ): array {
		$problems = array();

		if ( ! isset( $bubble['type'] ) || 'bubble' !== $bubble['type'] ) {
			$problems[] = __( 'Expected a bubble here.', 'moksa-line-login' );

			return $problems;
		}

		$has_section = false;

		foreach ( self::$section_types as $section => $allowed ) {
			if ( ! isset( $bubble[ $section ] ) ) {
				continue;
			}

			$has_section = true;
			$component   = (array) $bubble[ $section ];
			$child_type  = isset( $component['type'] ) ? $component['type'] : '';

			if ( ! in_array( $child_type, $allowed, true ) ) {
				$problems[] = sprintf(
					/* translators: 1: section name, 2: allowed types. */
					__( 'The %1$s section must contain one of: %2$s.', 'moksa-line-login' ),
					$section,
					implode( ', ', $allowed )
				);

				continue;
			}

			$problems = array_merge( $problems, self::check_component( $component, $section ) );
		}

		if ( ! $has_section ) {
			$problems[] = __( 'A bubble needs at least one of header, hero, body or footer.', 'moksa-line-login' );
		}

		if ( self::byte_size( $bubble ) > self::BUBBLE_MAX_BYTES ) {
			$problems[] = __( 'This bubble exceeds the 30 KB limit.', 'moksa-line-login' );
		}

		return $problems;
	}

	/**
	 * Recursively check one component.
	 *
	 * @param array  $component Component object.
	 * @param string $path      Human-readable location for messages.
	 * @param int    $depth     Recursion guard.
	 * @return string[]
	 */
	public static function check_component( array $component, string $path = '', int $depth = 0 ): array {
		$problems = array();

		if ( $depth > 12 ) {
			$problems[] = __( 'This layout nests too deeply.', 'moksa-line-login' );

			return $problems;
		}

		$type = isset( $component['type'] ) ? $component['type'] : '';

		if ( '' === $type ) {
			$problems[] = sprintf(
				/* translators: %s: location in the message. */
				__( 'A component at %s is missing its type.', 'moksa-line-login' ),
				$path ? $path : 'root'
			);

			return $problems;
		}

		if ( 'filler' === $type ) {
			$problems[] = __( 'The filler component was removed by LINE. Use a box with margin, padding or offset instead.', 'moksa-line-login' );
		}

		// Per-type required properties.
		switch ( $type ) {
			case 'box':
				if ( empty( $component['layout'] ) ) {
					$problems[] = sprintf(
						/* translators: %s: location in the message. */
						__( 'The box at %s needs a layout.', 'moksa-line-login' ),
						$path ? $path : 'root'
					);
				}

				if ( ! isset( $component['contents'] ) || ! is_array( $component['contents'] ) ) {
					$problems[] = sprintf(
						/* translators: %s: location in the message. */
						__( 'The box at %s needs a contents array.', 'moksa-line-login' ),
						$path ? $path : 'root'
					);
				}
				break;

			case 'text':
				if ( ! isset( $component['text'] ) && empty( $component['contents'] ) ) {
					$problems[] = __( 'A text component needs either text or a contents array of spans.', 'moksa-line-login' );
				}
				break;

			case 'image':
			case 'video':
				if ( empty( $component['url'] ) ) {
					$problems[] = sprintf(
						/* translators: %s: component type. */
						__( 'A %s component needs a url.', 'moksa-line-login' ),
						$type
					);
				} elseif ( 0 !== strpos( (string) $component['url'], 'https://' ) ) {
					$problems[] = __( 'Media URLs must use HTTPS.', 'moksa-line-login' );
				}
				break;

			case 'icon':
				if ( empty( $component['url'] ) ) {
					$problems[] = __( 'An icon component needs a url.', 'moksa-line-login' );
				}
				break;

			case 'button':
				if ( empty( $component['action'] ) || ! is_array( $component['action'] ) ) {
					$problems[] = __( 'A button needs an action.', 'moksa-line-login' );
				} else {
					$problems = array_merge( $problems, self::check_action( $component['action'] ) );
				}
				break;
		}

		// Enum typos.
		foreach ( self::$enums as $property => $allowed ) {
			if ( empty( $allowed ) || ! isset( $component[ $property ] ) || ! is_string( $component[ $property ] ) ) {
				continue;
			}

			// height is an enum on buttons only; elsewhere it is a size string.
			if ( 'height' === $property && 'button' !== $type ) {
				continue;
			}

			// style is an enum on buttons; on a box it is a border style.
			if ( 'style' === $property && 'button' !== $type ) {
				continue;
			}

			if ( ! in_array( $component[ $property ], $allowed, true ) ) {
				$problems[] = sprintf(
					/* translators: 1: property, 2: supplied value, 3: allowed values. */
					__( '%1$s cannot be "%2$s". Allowed: %3$s.', 'moksa-line-login' ),
					$property,
					$component[ $property ],
					implode( ', ', $allowed )
				);
			}
		}

		if ( isset( $component['action'] ) && is_array( $component['action'] ) && 'button' !== $type ) {
			$problems = array_merge( $problems, self::check_action( $component['action'] ) );
		}

		if ( ! empty( $component['contents'] ) && is_array( $component['contents'] ) ) {
			foreach ( $component['contents'] as $index => $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}

				if ( 'box' === $type && isset( $child['type'] ) && ! in_array( $child['type'], self::$box_children, true ) ) {
					$problems[] = sprintf(
						/* translators: %s: component type. */
						__( '"%s" cannot be placed inside a box.', 'moksa-line-login' ),
						$child['type']
					);
				}

				$problems = array_merge(
					$problems,
					self::check_component( $child, $path . '.contents[' . $index . ']', $depth + 1 )
				);
			}
		}

		return $problems;
	}

	/**
	 * Check an action object.
	 *
	 * @param array $action Action object.
	 * @return string[]
	 */
	public static function check_action( array $action ): array {
		$problems = array();
		$type     = isset( $action['type'] ) ? $action['type'] : '';

		$required = array(
			'uri'            => array( 'uri' ),
			'message'        => array( 'text' ),
			'postback'       => array( 'data' ),
			'datetimepicker' => array( 'data', 'mode' ),
			'richmenuswitch' => array( 'richMenuAliasId', 'data' ),
			'camera'         => array(),
			'cameraRoll'     => array(),
			'location'       => array(),
			'clipboard'      => array( 'clipboardText' ),
		);

		if ( ! isset( $required[ $type ] ) ) {
			$problems[] = sprintf(
				/* translators: %s: action type. */
				__( '"%s" is not a valid action type.', 'moksa-line-login' ),
				$type
			);

			return $problems;
		}

		foreach ( $required[ $type ] as $field ) {
			if ( empty( $action[ $field ] ) ) {
				$problems[] = sprintf(
					/* translators: 1: action type, 2: missing field. */
					__( 'A %1$s action needs %2$s.', 'moksa-line-login' ),
					$type,
					$field
				);
			}
		}

		if ( isset( $action['label'] ) && mb_strlen( (string) $action['label'] ) > 20 ) {
			$problems[] = __( 'Action labels are limited to 20 characters.', 'moksa-line-login' );
		}

		if ( 'uri' === $type && ! empty( $action['uri'] ) ) {
			$scheme = wp_parse_url( (string) $action['uri'], PHP_URL_SCHEME );

			if ( ! in_array( $scheme, array( 'https', 'tel', 'line' ), true ) ) {
				$problems[] = __( 'A uri action must point at https://, tel: or a LINE URL scheme.', 'moksa-line-login' );
			}
		}

		return $problems;
	}

	/**
	 * Serialized byte size, used for the container limits.
	 *
	 * @param array $data Any structure.
	 */
	private static function byte_size( array $data ): int {
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return false === $json ? 0 : strlen( $json );
	}
}
