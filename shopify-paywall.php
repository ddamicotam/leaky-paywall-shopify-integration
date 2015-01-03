<?php

/*
  Plugin Name: Shopify Paywall
  Plugin URI: http://maciejbis.net/
  Description: A simple plugin that allows to grab subscriber's data to Paywall system.
  Author: Maciej Bis
  Version: 1.0.0
  Author URI: http://maciejbis.net
 */

add_action( 'shopify_hook', 'shopify' ); 
function shopify($import_all = false) {
	global $wpdb;
	if (!isset($wpdb->issuem_leaky_paywall_subscribers)) {
		$wpdb->issuem_leaky_paywall_subscribers = $wpdb->prefix . 'issuem_leaky_paywall_subscribers';
	}
	// show the orders paid in last 6 hours (UTC -4:00)
	$timezone = 4*60*60 + 6*60*60;
	$limitdate = gmdate("Y-m-d\TH:i", time()-($timezone));
	$imported = '';
	$rows = '';
	$comma = '';
	
	$shop = get_option('shopify_url');
	$key = get_option('shopify_api_key');
	$secret = get_option('shopify_secret');
	$password = get_option('shopify_password');
	$product_ids = get_option('shopify_products');
	$product_ids = explode(',', str_replace(' ', '', $product_ids));
	
	if($key != '' && $secret != '' && $shop != '') {
	
		// parse JSON from Shopify
		if($import_all == false) {
			$json = file_get_contents("https://$key:$password@$shop/admin/orders.json?limit=100&financial_status=paid&updated_at_min=$limitdate");
		} else {
			$json = file_get_contents("https://$key:$password@$shop/admin/orders.json?limit=250&financial_status=paid");
		}
		$json = json_decode($json, true);
		$orders = $json['orders'];
		$count = count($orders);
		
		//get all subscribers' email
		$query = 'SELECT email FROM ' . $wpdb->prefix . 'issuem_leaky_paywall_subscribers';
		$subscribers_array = $wpdb->get_results($query, ARRAY_N);
		foreach ($subscribers_array as $subscriber) {
			$subscribers[] = $subscriber[0];
		}
		if($count > 0) {
			foreach($orders as $order) {
				// the account will expire after one year
				$created = date('Y-m-d H:i:s', strtotime($order['created_at']));
				$expires = date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s", strtotime($created)) . " + 365 day"));
				$email = $order['email'];
				$hash = md5($created);
				$price = $order['total_price'];
				$products = $order['line_items'];
				
				// show only orders with Subscription
				$has_subscription = 0;
				foreach($products as $product) {
					if (in_array($product['variant_id'], $product_ids)) {
						$has_subscription = 1;
					}
				}
				if($has_subscription == 1) {
					// check if email is already in database and if not add it to the database					
					if ( function_exists( 'issuem_leaky_paywall_new_subscriber' ) && (!in_array($email, $subscribers)) ) {			
						$rows .= "{$comma}('{$hash}','{$email}','{$price}','Shopify','{$created}','{$expires}','test','shopify','active')";
						$imported .= '<li>' . $email . ', expires: <em>' . $expires . '</em></li>';
						$comma = ',';
					}
				}
			}
			
			// bulk import to SQL
			$rowheader = "INSERT INTO {$wpdb->issuem_leaky_paywall_subscribers} (hash,email,price,description,created,expires,mode,payment_gateway,payment_status) VALUES";
			
			$wpdb->show_errors();
			if($rows != '') {
				$wpdb->query("{$rowheader} {$rows}");
			} else {
				$imported = '<p>No subscribers were added.</p>';
			}
			
			// show imported orders in admin
			if($import_true == false) {
				echo $imported;
				//print_r($orders);
			}
		}
	}
}

add_shortcode('shopify', 'shopify');

function shopify_paywall_admin_menu() {
		add_menu_page("Shopify/Paywall", "Shopify/Paywall", 'edit_themes', basename(__FILE__), 'shopify_paywall_page');
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
			
			<h3>URL of the shop</h3>
			<input name="shopify_url" id="shopify_url" style="width: 400px;" value="<?php echo get_option('shopify_url'); ?>" />
			
			<h3>Shopify API key</h3>
			<input name="shopify_api_key" id="shopify_api_key" style="width: 400px;" value="<?php echo get_option('shopify_api_key'); ?>" />
			
			<h3>Shopify Secret key</h3>
			<input name="shopify_secret" id="shopify_secret" style="width: 400px;" value="<?php echo get_option('shopify_secret'); ?>" />

			<h3>Shopify Password</h3>
			<input name="shopify_password" id="shopify_password" style="width: 400px;" value="<?php echo get_option('shopify_password'); ?>" />
			
			<h3>Product IDs</h3>
			<input name="shopify_products" id="shopify_products" style="width: 400px;" value="<?php echo get_option('shopify_products'); ?>" />
			
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
	update_option('shopify_secret', 					$_POST['shopify_secret']);
	update_option('shopify_api_key', 					$_POST['shopify_api_key']);
	update_option('shopify_password', 					$_POST['shopify_password']);
	update_option('shopify_products', 					$_POST['shopify_products']);
}

// add new schedule rule
add_filter( 'cron_schedules', 'shopify_paywall_add_schedule' ); 
function shopify_paywall_add_schedule( $schedules ) {
	$schedules['minute'] = array(
		'interval' => 90, // 60 seconds
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