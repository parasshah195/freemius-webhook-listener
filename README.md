# freemius-webhook-listener-wp
A WordPress plugin for receiving and processing Freemius Webhook events to subscribe/unsubscribe users to Sendfox. Derived from https://github.com/Freemius/php-webhook-example

This is specifically built to integrate with Sendfox's API structure. If you want to use it with any other email marketing service provide, you'll have to modify the `api.php` page's format/functions to suite the API's needs.

## Usage

A JSON schema consisting of events and actions needs to be defined which then connects the request URL and events received from Freemius Webhook with the methods and functionality to process using the plugin.

### Freemius Dashboard

Add the webhook URL as `{domain}/?freemius_webhook=sendfox&name={product name}` where `domain` is the URL of the site where you install the plugin and `product name` is the name of your product as defined in the JSON schema. You can also replace and customize the value of the `freemius_webhook` parameter to whatever you like but make sure to change that in `api.php:43` too.

### Settings page

There's a settings page in `Settings > Freemius Webhook Listener` (in the WP menu) where you can define the JSON schema. Both actions and events have many-to-many relation implementation, meaning the schema can define 1/many events to execute 1/many actions.

**JSON Schema outline:**

```json
{
	"{{product_name}}": {
		"events": [
			{
				"event": "{{freemius event name(s) separated by comma}}",
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
	},
	"{{another_product_name}}": {
		"events": [
			{
				"event": "{{freemius event name(s) separated by comma}}",
				"actions": {
					"sub": "{{list_id(s) separated by comma}}",
					"del": "{{list_id(s) separated by comma}}"
				}
			}
		]
	}
}
```

## Note

This plugin does not re-query the Freemius endpoint to fetch the data from there (which is a recommended practice) but instead uses other mechanisms (User Agent, query params, event names, product name defined in JSON Schema) to verify the authenticity of the received payload. This is primarily to avoid round trip queries and having to include Freemius' PHP SDK.

Multi-site compatibility has not been tested.

From the above schema example, the keys listed in actions are the names of the functions to execute defined in `api.php`, so if you change the function names there, make sure to use appropriate action names.
