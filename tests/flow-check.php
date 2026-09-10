<?php
/**
 * Drive a conversation flow from its trigger to its stored submission.
 *
 * Sends nothing: every step is exercised through the flow engine directly, so
 * it costs no message quota. It creates a flow and a session and deletes both
 * again, so run it against a throwaway site.
 *
 *   wp eval-file tests/flow-check.php
 *
 * This exists because every answer a customer gave was being read as a
 * validation failure -- validate() returned the cleaned value as a string and
 * the caller treated any string as an error -- so no flow could get past its
 * first question, and nothing in the admin showed it.
 *
 * @package Moksa\Line
 */

use Moksa\Line\Bot\Flow;

if ( 'production' === wp_get_environment_type() && ! defined( 'MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS' ) ) {
	echo "REFUSED: this site reports WP_ENVIRONMENT_TYPE=production.
";
	echo "It creates and deletes a conversation flow and its submissions.
";
	echo "If this really is a throwaway site, set WP_ENVIRONMENT_TYPE, or define
";
	echo "MOKSA_LINE_ALLOW_DESTRUCTIVE_TESTS in wp-config.php, and run it again.
";
	return;
}

$user = 'Uflowtest' . wp_generate_password( 8, false, false );
$GLOBALS['moksa_pass'] = 0;
$GLOBALS['moksa_fail'] = 0;

function check( $ok, $label, $detail = '' ) {
	global $moksa_pass, $moksa_fail;

	if ( $ok ) {
		++$moksa_pass;
		echo "  ok    $label\n";
	} else {
		++$moksa_fail;
		echo "  FAIL  $label" . ( '' !== $detail ? "  --  $detail" : '' ) . "\n";
	}
}

$definition = array(
	'steps' => array(
		array( 'prompt' => '請問怎麼稱呼？', 'type' => 'text', 'key' => 'name' ),
		array( 'prompt' => '方便聯絡的電話號碼是？', 'type' => 'phone', 'key' => 'phone' ),
		array( 'prompt' => '想預約哪一天？', 'type' => 'date', 'key' => 'date' ),
		array( 'prompt' => '想預約哪個服務？', 'type' => 'choice', 'key' => 'service', 'choices' => array( '諮詢', '回診' ) ),
	),
	'complete_message' => '謝謝您 {name}，已為您記下 {date} 的 {service}。',
);

$flow_id = Flow::save(
	array(
		'name'          => '流程測試',
		'trigger_type'  => 'keyword',
		'trigger_value' => '預約測試',
		'definition'    => wp_json_encode( $definition, JSON_UNESCAPED_UNICODE ),
		'is_active'     => 1,
	)
);

echo "flow id: $flow_id\n\n";

// --- trigger matching -----------------------------------------------------
echo "Trigger\n";
$matched = Flow::match_trigger( '預約測試' );
check( $matched && (int) $matched->id === (int) $flow_id, 'the trigger phrase starts this flow' );
check( null === Flow::match_trigger( '完全不相干的訊息' ), 'an unrelated message does not' );

// --- the first question ---------------------------------------------------
echo "\nAsking\n";
$first = Flow::start( (int) $flow_id, $user );
check( is_array( $first ) && ! empty( $first ), 'starting the flow returns the first question' );
check(
	isset( $first[0]['text'] ) && false !== strpos( $first[0]['text'], '怎麼稱呼' ),
	'the first question is the one that was written',
	isset( $first[0]['text'] ) ? $first[0]['text'] : wp_json_encode( $first )
);

$session = Flow::session( $user );
check( $session && 0 === (int) $session->step_index, 'the session starts at the first step' );

// --- answering ------------------------------------------------------------
echo "\nAnswering\n";
$answers = array( '小明', '0912345678', '2026-03-15', '諮詢' );

foreach ( $answers as $i => $answer ) {
	$session = Flow::session( $user );

	if ( ! $session ) {
		check( false, "step $i still has a live session" );
		break;
	}

	$reply = Flow::advance( $session, $answer, $user );
	$last  = $i === count( $answers ) - 1;

	if ( ! $last ) {
		$next = Flow::session( $user );
		check(
			$next && (int) $next->step_index === $i + 1,
			sprintf( 'answering question %d moves to question %d', $i + 1, $i + 2 ),
			$next ? 'at index ' . $next->step_index : 'session gone'
		);
	}

	if ( $last ) {
		check( is_array( $reply ) && ! empty( $reply ), 'the last answer produces a closing message' );
		$text = isset( $reply[0]['text'] ) ? $reply[0]['text'] : '';
		check( false !== strpos( $text, '小明' ), 'the closing message fills in an answer', $text );
		check( false !== strpos( $text, '2026-03-15' ), 'and the other answers too', $text );
		check( false === strpos( $text, '{' ), 'no placeholder is left showing', $text );
		check( null === Flow::session( $user ), 'the session is closed when the flow ends' );
	}
}

// --- what was stored ------------------------------------------------------
echo "\nStored\n";
$subs = Flow::submissions( (int) $flow_id, 5 );
check( ! empty( $subs ), 'the submission was recorded' );

if ( ! empty( $subs ) ) {
	$stored = json_decode( (string) $subs[0]->answers, true );
	check( is_array( $stored ), 'the answers are readable JSON' );
	check( isset( $stored['name'] ) && '小明' === $stored['name'], 'name stored under its own key', wp_json_encode( $stored, JSON_UNESCAPED_UNICODE ) );
	check( isset( $stored['phone'] ) && '0912345678' === $stored['phone'], 'phone stored under its own key' );
	check( isset( $stored['date'] ) && '2026-03-15' === $stored['date'], 'date stored under its own key' );
	check( isset( $stored['service'] ) && '諮詢' === $stored['service'], 'choice stored under its own key' );
}

// --- refusing bad answers -------------------------------------------------
echo "\nValidation\n";
Flow::start( (int) $flow_id, $user );
$s = Flow::session( $user );
Flow::advance( $s, '小華', $user );          // name
$s = Flow::session( $user );
$bad = Flow::advance( $s, '這不是電話', $user ); // phone
$after = Flow::session( $user );
check(
	$after && 1 === (int) $after->step_index,
	'a phone step refuses text that is not a phone number and stays put',
	$after ? 'moved to ' . $after->step_index : 'session gone'
);
check( is_array( $bad ) && ! empty( $bad ), 'and says something rather than going silent' );

// --- cancelling -----------------------------------------------------------
echo "\nCancelling\n";
$s = Flow::session( $user );
$cancel = Flow::advance( $s, '取消', $user );
check( null === Flow::session( $user ), 'the documented cancel word ends the session' );
check( is_array( $cancel ) && ! empty( $cancel ), 'and confirms it was cancelled' );

// --- clean up -------------------------------------------------------------
Flow::end( $user );
Flow::delete( (int) $flow_id );

global $wpdb;
$wpdb->delete( $wpdb->prefix . 'moksa_line_flow_submissions', array( 'flow_id' => (int) $flow_id ) );

$passed = (int) $GLOBALS['moksa_pass'];
$failed = (int) $GLOBALS['moksa_fail'];

echo "\n{$passed} passed, {$failed} failed\n";

if ( $failed > 0 ) {
	// So a build can tell, rather than the count scrolling past.
	WP_CLI::halt( 1 );
}
