<?php
/**
 * How long a LINE login actually lasts.
 *
 *   wp eval-file tests/session-check.php
 *
 * Reading the code tells you what was intended. This asks WordPress what it
 * computed: it hooks the moment the auth cookie is built and reads the
 * expiry out of it, for every value the setting can take.
 *
 * The other half matters just as much. auth_cookie_expiration is a global
 * filter, so a careless implementation of "keep LINE logins for 30 days"
 * silently keeps password logins for 30 days too. The last checks here sign
 * somebody in the ordinary way afterwards and assert nothing rubbed off.
 *
 * No cookies are sent -- send_auth_cookies is filtered off -- and the setting
 * is put back exactly as it was found.
 *
 * @package Mofoline
 */

use Mofoline\Login\LoginModule;
use Mofoline\Support\Options;

$GLOBALS['session_fail'] = 0;

function session_check( $ok, $label, $detail = '' ) {
	if ( $ok ) {
		echo "  ok    {$label}\n";
		return;
	}

	++$GLOBALS['session_fail'];
	echo "  FAIL  {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

// Nothing may actually reach a browser from a test.
add_filter( 'send_auth_cookies', '__return_false' );

$GLOBALS['session_seen'] = null;

add_action(
	'set_auth_cookie',
	function ( $cookie, $expire, $expiration ) {
		$GLOBALS['session_seen'] = array(
			'expire'     => (int) $expire,
			'expiration' => (int) $expiration,
		);
	},
	10,
	3
);

/**
 * Sign somebody in through the plugin's own path and report what WordPress
 * worked out.
 *
 * start_session() is private, which is the point -- going through the public
 * caller is what makes this a test of the thing that runs in production.
 *
 * @param int $user_id Who to sign in.
 * @return array{expire:int,expiration:int}|null
 */
function session_measure( $user_id ) {
	$GLOBALS['session_seen'] = null;

	$GLOBALS['session_login_fired'] = false;

	$method = new ReflectionMethod( LoginModule::class, 'start_session' );
	$method->setAccessible( true );
	$method->invoke( Mofoline\Plugin::instance()->module( 'login' ), $user_id );

	return $GLOBALS['session_seen'];
}

add_action(
	'wp_login',
	static function () {
		$GLOBALS['session_login_fired'] = true;
	}
);

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );

if ( empty( $admin ) ) {
	echo "REFUSED: no administrator to measure against.\n";
	return;
}

$user_id  = (int) $admin[0];
$previous = (string) Options::get( 'login_duration' );

echo "Session length\n";

$cases = array(
	'browser' => array( 'days' => 0, 'label' => 'until the browser closes' ),
	'default' => array( 'days' => 14, 'label' => "WordPress's own 14 days" ),
	'30'      => array( 'days' => 30, 'label' => '30 days' ),
	'90'      => array( 'days' => 90, 'label' => '90 days' ),
);

foreach ( $cases as $setting => $expected ) {
	Options::set( 'login_duration', $setting );

	$seen = session_measure( $user_id );

	session_check( true === $GLOBALS['session_login_fired'], 'wp_login fired for ' . $setting );

	if ( null === $seen ) {
		session_check( false, $expected['label'], 'no auth cookie was built' );
		continue;
	}

	$days = ( $seen['expiration'] - time() ) / DAY_IN_SECONDS;

	if ( 0 === $expected['days'] ) {
		// A session cookie is the one with no expiry date on it at all. The
		// token behind it still has a life, which is WordPress's two days.
		session_check( 0 === $seen['expire'], $expected['label'], 'expire=' . $seen['expire'] );
		session_check( round( $days ) <= 2, 'the token behind it is short-lived', round( $days, 2 ) . ' days' );
		continue;
	}

	session_check(
		abs( $days - $expected['days'] ) < 0.01,
		$expected['label'],
		'got ' . round( $days, 2 ) . ' days'
	);
	session_check( $seen['expire'] > $seen['expiration'], 'the cookie outlives the token slightly, as WordPress intends' );
}

// --- and the half that is easy to get wrong -------------------------------

echo "  nothing rubs off on other logins\n";

Options::set( 'login_duration', '90' );
session_measure( $user_id );

// An ordinary password login, right after a 90-day LINE one.
$GLOBALS['session_seen'] = null;
wp_set_auth_cookie( $user_id, true );
$after = $GLOBALS['session_seen'];

$after_days = null === $after ? null : ( $after['expiration'] - time() ) / DAY_IN_SECONDS;

session_check(
	null !== $after && abs( $after_days - 14 ) < 0.01,
	'a password login still lasts 14 days',
	null === $after ? 'no cookie built' : 'got ' . round( $after_days, 2 ) . ' days'
);

session_check(
	! has_filter( 'auth_cookie_expiration' ),
	'no expiry filter is left hanging on the hook afterwards'
);

Options::set( 'login_duration', $previous );
session_check( $previous === (string) Options::get( 'login_duration' ), 'the setting was put back' );

echo "\n" . ( 0 === $GLOBALS['session_fail'] ? "SESSION LENGTH OK\n" : $GLOBALS['session_fail'] . " checks failed\n" );
