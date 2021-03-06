<?php

/*
  Plugin Name: Leaky Paywall - Shopify Integration
  Plugin URI: https://github.com/donbui/leaky-paywall-shopify-integration/tree/update
  Description: Sell Leaky Paywall WordPress subscriptions on Shopify
  Author: Maciej Bis, updated by Don Bui
  Version: 1.0.0
 */

add_action( 'shopify_hook', 'shopify' );
function shopify($import_all = false) {

	// error_log('LPW: shopify integration ran!');

	$shop = get_option('shopify_url');
	$key = get_option('shopify_api_key');
	$password = get_option('shopify_password');
	$product_ids = get_option('shopify_products');
	$product_ids = explode(',', str_replace(' ', '', $product_ids));
	$subscription_period = get_option('shopify_subscription_period');
	$subscription_period = explode(',', str_replace(' ', '', $subscription_period));
	$level_id = get_option('shopify_level_id');
	$level_id = explode(',', str_replace(' ', '', $level_id));

	if($key != '' && $shop != '') {

		$limitdate = date('o-m-d\TH:i:s', time() - 2 * 60); // get orders in the last 2 minutes
		$shopify_url = "https://$key:$password@$shop/admin/orders.json?limit=100&financial_status=paid&updated_at_min=$limitdate";
		if($import_all === true) {
			// unless we are grabbing everything, in which case get the last 250 orders
			$shopify_url = "https://$key:$password@$shop/admin/orders.json?limit=250&financial_status=paid";
		}
		$json = file_get_contents($shopify_url);
		// error_log('LPW: '.$json);

		// stop if the JSON string is empty.
		if(empty($json)) die();

		$json = json_decode($json, true);
		$orders = $json['orders'];
		$count = count($orders);

		if($count > 0) {
			foreach($orders as $order) {
				$created = date('Y-m-d H:i:s', strtotime($order['created_at']));
				$email = $order['email'];
				$price = $order['total_price'];
				$products = $order['line_items'];
				$customer_id = $order['customer']['id'];

				foreach($products as $product) {
					if (in_array($product['variant_id'], $product_ids)) {
						$index = array_search($product['variant_id'], $product_ids); // get index of received variant-ID in user-defined list of IDs
						$expires = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s', strtotime($created)) . " + " . $subscription_period[$index] . " day")); // add subscription period to order date
						
						$meta = array(
							'level_id' 			=> $level_id[$index],
							'subscriber_id'		=> strval($customer_id),
							'price' 			=> $product['price'],
							// 'description' 		=> __( 'Manual Addition', 'issuem-leaky-paywall' ),
							'expires' 			=> $expires,
							'payment_gateway' 	=> 'manual',// @TODO add an actual shopify gateway, rn we just use manual to go with the flow in lp
							'payment_status' 	=> 'active',
							'interval' 			=> 365,
							'plan'				=> ''
						);
						
						// error_log('LPW: here\'s the meta info I\'m going to pass to new subscriber maker');
						// error_log('LPW: '.implode(' | ', $meta));
						
						$user_id = leaky_paywall_new_subscriber( NULL, $email, $customer_id, $meta, NULL );
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
	add_submenu_page( 'tools.php', 'Leaky Paywall - Shopify Integration', 'Leaky Paywall - Shopify Integration', 'edit_themes', basename(__FILE__), 'shopify_paywall_page');
}
add_action('admin_menu', 'shopify_paywall_admin_menu');

function shopify_paywall_page() {
	if ( $_POST['update_themeoptions'] == 'true' ) { shopify_paywall_page_update(); }	?>
	<div class="wrap">
		<div id="icon-themes" class="icon32"><br /></div>
		<h2>Shopify settings</h2>

		<form method="POST" action="">

			<input type="hidden" name="update_themeoptions" value="true" />

			<p>Shopify API Key <em>(required)</em></p>
			<input name="shopify_api_key" id="shopify_api_key" style="width: 400px;" value="<?php echo get_option('shopify_api_key'); ?>" />

			<p>Shopify Password <em>(required)</em></p>
			<input name="shopify_password" id="shopify_password" style="width: 400px;" value="<?php echo get_option('shopify_password'); ?>" />

			<p>Shopify Store URL <em>(eg. yourstore.myshopify.com, required)</em></p>
			<input name="shopify_url" id="shopify_url" style="width: 400px;" value="<?php echo get_option('shopify_url'); ?>" />

			<p>Variant IDs <em>(comma separated, required)</em></p>
			<input name="shopify_products" id="shopify_products" style="width: 400px;" value="<?php echo get_option('shopify_products'); ?>" />

			<p>Subscription period in days <em>(comma separated, in same sequence as Variant ID)</em></p>
			<input name="shopify_subscription_period" id="shopify_subscription_period" style="width: 400px;" value="<?php echo get_option('shopify_subscription_period'); ?>" />

			<p>Level ID <em>(comma separated, in same sequence as Variant ID)</em></p>
			<input name="shopify_level_id" id="shopify_level_id" style="width: 400px;" value="<?php echo get_option('shopify_level_id'); ?>" />

			<p>Import last 250 orders (If unchecked, import orders from last 2 minutes)</p>
			<input type="checkbox" name="shopify_import" id="shopify_import" value="1" />

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
	update_option('shopify_api_key', 					$_POST['shopify_api_key']);
	update_option('shopify_password', 					$_POST['shopify_password']);
	update_option('shopify_products', 					$_POST['shopify_products']);
	update_option('shopify_level_id', 					$_POST['shopify_level_id']);
}

// add new schedule rule
add_filter( 'cron_schedules', 'shopify_paywall_add_schedule' );
function shopify_paywall_add_schedule( $schedules ) {
	$schedules['minute'] = array(
		'interval' => 120,
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

// @TODO implement leaky_paywall_subscriber_payment_gateways to support shopify as an actual payment gateway
// @TODO implement leaky_paywall_has_user_paid to add validation for shopify gateway

?>