<?php
/**
 * The login callback, exercised over HTTP.
 *
 * Run with: wp eval-file tests/callback-check.php
 *
 * The callback reads the query string through filter_input(), which only
 * sees a real request -- so these checks go out through the web server and
 * come back, rather than setting $_GET and calling the method. Each case
 * plants a state the way authorize_url() does, then opens the callback the
 * way LINE would, and asserts the answer:
 *
 *   - a link started by user 1, opened anonymously  -> refused (login CSRF)
 *   - a login with a code LINE will not accept      -> refused, not redirected
 *   - the user cancelling at LINE                   -> sent back, no error page
 *   - a state used twice                            -> refused as expired
 *
 * The third case is the one that found a bug: query_has() once used a filter
 * flag that made "absent" read as "present", and every callback went down
 * the cancel branch.
 *
 * @package Mofoline
 */

defined( 'ABSPATH' ) || exit;

$callback_fails = 0;

function callback_check( bool $ok, string $label, string $detail = '' ): void {
	global $callback_fails;

	if ( ! $ok ) {
		++$callback_fails;
	}

	printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' !== $detail ? " -- $detail" : '' );
}

$plant = static function ( string $state, int $link_to ): void {
	set_transient(
		'mofoline_' . hash( 'sha256', $state ),
		array(
			'code_verifier' => str_repeat( 'a', 43 ),
			'redirect'      => home_url( '/' ),
			'link_to'       => $link_to,
		),
		300
	);
};

$open = static function ( array $query ): array {
	$url      = add_query_arg( $query, admin_url( 'admin-ajax.php?action=mofoline_callback' ) );
	$response = wp_remote_get( $url, array( 'redirection' => 0, 'timeout' => 20, 'sslverify' => false ) );

	if ( is_wp_error( $response ) ) {
		return array( 'status' => 0, 'location' => '', 'body' => $response->get_error_message() );
	}

	return array(
		'status'   => (int) wp_remote_retrieve_response_code( $response ),
		'location' => (string) wp_remote_retrieve_header( $response, 'location' ),
		'body'     => wp_strip_all_tags( (string) wp_remote_retrieve_body( $response ) ),
	);
};

$suffix = wp_generate_password( 8, false );

echo "Login callback over HTTP\n";

$plant( "link-$suffix", 1 );
$r = $open( array( 'state' => "link-$suffix", 'code' => 'bogus' ) );
callback_check( 400 === $r['status'], 'a link opened from another session is refused', 'HTTP ' . $r['status'] );
callback_check( false !== strpos( $r['body'], __( 'This link was started from a different account. Please start again from your own account page.', 'moksa-for-line' ) ), 'and says why' );

$plant( "login-$suffix", 0 );
$r = $open( array( 'state' => "login-$suffix", 'code' => 'bogus' ) );
callback_check( 400 === $r['status'], 'a code LINE rejects ends on an error page, not a redirect', 'HTTP ' . $r['status'] . ' ' . $r['location'] );

$r = $open( array( 'state' => "login-$suffix", 'code' => 'bogus' ) );
callback_check( 400 === $r['status'] && false !== strpos( $r['body'], __( 'This login link has expired. Please try again.', 'moksa-for-line' ) ), 'the same state a second time is expired', 'HTTP ' . $r['status'] );

$plant( "cancel-$suffix", 0 );
$r = $open( array( 'state' => "cancel-$suffix", 'error' => 'access_denied' ) );
callback_check( 302 === $r['status'] && 0 === strpos( $r['location'], home_url( '/' ) ), 'cancelling at LINE goes back to the site', 'HTTP ' . $r['status'] . ' ' . $r['location'] );

set_transient(
	'mofoline_' . hash( 'sha256', "evil-$suffix" ),
	array( 'code_verifier' => str_repeat( 'a', 43 ), 'redirect' => 'https://evil.example/steal', 'link_to' => 0 ),
	300
);
$r = $open( array( 'state' => "evil-$suffix", 'error' => 'access_denied' ) );
callback_check( 302 === $r['status'] && false === strpos( $r['location'], 'evil.example' ), 'an off-site redirect stored in the state is not followed', $r['location'] );

echo $callback_fails ? "\n$callback_fails FAILURE(S)\n" : "\nLOGIN CALLBACK OK\n";
