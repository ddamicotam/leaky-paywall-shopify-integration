<?php

/*
  Plugin Name: Shopify->Leaky Paywall Integration Wordpress Plugin
  Plugin URI: https://github.com/donbui/Shopify-Leaky-Paywall-Integration-Wordpress-Plugin
  Description: Sell Leaky Paywall WordPress subscriptions on Shopify
  Author: Don Bui, Original by Maciej Bis
  Version: 1.0.0
  Author URI: http://maciejbis.net
 */

add_action( 'shopify_hook', 'shopify' );
function shopify($import_all = false) {

	$shop = get_option('shopify_url');
	$key = get_option('shopify_api_key');
	$secret = get_option('shopify_secret');
	$password = get_option('shopify_password');
	$product_ids = get_option('shopify_products');
	$product_ids = explode(',', str_replace(' ', '', $product_ids));
	$subscription_period = get_option('shopify_subscription_period');
	$subscription_period = explode(',', str_replace(' ', '', $subscription_period));
	$level_id = get_option('shopify_level_id');
	$level_id = explode(',', str_replace(' ', '', $level_id));

	if($key != '' && $secret != '' && $shop != '') {

		// get orders in the last 2 minutes
		$date_min = date('o-m-d\TH:i:s', time() - 2 * 60);
		$json = file_get_contents("https://$key:$password@$shop/admin/orders.json?limit=250&financial_status=paid&created_at_min=$date_min");

		// stop if the JSON string is empty.
		if(empty($json)) die();

		$json = json_decode($json, true);
		$orders = $json['orders'];
		$count = count($orders);

		if($count > 0) {
			foreach($orders as $order) {
				$created = date('Y-m-d H:i:s', strtotime($order['created_at']));
				$email = $order['email'];
				$hash = md5($created);
				$price = $order['total_price'];
				$products = $order['line_items'];

				foreach($products as $product) {
					if (in_array($product['variant_id'], $product_ids)) {
						$index = array_search($product['variant_id'], $product_ids);
						$expires = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s', strtotime($created)) . " + " . $subscription_period[$index] . " day"));
						$meta = array(
							'level_id' 			=> $level_id[$index],
							// 'subscriber_id'		=> $subscriber_id,
							// 'price' 			=> trim( $_POST['leaky-paywall-subscriber-price'] ),
							'description' 		=> __( 'Manual Addition', 'issuem-leaky-paywall' ),
							'expires' 			=> $expires,
							'payment_gateway' 	=> 'Shopify',
							'payment_status' 	=> 'active',
							'interval' 			=> 0,
							'plan'				=> '',
						);

						// $user_id = leaky_paywall_new_subscriber( NULL, $email, $subscriber_id, $meta, $login );
						$user_id = leaky_paywall_new_subscriber( NULL, $email, NULL, $meta, NULL );
						do_action( 'add_leaky_paywall_subscriber', $user_id );
						echo $email;
					}
				}
			}
			}
		}
}

add_shortcode('shopify', 'shopify');

function shopify_paywall_admin_menu() {
	add_submenu_page( 'tools.php', 'Shopify/Paywall', 'Shopify/Paywall', 'edit_themes', basename(__FILE__), 'shopify_paywall_page');
}

add_action('admin_menu', 'shopify_paywall_admin_menu');

function shopify_paywall_page() {
	if ( $_POST['update_themeoptions'] == 'true' ) { shopify_paywall_page_update(); }	?>
	<div class="wrap">
		<div id="icon-themes" class="icon32"><br /></div>
		<h2>Shopify settings</h2>

		<form method="POST" action="">

			<input type="hidden" name="update_themeoptions" value="true" />

			<h3>Import recent subscribers from Shopify (max. 250 last orders)</h3>
			<input type="checkbox" name="shopify_import" id="shopify_import" value="1" />

			<h3>Shopify API key <em>(required)</em></h3>
			<input name="shopify_api_key" id="shopify_api_key" style="width: 400px;" value="<?php echo get_option('shopify_api_key'); ?>" />

			<h3>Shopify Password <em>(required)</em></h3>
			<input name="shopify_password" id="shopify_password" style="width: 400px;" value="<?php echo get_option('shopify_password'); ?>" />

			<h3>Shopify Secret key <em>(required)</em></h3>
			<input name="shopify_secret" id="shopify_secret" style="width: 400px;" value="<?php echo get_option('shopify_secret'); ?>" />

			<h3>URL of the shop <em>(eg. yourstore.myshopify.com, required)</em></h3>
			<input name="shopify_url" id="shopify_url" style="width: 400px;" value="<?php echo get_option('shopify_url'); ?>" />

			<h3>Variant IDs <em>(comma separated, required)</em></h3>
			<input name="shopify_products" id="shopify_products" style="width: 400px;" value="<?php echo get_option('shopify_products'); ?>" />

			<h3>Subscription period in days <em>(comma separated, in same sequence as Variant ID)</em></h3>
			<input name="shopify_subscription_period" id="shopify_subscription_period" style="width: 400px;" value="<?php echo get_option('shopify_subscription_period'); ?>" />

			<h3>Level ID <em>(comma separated, in same sequence as Variant ID)</em></h3>
			<input name="shopify_level_id" id="shopify_level_id" style="width: 400px;" value="<?php echo get_option('shopify_level_id'); ?>" />

			<p style="clear: both; padding-top: 20px;">
			<input type="submit" name="search" value="Update Options" class="button" />
			</p>

		</form>

		<?php if ( $_POST['shopify_import'] == '1' ) {
			echo '<h2>Emails imported:</h2><ul>';
				shopify(true);
			echo '</ul>';
			}
		?>

	</div>
<?php
}

function shopify_paywall_page_update()	{
	// this is where validation would go
	update_option('shopify_url', 						$_POST['shopify_url']);
	update_option('shopify_subscription_period', 		$_POST['shopify_subscription_period']);
	update_option('shopify_secret', 					$_POST['shopify_secret']);
	update_option('shopify_api_key', 					$_POST['shopify_api_key']);
	update_option('shopify_password', 					$_POST['shopify_password']);
	update_option('shopify_products', 					$_POST['shopify_products']);
	update_option('shopify_level_id', 					$_POST['shopify_level_id']);
}

// add new schedule rule
add_filter( 'cron_schedules', 'shopify_paywall_add_schedule' );
function shopify_paywall_add_schedule( $schedules ) {
	$schedules['minute'] = array(
		'interval' => 60, // 60 seconds
		'display' => 'Every minute'
	);
	return $schedules;
}

// Function which will register the event
add_action( 'init', 'register_sync_event');
function register_sync_event() {
	//wp_clear_scheduled_hook( 'shopify_hook' );
	// Make sure this event hasn't been scheduled
	if( !wp_next_scheduled( 'shopify_hook' ) ) {
		// Schedule the event
		wp_schedule_event( time(), 'minute', 'shopify_hook' );
	}
}

?>