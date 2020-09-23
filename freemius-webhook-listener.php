<?php
/**
 * Plugin Name:  Freemius Webhook Listener
 * Description:  WebHook listener for subscribing Freemius users to Sendfox.
 * Author:       Paras Shah
 * Contributors: parasshah99
 * Version:      1.0
 * Author URI:   https://pixify.net
 *
 * @package freemius-webhook-listener
 *
 * phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
 */

require_once plugin_dir_path( __FILE__ ) . '/api.php';

add_action( 'admin_menu', 'fwl_add_settings_page' );
function fwl_add_settings_page() {
	add_options_page( 'Freemius Webhook Listener', 'Freemius Webhook Listener', 'manage_options', 'freemius-webhook-listener', 'fwl_render_plugin_settings_page' );
}

function fwl_render_plugin_settings_page() {
	?>
	<style>
		#freemius_webhook_listener_schema {
			height: 300px;
		}
	</style>
	<h2>Freemius Webhook Listener Settings</h2>
	<form action="options.php" method="post">
		<?php
		settings_fields( 'freemius_webhook_listener_options' );
		do_settings_sections( 'freemius_webhook_listener' );
		submit_button();
		?>
	</form>
	<h4>Sample Schema</h4>
	<pre>
	{
		"{{product_name}}": {
			"events": [
				{
					"event": "{{event name(s) separated by comma}}",
					"actions": {
						"sub": "{{list_id(s) separated by comma}}",
						"del": "{{list_id(s) separated by comma}}"
					}
				},
				{
					"event": "{{event name(s) separated by comma}}",
					"actions": {
						"unsub": "true"
					}
				}
			]
		}
	}
	</pre>
	<script src="https://ajaxorg.github.io/ace-builds/src-min-noconflict/ace.js" defer></script>
	<script src="https://ajaxorg.github.io/ace-builds/src-min-noconflict/mode-json.js" defer></script>
	<script>
		window.addEventListener('DOMContentLoaded', function() {
			var editor = ace.edit("freemius_webhook_listener_schema");
			editor.session.setMode("ace/mode/json");
			var textarea = jQuery('textarea[name="freemius_webhook_listener_options[webhook_schema]"]').hide();
			editor.session.setValue(textarea.val());
			editor.session.on('change', function() {
				textarea.val(editor.session.getValue());
			});
		});
	</script>
	<?php
}

add_action( 'admin_init', 'fwl_register_settings' );
function fwl_register_settings() {
	register_setting( 'freemius_webhook_listener_options', 'freemius_webhook_listener_options', [ 'sanitize_callback' => 'freemius_webhook_listener_options_validate' ] );

	add_settings_section( 'webhook_settings', '', 'freemius_webhook_listener_section_text', 'freemius_webhook_listener' );
	add_settings_field( 'freemius_webhook_listener_schema', 'Webhook Schema', 'freemius_webhook_listener_schema', 'freemius_webhook_listener', 'webhook_settings' );
}

/**
 * Validate JSON Input
 *
 * @param string $input Input.
 * @return string Sanitized input value
 */
function freemius_webhook_listener_options_validate( $input ) {

	$newinput['webhook_schema'] = trim( $input['webhook_schema'] );

	$decoded = json_decode( $newinput['webhook_schema'] );

	if ( null === $decoded ) {
		add_settings_error( 'freemius_webhook_listener_schema', 'settings-updated', 'Invalid JSON' . fwl_get_json_error() );
		return get_option( 'freemius_webhook_listener_options' ); // Return old value.
	}

	return $newinput;
}

function freemius_webhook_listener_section_text() {
	echo '<p>Add JSON to map user data and events to actions.</p>';
}

function freemius_webhook_listener_schema() {
	$options = get_option( 'freemius_webhook_listener_options' );
	$schema = ( isset( $options['webhook_schema'] ) ) ? $options['webhook_schema'] : '{&#013;}';
	echo '<textarea name="freemius_webhook_listener_options[webhook_schema]" rows="20" cols="100">' . esc_attr( $schema ) . '</textarea>';
	echo '<div id="freemius_webhook_listener_schema"></div>';
}

function fwl_get_json_error() {
	switch ( json_last_error() ) {
		case JSON_ERROR_NONE:
			return ' - No errors';
		case JSON_ERROR_DEPTH:
			return ' - Maximum stack depth exceeded';
		case JSON_ERROR_STATE_MISMATCH:
			return ' - Underflow or the modes mismatch';
		case JSON_ERROR_CTRL_CHAR:
			return ' - Unexpected control character found';
		case JSON_ERROR_SYNTAX:
			return ' - Syntax error, malformed JSON';
		case JSON_ERROR_UTF8:
			return ' - Malformed UTF-8 characters, possibly incorrectly encoded';
		default:
			return ' - Unknown error';
	}
}
