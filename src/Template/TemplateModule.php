<?php
/**
 * The template message screen.
 *
 * @package Mofoline
 */

namespace Mofoline\Template;

use Mofoline\Api\Client;
use Mofoline\Api\MessagingClient;
use Mofoline\Api\TokenManager;
use Mofoline\Data\Templates;
use Mofoline\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

class TemplateModule {

	public function register(): void {
		add_action( 'wp_ajax_mofoline_template_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_mofoline_template_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_mofoline_template_validate', array( $this, 'ajax_validate' ) );
		add_action( 'wp_ajax_mofoline_template_send_test', array( $this, 'ajax_send_test' ) );
		add_action( 'wp_ajax_mofoline_template_warnings', array( $this, 'ajax_warnings' ) );
	}

	/**
	 * The same problems save would refuse, reported while the shop is typing.
	 *
	 * Deliberately a success response whatever it finds: this is advice, not a
	 * rejection, and the editor should not treat a half-finished template as an
	 * error state.
	 */
	public function ajax_warnings(): void {
		$this->guard();

		$decoded = Ajax::json_verbatim( 'definition' );

		wp_send_json_success(
			array( 'problems' => is_array( $decoded ) ? TemplateMessages::check( $decoded ) : array() )
		);
	}

	/**
	 * Save a template, refusing anything LINE would not accept.
	 */
	public function ajax_save(): void {
		$this->guard();

		$id       = Ajax::int( 'id' );
		$name     = Ajax::text( 'name' );
		$alt_text = Ajax::text( 'alt_text' );
		$decoded  = $this->decoded_definition();

		if ( '' === trim( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Give this template a name.', 'moksa-for-line' ) ) );
		}

		if ( '' === trim( $alt_text ) ) {
			wp_send_json_error( array( 'message' => __( 'Fallback text is required: it is all the customer sees in the chat list.', 'moksa-for-line' ) ) );
		}

		$problems = TemplateMessages::check( $decoded );

		if ( $problems ) {
			wp_send_json_error(
				array(
					'message'  => __( 'This template will not send as it stands.', 'moksa-for-line' ),
					'problems' => $problems,
				)
			);
		}

		$fields = array(
			'name'       => $name,
			'alt_text'   => $alt_text,
			'kind'       => isset( $decoded['type'] ) ? (string) $decoded['type'] : 'buttons',
			'definition' => (string) wp_json_encode( $decoded ),
		);

		if ( $id > 0 ) {
			$fields['id'] = $id;
		}

		$saved = Templates::save( $fields );

		if ( $saved <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'The template could not be saved.', 'moksa-for-line' ) ) );
		}

		wp_send_json_success(
			array(
				'id'      => $saved,
				'message' => __( 'Template saved.', 'moksa-for-line' ),
			)
		);
	}

	/**
	 * Check a template, locally and then against LINE.
	 *
	 * LINE's own validator costs nothing and sends nothing, so once the channel
	 * is connected there is no reason to guess.
	 */
	public function ajax_validate(): void {
		$this->guard();

		$decoded  = $this->decoded_definition();
		$alt_text = Ajax::text( 'alt_text', 'Preview' );
		$problems = TemplateMessages::check( $decoded );

		if ( $problems ) {
			wp_send_json_error(
				array(
					'message'  => __( 'Found problems before contacting LINE.', 'moksa-for-line' ),
					'problems' => $problems,
					'source'   => 'local',
				)
			);
		}

		if ( ! TokenManager::is_configured() ) {
			wp_send_json_success(
				array(
					'message' => __( 'Passed the local checks. Connect the Messaging API channel to also validate against LINE.', 'moksa-for-line' ),
					'source'  => 'local',
				)
			);
		}

		$result = Client::request(
			'POST',
			'/message/validate/push',
			array( 'messages' => array( TemplateMessages::message( $alt_text, $decoded ) ) )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'LINE rejected this template.', 'moksa-for-line' ),
					'problems' => array( $result->get_error_message() ),
					'source'   => 'line',
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'LINE accepted this template.', 'moksa-for-line' ),
				'source'  => 'line',
			)
		);
	}

	/**
	 * Push one template to a single LINE user.
	 */
	public function ajax_send_test(): void {
		$this->guard();

		$id     = Ajax::int( 'id' );
		$target = Ajax::text( 'line_user_id' );

		if ( '' === $target ) {
			wp_send_json_error( array( 'message' => __( 'Enter the LINE user id to send the test to.', 'moksa-for-line' ) ) );
		}

		$row        = Templates::find( $id );
		$definition = Templates::definition( $id );

		if ( ! $row || ! $definition ) {
			wp_send_json_error( array( 'message' => __( 'That template no longer exists.', 'moksa-for-line' ) ) );
		}

		$result = MessagingClient::push(
			$target,
			array( TemplateMessages::message( (string) $row->alt_text, $definition ) )
		);

		Ajax::bail( $result, 'Could not send a template test', 'template' );

		wp_send_json_success( array( 'message' => __( 'Sent. Check the chat on your phone.', 'moksa-for-line' ) ) );
	}

	/**
	 * Delete a template.
	 */
	public function ajax_delete(): void {
		$this->guard();

		$id = Ajax::int( 'id' );

		// Reporting "deleted" for a row that was not there tells somebody
		// looking at a stale list that they fixed something. The Flex handler
		// next door already got this right; these two did not.
		if ( $id <= 0 || ! Templates::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That template no longer exists.', 'moksa-for-line' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Template deleted.', 'moksa-for-line' ) ) );
	}

	/**
	 * The posted template object.
	 *
	 * @return array
	 */
	private function decoded_definition(): array {
		$decoded = Ajax::json_verbatim( 'definition' );

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'That is not valid JSON.', 'moksa-for-line' ) ) );
		}

		return $decoded;
	}

	private function guard(): void {
		Ajax::guard( __( 'You do not have permission to manage templates.', 'moksa-for-line' ) );
	}
}
