<?php
/**
 * Every AJAX endpoint this plugin registers must check who is asking.
 *
 *   wp eval-file tests/ajax-check.php
 *
 * A sweep of the live endpoints found them all guarded, which is the state
 * worth keeping rather than the state worth celebrating: the next handler
 * someone adds is the one that will forget. This reads each registered
 * callback's own source and asserts two things are in it -- a nonce check and
 * a capability check -- so a new endpoint without them fails here rather than
 * on the internet.
 *
 * It cannot prove the checks run before the work, only that they are present.
 * That is still the difference between "somebody thought about it" and "nobody
 * did", which is the failure this is for.
 *
 * Reads only. Sends nothing, writes nothing.
 *
 * @package Mofoline
 */

$GLOBALS['ajax_fail'] = 0;

function ajax_check( $ok, $label, $detail = '' ) {
	if ( $ok ) {
		echo "  ok    {$label}\n";
		return;
	}

	++$GLOBALS['ajax_fail'];
	echo "  FAIL  {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

/**
 * The source of whatever is hooked to an action.
 *
 * @param callable $callback Hooked callback.
 * @return string Its source, or '' when it cannot be read.
 */
function ajax_source_of( $callback ) {
	try {
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$reflection = new ReflectionMethod( $callback[0], $callback[1] );
		} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			$reflection = new ReflectionMethod( $callback );
		} elseif ( is_string( $callback ) || $callback instanceof Closure ) {
			$reflection = new ReflectionFunction( $callback );
		} else {
			return '';
		}
	} catch ( ReflectionException $e ) {
		return '';
	}

	$file = $reflection->getFileName();

	if ( ! $file || ! is_readable( $file ) ) {
		return '';
	}

	$lines = file( $file );
	$start = $reflection->getStartLine() - 1;
	$end   = $reflection->getEndLine();

	return implode( '', array_slice( $lines, $start, $end - $start ) );
}

/**
 * Whichever helper a handler reaches for, these all end in the same two
 * checks. Ajax::guard() does both at once.
 */
$nonce_markers = array( 'check_ajax_referer', 'Ajax::guard', '$this->guard()', '$this->webhook_guard()', 'self::guard' );
$cap_markers   = array( 'current_user_can', 'Ajax::guard', '$this->guard()', '$this->webhook_guard()', 'self::guard', 'can_manage', 'can_view' );

$registered = array();

foreach ( array_keys( $GLOBALS['wp_filter'] ) as $hook ) {
	if ( 0 !== strpos( $hook, 'wp_ajax_' ) ) {
		continue;
	}

	$action = substr( $hook, strlen( 'wp_ajax_' ) );
	$public = false;

	if ( 0 === strpos( $action, 'nopriv_' ) ) {
		$action = substr( $action, strlen( 'nopriv_' ) );
		$public = true;
	}

	if ( 0 !== strpos( $action, 'mofoline_' ) ) {
		continue;
	}

	foreach ( $GLOBALS['wp_filter'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$registered[ $action ]['public']   = ( $registered[ $action ]['public'] ?? false ) || $public;
			$registered[ $action ]['source'] ??= '';
			$registered[ $action ]['source']  .= ajax_source_of( $entry['function'] );
		}
	}
}

ksort( $registered );

echo "AJAX endpoints\n";
ajax_check( count( $registered ) > 0, 'found registered endpoints', 'none -- is the plugin active?' );

// Endpoints that are deliberately open, with the reason they can be.
$open = array(
	// The two halves of the LINE Login redirect. A visitor who is not signed
	// in yet is exactly who these are for; the state transient is what makes
	// them safe, and tests/logic-check.php covers that.
	'mofoline_start'    => 'starts the OAuth redirect, before anybody is signed in',
	'mofoline_callback' => 'receives the OAuth redirect, before anybody is signed in',
);

// Endpoints a customer is allowed to call about their own account. Being
// signed in is the authorisation; there is no capability above it to check.
// They still have to check a nonce and who the caller is, and this asserts
// exactly that rather than waving them through.
$self_service = array(
	'mofoline_member_reissue' => 'a customer replacing their own membership card',
	'mofoline_unlink'         => 'a customer unlinking their own LINE account',
);

foreach ( $registered as $action => $info ) {
	$source = (string) $info['source'];

	if ( '' === $source ) {
		ajax_check( false, $action, 'could not read the callback source' );
		continue;
	}

	if ( isset( $open[ $action ] ) ) {
		// Only that it is still the endpoint we decided was allowed to be open.
		ajax_check( ! empty( $info['public'] ), $action . ' is public on purpose', $open[ $action ] );
		continue;
	}

	if ( isset( $self_service[ $action ] ) ) {
		ajax_check(
			false !== strpos( $source, 'check_ajax_referer' ),
			$action . ' checks a nonce',
			$self_service[ $action ]
		);
		ajax_check(
			false !== strpos( $source, 'get_current_user_id' ),
			$action . ' acts only for the signed-in caller',
			$self_service[ $action ]
		);
		ajax_check( ! $info['public'], $action . ' is not exposed to logged-out callers' );
		continue;
	}

	$has_nonce = false;
	foreach ( $nonce_markers as $marker ) {
		$has_nonce = $has_nonce || false !== strpos( $source, $marker );
	}

	$has_cap = false;
	foreach ( $cap_markers as $marker ) {
		$has_cap = $has_cap || false !== strpos( $source, $marker );
	}

	ajax_check( ! $info['public'], $action . ' is not exposed to logged-out callers' );
	ajax_check( $has_nonce, $action . ' checks a nonce' );
	ajax_check( $has_cap, $action . ' checks a capability' );
}

printf( "\n%d endpoints, %s\n", count( $registered ), 0 === $GLOBALS['ajax_fail'] ? 'AJAX GUARDS OK' : $GLOBALS['ajax_fail'] . ' checks failed' );
