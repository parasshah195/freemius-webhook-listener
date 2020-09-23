<?php
/**
 * The API Handler
 *
 * @package freemius-webhook-listener
 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_dump
 */

/**
 * API handler class
 */
class FWL_Sendfox_API {


	/**
	 * Sendfox API auth token.
	 *
	 * @var string
	 */
	private $auth_token = '{{Sendfox authentication token}}';

	/**
	 * Product Name
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Listens to Freemius WebHooks using a specific query string param 'fwebhook' which informs on the service to use.
	 */
	public function __construct() {
		if ( empty( $_SERVER['QUERY_STRING'] ) ) {
			return;
		}

		parse_str( $_SERVER['QUERY_STRING'], $query_vars );

		if ( ! isset( $query_vars['freemius_webhook'] ) ) {
			return;
		}

		if ( 'sendfox' !== $query_vars['freemius_webhook'] ) {
			$this->log( 'Freemius Webhook - Invalid Destination' );
			http_response_code( 403 );
			exit;
		}

		if ( ! isset( $query_vars['name'] ) ) {
			$this->log( 'Freemius Webhook - Product Name Required' );
			http_response_code( 403 );
			exit;
		}

		$this->name = $query_vars['name'];

		$this->process_request();

		http_response_code( 200 );
		exit;

	}

	/**
	 * Processes the Webhook request
	 *
	 * @return void
	 */
	public function process_request() {

		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) || false === strpos( $_SERVER['HTTP_USER_AGENT'], 'Freemius/1.0' ) ) {
			$this->log( 'Freemius Webhook - Invalid Request Source' );
			http_response_code( 403 );
			exit;
		}

		// Retrieve the request's body and parse it as JSON.
		$post_query = json_decode( @file_get_contents( 'php://input' ) );

		if ( ! isset( $post_query->id ) ) {
			$this->log( 'Freemius Webhook - ID not set' );
			http_response_code( 200 );
			exit;
		}

		$user = $post_query->objects->user;

		if ( ! $user ) {
			return;
		}

		$email = $user->email;
		$first = $user->first;
		$last  = $user->last;

		$data = [
			'email'      => $email,
			'first_name' => $first,
			'last_name'  => $last,
		];

		$schema_option = get_option( 'freemius_webhook_listener_options' );
		$schema = json_decode( $schema_option['webhook_schema'] );

		// No rules defined for the mentioned product. Bail.
		if ( ! isset( $schema->{ $this->name } ) ) {
			$this->log( "Freemius Webhook - Product {$this->name} events not defined" );
			http_response_code( 200 );
			exit;
		}

		$product = $schema->{ $this->name };

		try {

			foreach ( $product->events as $event ) {

				$events = explode( ',', $event->event );

				if ( in_array( $post_query->type, $events ) ) {

					foreach ( $event->actions as $action => $list_ids ) {

						try {
							// Call the function.
							$this->{$action}( $data, explode( ',', $list_ids ) );
						} catch ( \Throwable $th ) {
							var_dump( $th );
							$this->log( "Freemius Webhook - Error - {$th}" );
						}
					}
				}
			}

			print_r( 'Query successful' );

		} catch ( \Throwable $th ) {

			var_dump( $th );
			$this->log( "Freemius Webhook - Error - {$th}" );

		}

	}

	/**
	 * Subscribe a user to a sendfox lists
	 *
	 * @param array $data User data consisting of email and name.
	 * @param array $list_ids List IDs to subscribe to.
	 *
	 * @return void
	 */
	public function sub( $data, $list_ids ) {

		$data['lists'] = $list_ids;

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => "Bearer {$this->auth_token}",
			),
			'body'    => wp_json_encode( $data ),
		);

		$url = 'https://api.sendfox.com/contacts';

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( $response );
			return;
		}

	}

	/**
	 * Function to delete contact from a list.
	 *
	 * @param array $data The user data.
	 * @param array $list_ids List IDs to process.
	 *
	 * @return void
	 */
	public function del( $data, $list_ids ) {

		$email = $data['email'];

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => "Bearer {$this->auth_token}",
			),
		);

		$url = "https://api.sendfox.com/contacts?email={$email}";

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( $response );
			return;
		}

		$response_body = json_decode( $response['body'] );

		if ( ! isset( $response_body->data[0]->id ) ) {
			$this->log( 'Freemius Webhook - Delete - contact ID is not defined' );
			return;
		}

		$contact_id = $response_body->data[0]->id;

		// Delete.
		foreach ( $list_ids as $list_id ) {

			$delete_response = wp_remote_request( "https://api.sendfox.com/lists/{$list_id}/contacts/{$contact_id}", array(
				'method'  => 'DELETE',
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => "Bearer {$this->auth_token}",
				),
			));

			if ( is_wp_error( $delete_response ) ) {
				$this->log( $delete_response );
				return;
			}
		}

	}

	/**
	 * Unsubscribe the user from Sendfox
	 *
	 * @param array $data The user data.
	 * @param array $list_ids List IDs to process.
	 *
	 * @return void
	 */
	public function unsub( $data, $list_ids ) {

		$body = [
			'email' => $data['email'],
		];

		$unsub_response = wp_remote_request( 'https://api.sendfox.com/unsubscribe', array(
			'method'  => 'PATCH',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => "Bearer {$this->auth_token}",
			),
			'body'    => wp_json_encode( $body ),
		));

		if ( is_wp_error( $unsub_response ) ) {
			$this->log( $unsub_response );
			return;
		}

	}

	/**
	 * Log any errors.
	 *
	 * @param string $log The log message.
	 */
	public function log( $log ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}
}

new FWL_Sendfox_API();
