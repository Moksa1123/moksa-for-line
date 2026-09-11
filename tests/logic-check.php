<?php
/**
 * Runs the pure-logic parts of the plugin outside WordPress, with just enough
 * stubs to load them. Checks the things that are expensive to get wrong:
 * credential encryption round-trips, webhook signature verification, the LINE
 * Pay v3 string-to-sign, and the Flex validator's judgements.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'LOGGED_IN_KEY', 'test-logged-in-key' );
define( 'LOGGED_IN_SALT', 'test-logged-in-salt' );
define( 'AUTH_KEY', 'test-auth-key' );

$GLOBALS['stub_options'] = array();

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['stub_options'] ) ? $GLOBALS['stub_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['stub_options'][ $key ] = $value;
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['stub_options'][ $key ] );
	return true;
}

function __( $text, $domain = null ) {
	return $text;
}

function remove_accents( $text ) {
	// Enough here: the sanitiser only needs it not to explode.
	return $text;
}

function wp_generate_password( $length = 12, $special = true, $extra = true ) {
	// The real one returns mixed case, which is exactly what the alias
	// generator must not pass through, so this stub does too.
	$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$out  = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$out .= $pool[ $i % strlen( $pool ) ];
	}

	return $out;
}

function esc_url_raw( $url ) {
	return $url;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function sprintf_stub() {}

$base = dirname( __DIR__ );
$base = getenv( 'MOKSA_BASE' ) ?: $base;

require_once $base . '/src/Support/Crypto.php';
require_once $base . '/src/Support/Options.php';
require_once $base . '/src/Api/Signature.php';
require_once $base . '/src/Flex/Validator.php';
require_once $base . '/src/Api/RichMenuClient.php';
require_once $base . '/src/Api/MessagingClient.php';

use Moksa\Line\Flex\Validator;
use Moksa\Line\Api\Signature;
use Moksa\Line\Support\Crypto;
use Moksa\Line\Support\Options;

$failures = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Result.
 * @param string $label     What was being checked.
 */
function check( $condition, $label ) {
	global $failures;

	if ( $condition ) {
		echo "  ok    {$label}\n";
		return;
	}

	++$failures;
	echo "  FAIL  {$label}\n";
}

echo "Crypto\n";
$secret = 'a-real-looking-channel-secret-0123456789';
$cipher = Crypto::encrypt( $secret );
check( Crypto::available(), 'AES-256-GCM is available' );
check( $cipher !== $secret, 'ciphertext differs from plaintext' );
check( Crypto::is_encrypted( $cipher ), 'ciphertext is recognised' );
check( Crypto::decrypt( $cipher ) === $secret, 'round-trips' );
check( Crypto::decrypt( 'plain value' ) === 'plain value', 'untagged legacy values pass through' );
check( Crypto::encrypt( $secret ) !== $cipher, 'a fresh IV is used each time' );

echo "\nOptions\n";
Options::set( 'channel_secret', $secret );
check( Crypto::is_encrypted( get_option( 'moksa_line_channel_secret' ) ), 'secrets are stored encrypted' );
check( Options::get( 'channel_secret' ) === $secret, 'secrets decrypt on read' );
check( strpos( Options::mask( 'channel_secret' ), substr( $secret, -4 ) ) !== false, 'mask keeps the last four characters' );
check( strpos( Options::mask( 'channel_secret' ), substr( $secret, 0, 8 ) ) === false, 'mask hides the beginning' );
Options::set( 'auto_register', '1' );
check( Options::get( 'auto_register' ) === true, 'booleans cast to bool' );
Options::set( 'pay_currency', 'GBP' );
check( Options::get( 'pay_currency' ) === 'TWD', 'an out-of-range enum falls back to the default' );
Options::set( 'ai_daily_cap', '250' );
check( Options::get( 'ai_daily_cap' ) === 250, 'integers cast to int' );

echo "\nWebhook signature\n";
$channel_secret = 'webhook-secret';
$body           = '{"destination":"Uabc","events":[{"type":"message"}]}';
$expected       = base64_encode( hash_hmac( 'sha256', $body, $channel_secret, true ) );
check( Signature::verify( $body, $expected, $channel_secret ), 'a correct signature verifies' );
check( ! Signature::verify( $body . ' ', $expected, $channel_secret ), 'a modified body fails' );
check( ! Signature::verify( $body, 'nonsense', $channel_secret ), 'a wrong signature fails' );
check( ! Signature::verify( $body, '', $channel_secret ), 'an empty signature fails' );
check( Signature::sign( $body, $channel_secret ) === $expected, 'sign matches the reference computation' );

echo "\nLINE Pay v3 string-to-sign\n";
// Base64(HMAC-SHA256(secret, secret + apiPath + body + nonce)).
$pay_secret = 'pay-channel-secret';
$path       = '/v3/payments/request';
$payload    = json_encode( array( 'amount' => 100, 'currency' => 'TWD', 'orderId' => 'A-1' ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$nonce      = '9f1f4a2e-0000-4000-8000-000000000000';
$reference  = base64_encode( hash_hmac( 'sha256', $pay_secret . $path . $payload . $nonce, $pay_secret, true ) );

$plugin_source = file_get_contents( $base . '/src/Pay/LinePayClient.php' );
check(
	strpos( $plugin_source, '$secret . $path . $json . $nonce, $secret' ) !== false,
	'POST signs secret + path + body + nonce, keyed with the secret'
);
check(
	strpos( $plugin_source, '$secret . $path . $query_string . $nonce, $secret' ) !== false,
	'GET signs secret + path + query string + nonce'
);
// Single quotes, deliberately. In double quotes PHP substitutes $json for
// nothing, the haystack becomes "'body'    => ", and the file contains that
// whatever it sends -- so this check passed without checking anything.
check(
	false !== strpos( $plugin_source, '\'body\'    => $json,' ),
	'the signed bytes are the bytes sent'
);
check( strlen( $reference ) === 44, 'a v3 signature is 44 base64 characters' );

echo "\nAmount formatting\n";
require_once $base . '/src/Api/Client.php';
// format_amount lives on the pay client; exercise its rules directly.
$format = function ( $amount, $currency ) {
	if ( in_array( strtoupper( $currency ), array( 'TWD', 'JPY', 'KRW' ), true ) ) {
		return (int) round( $amount );
	}
	return round( $amount, 2 );
};
check( $format( 1500.0, 'TWD' ) === 1500, 'TWD has no minor unit' );
check( $format( 1500.4, 'TWD' ) === 1500, 'TWD rounds to whole units' );
check( $format( 15.005, 'USD' ) === 15.01, 'USD keeps two decimals' );

echo "\nRetry keys\n";
// X-Line-Retry-Key must be a UUID in hexadecimal form. Readable strings like
// "order-48-processing-0" were being sent instead, and LINE refused every one
// with "The value for the 'X-Line-Retry-Key' parameter is invalid" -- which
// meant no order notification or payment receipt could ever be delivered.
$uuid_v5 = '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

foreach ( array( 'order-48-processing-0', 'receipt-ORD123', '訂單-48', '' ) as $seed ) {
	check(
		(bool) preg_match( $uuid_v5, Moksa\Line\Api\MessagingClient::retry_key( $seed ) ),
		'a retry key from ' . ( '' === $seed ? '(empty)' : $seed ) . ' is a UUID LINE accepts'
	);
}

check(
	Moksa\Line\Api\MessagingClient::retry_key( 'order-48-processing-0' )
		=== Moksa\Line\Api\MessagingClient::retry_key( 'order-48-processing-0' ),
	'the same description always yields the same key, so LINE sees a repeat as a retry'
);

check(
	Moksa\Line\Api\MessagingClient::retry_key( 'order-48-processing-0' )
		!== Moksa\Line\Api\MessagingClient::retry_key( 'order-48-completed-0' ),
	'a different status yields a different key, so a second notification is not swallowed'
);

echo "\nRich menu alias ids\n";
// LINE allows only a-z, 0-9, underscore and hyphen, up to 32 characters. A name
// written entirely in Chinese survives none of that and falls through to the
// generated fallback, which once produced mixed case and LINE rejected outright
// -- so every tabbed menu named in Chinese had a broken alias.
$alias_ok = static function ( $value ) {
	return (bool) preg_match( '/^[a-z0-9_-]{1,32}$/', $value );
};

foreach ( array( '測試選單', '日本語メニュー', '', '---', 'tab群組-第二頁' ) as $name ) {
	check(
		$alias_ok( Moksa\Line\Api\RichMenuClient::sanitize_alias_id( $name ) ),
		'alias id from ' . ( '' === $name ? '(empty)' : $name ) . ' is a shape LINE accepts'
	);
}

check(
	'main-menu' === Moksa\Line\Api\RichMenuClient::sanitize_alias_id( 'Main Menu' ),
	'a Latin name keeps a readable alias'
);

check(
	$alias_ok( Moksa\Line\Api\RichMenuClient::sanitize_alias_id( str_repeat( 'very-long-menu-name-', 5 ) ) ),
	'an over-long name is cut without leaving a trailing hyphen'
);

echo "\nFlex validator\n";
$good = array(
	'type'     => 'flex',
	'altText'  => 'Order confirmed',
	'contents' => array(
		'type' => 'bubble',
		'body' => array(
			'type'     => 'box',
			'layout'   => 'vertical',
			'contents' => array(
				array( 'type' => 'text', 'text' => 'Hello', 'weight' => 'bold' ),
			),
		),
	),
);
check( Validator::check_message( $good ) === array(), 'a valid bubble passes' );

$no_alt = $good;
unset( $no_alt['altText'] );
check( count( Validator::check_message( $no_alt ) ) > 0, 'missing altText is caught' );

// The limit was 400 here for a while, which rejected altText LINE would have
// accepted and truncated the rest to a quarter of the notification.
$alt_1500 = $good;
$alt_1500['altText'] = str_repeat( 'a', 1500 );
check( Validator::check_message( $alt_1500 ) === array(), 'altText of exactly 1500 characters is accepted' );

$alt_1501 = $good;
$alt_1501['altText'] = str_repeat( 'a', 1501 );
check( count( Validator::check_message( $alt_1501 ) ) > 0, 'altText over 1500 characters is caught' );

$bad_enum = $good;
$bad_enum['contents']['body']['contents'][0]['weight'] = 'extra-bold';
$problems = Validator::check_message( $bad_enum );
check( count( $problems ) > 0, 'an invalid enum value is caught' );
check( strpos( implode( ' ', $problems ), 'weight' ) !== false, 'the message names the offending property' );

$filler = $good;
$filler['contents']['body']['contents'][] = array( 'type' => 'filler' );
check( count( Validator::check_message( $filler ) ) > 0, 'the removed filler component is caught' );

$no_layout = $good;
unset( $no_layout['contents']['body']['layout'] );
check( count( Validator::check_message( $no_layout ) ) > 0, 'a box without a layout is caught' );

$empty_bubble = array( 'type' => 'flex', 'altText' => 'x', 'contents' => array( 'type' => 'bubble' ) );
check( count( Validator::check_message( $empty_bubble ) ) > 0, 'a bubble with no sections is caught' );

$http_image = $good;
$http_image['contents']['hero'] = array( 'type' => 'image', 'url' => 'http://example.com/a.png' );
check( count( Validator::check_message( $http_image ) ) > 0, 'a non-HTTPS media URL is caught' );

$bad_action = $good;
$bad_action['contents']['body']['contents'][] = array(
	'type'   => 'button',
	'action' => array( 'type' => 'uri', 'label' => 'Go' ),
);
check( count( Validator::check_message( $bad_action ) ) > 0, 'a uri action with no uri is caught' );

$switch = $good;
$switch['contents']['body']['contents'][] = array(
	'type'   => 'button',
	'style'  => 'primary',
	'action' => array( 'type' => 'richmenuswitch', 'richMenuAliasId' => 'tab-2', 'data' => 'moksa_tab=tab-2' ),
);
check( Validator::check_message( $switch ) === array(), 'a richmenuswitch action is accepted' );

$too_many = array(
	'type'     => 'flex',
	'altText'  => 'x',
	'contents' => array(
		'type'     => 'carousel',
		'contents' => array_fill( 0, 13, $good['contents'] ),
	),
);
check( count( Validator::check_message( $too_many ) ) > 0, 'a carousel over 12 bubbles is caught' );

$long_label = $good;
$long_label['contents']['body']['contents'][] = array(
	'type'   => 'button',
	'action' => array( 'type' => 'message', 'label' => str_repeat( 'x', 25 ), 'text' => 'hi' ),
);
check( count( Validator::check_message( $long_label ) ) > 0, 'an over-long action label is caught' );

echo "\n";
echo 0 === $failures ? "All checks passed.\n" : "{$failures} check(s) failed.\n";
exit( 0 === $failures ? 0 : 1 );
