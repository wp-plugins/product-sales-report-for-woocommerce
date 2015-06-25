<?php
/**
 * Plugin Name: Product Sales Report for WooCommerce
 * Description: Generates a report on individual WooCommerce products sold during a specified time period.
 * Version: 1.0
 * Author: Hearken Media
 * Author URI: http://hearkenmedia.com/landing-wp-plugin.php?utm_source=product-sales-report&utm_medium=link&utm_campaign=wp-widget-link
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 */

// Add the Product Sales Report to the WordPress admin
add_action('admin_menu', function() {
	add_submenu_page('woocommerce', 'Product Sales Report', 'Product Sales Report', 'view_woocommerce_reports', 'hm_sbp', 'hm_sbp_page');
});

// This function generates the Product Sales Report page HTML
function hm_sbp_page() {
	
	// Print header
	echo('
		<div class="wrap">
			<h2>Product Sales Report</h2>
	');
	
	// Check for WooCommerce
	if (!class_exists('WooCommerce')) {
		echo('
			This plugin requires that WooCommerce is installed and activated.
		</div>');
		return;
	}
	
	// Print form
	echo('
			<form action="" method="post">
				<input type="hidden" name="hm_sbp_do_export" value="1" />
		');
	wp_nonce_field('hm_sbp_do_export');
	echo('
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="hm_sbp_field_report_time">Report Period:</label>
						</th>
						<td>
							<select name="report_time" id="hm_sbp_field_report_time">
								<option value="0d">Today</option>
								<option value="1d">Yesterday</option>
								<option value="7d">Last 7 days</option>
								<option value="30d" selected="selected">Last 30 days</option>
								<option value="all">All time</option>
								<option value="custom">Custom date range</option>
							</select>
						</td>
					</tr>
					<tr valign="top" class="hm_sbp_custom_time">
						<th scope="row">
							<label for="hm_sbp_field_report_start">Start Date:</label>
						</th>
						<td>
							<input type="date" name="report_start" id="hm_sbp_field_report_start" value="'.date('Y-m-d', current_time('timestamp') - (86400 * 31)).'" />
						</td>
					</tr>
					<tr valign="top" class="hm_sbp_custom_time">
						<th scope="row">
							<label for="hm_sbp_field_report_end">End Date:</label>
						</th>
						<td>
							<input type="date" name="report_end" id="hm_sbp_field_report_end" value="'.date('Y-m-d', current_time('timestamp') - 86400).'" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="hm_sbp_field_cat">Product Category:</label>
						</th>
						<td>
	');
	
	wp_dropdown_categories(array(
		'taxonomy' => 'product_cat',
		'id' => 'hm_sbp_field_cat',
		'name' => 'cat',
		'orderby' => 'NAME',
		'order' => 'ASC',
		'show_option_all' => 'All Categories'
	));
	
	echo('
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="hm_sbp_field_orderby">Sort By:</label>
						</th>
						<td>
							<select name="orderby" id="hm_sbp_field_orderby">
								<option value="product_id">Product ID</option>
								<option value="quantity" selected="selected">Quantity Sold</option>
								<option value="gross">Gross Sales</option>
							</select>
							<select name="orderdir">
								<option value="asc">ascending</option>
								<option value="desc" selected="selected">descending</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" colspan="2" class="th-full">
							<label>
								<input type="checkbox" name="limit_on" />
								Show only the first
								<input type="number" name="limit" value="10" min="0" step="1" class="small-text" />
								products
							</label>
						</th>
					</tr>
					<tr valign="top">
						<th scope="row" colspan="2" class="th-full">
							<label>
								<input type="checkbox" name="include_header" checked="checked" />
								Include header row
							</label>
						</th>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button-primary">Get Report</button>
				</p>
			</form>
			
			<p>
				Plugin by:<br />
				<a href="http://hearkenmedia.com/landing-wp-plugin.php?utm_source=product-sales-report&amp;utm_medium=link&amp;utm_campaign=wp-widget-link" target="_blank">
					<img src="'.plugins_url('images/hm-logo.png', __FILE__).'" alt="Hearken Media" style="width: 250px;" />
				</a>
			</p>
			
		</div>
		
		<script type="text/javascript" src="'.plugins_url('js/hm-product-sales-report.js', __FILE__).'"></script>
	');

}

// Hook into WordPress init; this function performs report generation when
// the admin form is submitted
add_action('init', 'hm_sbp_on_init');
function hm_sbp_on_init() {
	global $pagenow;
	
	// Check if we are in admin and on the report page
	if (!is_admin())
		return;
	if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'hm_sbp' && !empty($_POST['hm_sbp_do_export'])) {
		
		// Verify the nonce
		check_admin_referer('hm_sbp_do_export');
		
		// Assemble the filename for the report download
		$filename =  'Product Sales - ';
		if (!empty($_POST['cat']) && is_numeric($_POST['cat'])) {
			$cat = get_term($_POST['cat'], 'product_cat');
			if (!empty($cat->name))
				$filename .= addslashes(html_entity_decode($cat->name)).' - ';
		}
		$filename .= date('Y-m-d', current_time('timestamp')).'.csv';
		
		// Send headers
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		
		// Output the report header row (if applicable) and body
		$stdout = fopen('php://output', 'w');
		if (!empty($_POST['include_header']))
			hm_sbp_export_header($stdout);
		hm_sbp_export_body($stdout);
		
		exit;
	}
}

// This function outputs the report header row
function hm_sbp_export_header($dest) {
	fputcsv($dest, array('Product ID', 'Product Name', 'Quantity Sold', 'Gross Sales'));
}

// This function generates and outputs the report body rows
function hm_sbp_export_body($dest) {
	global $woocommerce;
	
	// If a category was selected, fetch all the product IDs in that category
	$category_id = (isset($_POST['cat']) && is_numeric($_POST['cat']) ? $_POST['cat'] : 0);
	if (!empty($category_id))
		$product_ids = get_objects_in_term($category_id, 'product_cat');
	
	// Calculate report start and end dates (timestamps)
	switch ($_POST['report_time']) {
		case '0d':
			$end_date = strtotime('midnight');
			$start_date = $end_date;
			break;
		case '1d':
			$end_date = strtotime('midnight') - 86400;
			$start_date = $end_date;
			break;
		case '7d':
			$end_date = strtotime('midnight') - 86400;
			$start_date = $end_date - (86400 * 7);
			break;
		case 'custom':
			$end_date = strtotime($_POST['report_end']);
			$start_date = strtotime($_POST['report_start']);
			break;
		default: // 30 days is the default
			$end_date = strtotime('midnight') - 86400;
			$start_date = $end_date - (86400 * 30);
	}
	
	// Assemble order by string
	$orderby = (in_array($_POST['orderby'], array('product_id', 'gross')) ? $_POST['orderby'] : 'quantity');
	$orderby .= ' '.($_POST['orderdir'] == 'asc' ? 'ASC' : 'DESC');
	
	// Create a new WC_Admin_Report object
	include_once($woocommerce->plugin_path().'/includes/admin/reports/class-wc-admin-report.php');
	$wc_report = new WC_Admin_Report();
	$wc_report->start_date = $start_date;
	$wc_report->end_date = $end_date;

	// Get report data
	// Based on woocoommerce/includes/admin/reports/class-wc-report-sales-by-product.php
	$sold_products = $wc_report->get_order_report_data(array(
		'data' => array(
			'_product_id' => array(
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => '',
				'name' => 'product_id'
			),
			'_qty' => array(
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => 'SUM',
				'name' => 'quantity'
			),
			'_line_subtotal' => array(
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => 'SUM',
				'name' => 'gross'
			)
		),
		'query_type' => 'get_results',
		'group_by' => 'product_id',
		'order_by' => $orderby,
		'limit' => (!empty($_POST['limit_on']) && is_numeric($_POST['limit']) ? $_POST['limit'] : ''),
		'filter_range' => ($_POST['report_time'] != 'all'),
		'order_types' => wc_get_order_types('order_count')
	));
	
	// Output report rows
	foreach ($sold_products as $product) {
		if (!empty($category_id) && !in_array($product->product_id, $product_ids))
			continue;
		fputcsv($dest, array($product->product_id, html_entity_decode(get_the_title($product->product_id)), $product->quantity, $product->gross));
	}
}
?>