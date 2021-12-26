<?php
/*
* Plugin Name: افزونه کد تخفیف ارجاعی
* Plugin URI: https://radwebacademy.ir
* Description: افزایش خرید با به اشتراک گذاری کد تخفیف
* Version: 1.1
* Author: رضا راد
* Author URI: https://radwebacademy.ir
* License: GPLv2 or later
* Text Domain: rad-ref-coupon
* Domain Path: /languages
*/


//// Read admin setting from DB

global $wpdb;
$results = $wpdb->get_results("SELECT * FROM rad_referral_coupon_settings WHERE `setting` = 'admin_settings' LIMIT 1");
$sv = json_decode($results[0]->value);


define("PLUGIN_NAME", "referral_coupon");
define("RC_AMOUNT", $sv->RC_AMOUNT);
define("RW_AMOUNT", $sv->RW_AMOUNT);
define("RC_DISCOUNT_TYPE", $sv->RC_DISCOUNT_TYPE); // Type: fixed_cart, percent, fixed_product, percent_product
define("RW_DISCOUNT_TYPE", $sv->RW_DISCOUNT_TYPE); // Type: fixed_cart, percent, fixed_product, percent_product
define("RC_EXPIRY_DATE", $sv->RC_EXPIRY_DATE);
define("RW_EXPIRY_DATE", $sv->RW_EXPIRY_DATE);
define("RC_PRODUT_IDS", $sv->RC_PRODUT_IDS);
define("RW_PRODUT_IDS", $sv->RW_PRODUT_IDS);

define("RC_INDIVIDUAL_USE", $sv->RC_INDIVIDUAL_USE);
define("RW_INDIVIDUAL_USE", $sv->RW_INDIVIDUAL_USE);
define("RC_EXCLUDE_PRODUCT_IDS", $sv->RC_EXCLUDE_PRODUCT_IDS);
define("RW_EXCLUDE_PRODUCT_IDS", $sv->RW_EXCLUDE_PRODUCT_IDS);
define("RC_USAGE_LIMIT", $sv->RC_USAGE_LIMIT);
define("RW_USAGE_LIMIT", $sv->RW_USAGE_LIMIT);
define("RC_USAGE_LIMIT_PER_USER", $sv->RC_USAGE_LIMIT_PER_USER);
define("RW_USAGE_LIMIT_PER_USER", $sv->RW_USAGE_LIMIT_PER_USER);
define("RC_APPLY_BEFORE_TAX", $sv->RC_APPLY_BEFORE_TAX);
define("RW_APPLY_BEFORE_TAX", $sv->RW_APPLY_BEFORE_TAX);
define("RC_FREE_SHIPPING", $sv->RC_FREE_SHIPPING);
define("RW_FREE_SHIPPING", $sv->RW_FREE_SHIPPING);


/////////////////////////////////////////////////////////////////////////////////////////// wp_head
add_action("wp_head", "initial_task", -99);
function initial_task() {

	////////////// Create Table

	global $wpdb;

	$table_name = 'rad_referral_coupon_settings';


	// check if table exists
	$query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));

	if (! $wpdb->get_var($query) == $table_name) {
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting VARCHAR(256) NOT NULL,
                value TEXT,
                extra TEXT
                ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$success = empty($wpdb->last_error);

		////////////////////// insert default setting into table
		$admin_settings = array(
			'RC_AMOUNT' => '15',
			'RW_AMOUNT' => '10',
			'RC_DISCOUNT_TYPE' => 'percent',
			'RW_DISCOUNT_TYPE' => 'percent',
			'RC_EXPIRY_DATE' => '',
			'RW_EXPIRY_DATE' => '',
			'RC_PRODUT_IDS' => '',
			'RW_PRODUT_IDS' => '',
			'RC_INDIVIDUAL_USE' => 'no',
			'RW_INDIVIDUAL_USE' => 'no',
			'RC_EXCLUDE_PRODUCT_IDS' => '',
			'RW_EXCLUDE_PRODUCT_IDS' => '',
			'RC_USAGE_LIMIT' => '',
			'RW_USAGE_LIMIT' => '1',
			'RC_USAGE_LIMIT_PER_USER' => '1',
			'RW_USAGE_LIMIT_PER_USER' => '1',
			'RC_APPLY_BEFORE_TAX' => 'yes',
			'RW_APPLY_BEFORE_TAX' => 'yes',
			'RC_FREE_SHIPPING' => 'no',
			'RW_FREE_SHIPPING' => 'no'
		);
		$wpdb->insert($table_name, array(
			'setting' => 'admin_settings',
			'value' => json_encode($admin_settings)
		));
	}


}



/////////////////////////////////////////////////////////////////////////////////////////// my-account

// ------------------
// 1. register new endpoint
function referral_coupon_endpoint() {
	add_rewrite_endpoint('ref-coupon', EP_ROOT | EP_PAGES);
}
add_action('init', 'referral_coupon_endpoint');

// ------------------
// 2. Add new query var
function referral_coupon_query_vars($vars) {
	$vars[] = 'ref-coupon';
	return $vars;
}
add_filter('query_vars', 'referral_coupon_query_vars', 0);

// ------------------
// 3. Insert the new endpoint into the My Account menu
function referral_coupon_link_my_account($items) {
	$items['ref-coupon'] = __('کد تخفیف', 'rad-ref-coupon');
	return $items;
}
add_filter('woocommerce_account_menu_items', 'referral_coupon_link_my_account');

// ------------------
// 4. Add content to the new tab
function referral_coupon_content() {


	// step 0: get user
	$UserId = get_current_user_id();
	$user = new WP_User($UserId);

	// step 1: does user have coupon?
	$Post = get_page_by_title("RC-$UserId", $output = OBJECT, $post_type = 'shop_coupon');

	if (empty($Post)) {
		// create user Coupon
		$coupon = array(
			'post_title' => "RC-$UserId",
			'post_content' => PLUGIN_NAME . __(' برای ', 'rad-ref-coupon')." $user->display_name",
			'post_excerpt' => PLUGIN_NAME . __(' برای ', 'rad-ref-coupon')." $user->display_name",
			'post_status' => 'publish',
			'post_author' => $UserId,
			'post_type' => 'shop_coupon'
		);

		// create coupon
		$coupon_id = wp_insert_post($coupon);

		// Add meta
		update_post_meta($coupon_id, 'discount_type', RC_DISCOUNT_TYPE);
		update_post_meta($coupon_id, 'coupon_amount', RC_AMOUNT);
		update_post_meta($coupon_id, 'individual_use', 'no');
		update_post_meta($coupon_id, 'product_ids', RC_PRODUT_IDS);
		update_post_meta($coupon_id, 'exclude_product_ids', '');
		update_post_meta($coupon_id, 'usage_limit', '');
		update_post_meta($coupon_id, 'usage_limit_per_user', '1');
		update_post_meta($coupon_id, 'expiry_date', RC_EXPIRY_DATE);
		update_post_meta($coupon_id, 'apply_before_tax', 'yes');
		update_post_meta($coupon_id, 'free_shipping', 'no');
		update_post_meta($coupon_id, "coupon_owner", $UserId);

		$coupon = get_post($coupon_id)->post_title;
		$c = new WC_Coupon($coupon_id);
		$amount = $c->get_amount();
	} else if ($Post->post_status == "trash") {
		_e('کد تخفیف شما غیر فعال شده است', 'rad-ref-coupon');
		return;
	} else if ($Post->post_status == "publish") {
		$coupon = $Post->post_title;
		$c = new WC_Coupon($Post->ID);
		$amount = $c->get_amount();
		$rc_usage_user_limit = $c->get_usage_limit_per_user();
	}


	//////////////////////////////////////////////////////////////// display coupon
	echo
	"
	<span class='display-6'>".__('کد تخفیف شما', 'rad-ref-coupon').": </span>
	<button class='btn btn-primary px-4 position-relative'>
		<span class='fs-1'>$coupon</span>
		<span class='position-absolute top-0 start-100 fs-5 translate-middle badge rounded-pill bg-danger'>
			$amount%
		</span>
	</button>
	";

	//////////////////////////////////////////////////////////////// display social Network links
	$text = "
	*کد تخفیف: $coupon*
	شما با خرید از سایت دپارتمان ارز دیجیتال خانه کیفیت ایرانیان، با کد تخفیف بالا، میتوانید در خرید،  *$amount%*  تخفیف بگیرید.

	https://invest.iranhq.ir
	";

	//echo "<h2 class='display-6 mb-0 position-relative'> کد تخفیف شما: <span class='badge bg-primary'> $coupon </span><span class='position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger'> $amount% </span> </h2>";
	echo "<p class='text-muted mt-3'>شما می توانید با به اشتراک گذاشتن کد تخفیف بالا با دوستان خود، پس از خرید دوستتان، یک کد تخفیف ".RW_AMOUNT." درصدی دیگری هدیه بگیرید.<br>همچنین شما می توانید ".$rc_usage_user_limit." بار با کد تخفیف بالا، برای خودتان خرید کنید و ".$amount." درصد تخفیف در خرید بگیرید.</p>";
	echo "
		<div class='text-center'>
			<a href='https://wa.me/?text=$text' target='_blank' class='mx-2'> <i class='bi bi-whatsapp text-success' style='font-size: 25px'></i> </a>
			<a href='tg://msg_url?url=https://www.google.com.mx/?text=$text' target='_blank' class='mx-2'> <i class='bi bi-telegram text-primary' style='font-size: 25px'></i> </a>
		</div>
		";
	echo "<hr class='mx-5 my-5' style='border-top: 1px solid rgba(200,200,200,0.5)'>";
	//////////////////////////////////////////////////////////////// display coupon rewards
	global $wpdb;
	$result = $wpdb->get_results("SELECT `post_id` FROM `wp_postmeta`	WHERE	`meta_key` = 'coupon_owner'	AND `meta_value` = $UserId");

	echo "<h2 class='display-6 fs-3 mb-0'> کوپن های جایزه گرفته <small><i class='bi bi-emoji-smile-fill'></i></small> </h2>";
	echo "<p class='text-muted'>".__('کد تخفیف هایی که با اشتراک گذاری کد تخفیف خود، به دست آورده اید.', 'rad-ref-coupon')."</p>";
	echo "<span class='badge rounded-pill bg-secondary mx-1'>".__('مصرف شده', 'rad-ref-coupon')."</span>";
	echo "<span class='badge rounded-pill bg-success mx-1'> ".__('مصرف نشده', 'rad-ref-coupon')." </span>";
	echo "<br><br>";
	foreach ($result as $item) {
		//echo $item->post_id;
		if (empty(get_post_meta($item->post_id, "rewarded_by_order", true)))
			continue;

		$coupon = new WC_Coupon($item->post_id);

		if ($coupon->is_valid()) {
			echo
			"
			<button type='button' class='btn btn-succcess ms-4 position-relative'>
				{$coupon->code}
				<span class='position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger'> {$coupon->get_amount()}% </span>
			</button>
			";
		} else {
			echo
			"
				<button type='button' class='btn btn-secondary ms-4 position-relative'>
					{$coupon->code}
					<span class='position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger'> {$coupon->get_amount()}% </span>
				</button>
			";
		}
	}

	//print_r($my_query);
}
add_action('woocommerce_account_ref-coupon_endpoint', 'referral_coupon_content');



/////////////////////////////////////////////////////////////////////////////////////////// Add plugin menu to wordpress dashboard
////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////


add_action('admin_menu', 'custom_menu');

function custom_menu() {
	add_menu_page(
		__('کد تحفیف ارجاع', 'rad-ref-coupon'),
		__('کد تحفیف ارجاع', 'rad-ref-coupon'),
		"edit_posts",
		"ref-coupon",
		"ref_coupon_function",
		null,
		2
	);
}

function ref_coupon_function() {
	echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">';
	echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">';
	?>

	<style>
		.search-inp {
			border-top-style: hidden;
			border-right-style: hidden;
			border-left-style: hidden;
			border-bottom-style: hidden;
			background-color: transparent;
			color: rgba(0,0,0,0.4);
		}
		.search-inp:focus {
			outline: none;
		}
	</style>


	<?php
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		?>
		<div class="container-fluid">
			<div class="rounded shadow p-4 my-3" style="background: rgba(200,200,200,0.2)">
				<?php

				$admin_settings = array(
					'RC_AMOUNT' => $_POST['RC_AMOUNT'],
					'RW_AMOUNT' => $_POST['RW_AMOUNT'],
					'RC_DISCOUNT_TYPE' => $_POST['RC_DISCOUNT_TYPE'],
					'RW_DISCOUNT_TYPE' => $_POST['RW_DISCOUNT_TYPE'],
					'RC_EXPIRY_DATE' => $_POST['RC_EXPIRY_DATE'],
					'RW_EXPIRY_DATE' => $_POST['RW_EXPIRY_DATE'],
					'RC_PRODUT_IDS' => $_POST['RC_PRODUT_IDS'],
					'RW_PRODUT_IDS' => $_POST['RW_PRODUT_IDS'],
					'RC_INDIVIDUAL_USE' => $_POST['RC_INDIVIDUAL_USE'],
					'RW_INDIVIDUAL_USE' => $_POST['RW_INDIVIDUAL_USE'],
					'RC_EXCLUDE_PRODUCT_IDS' => $_POST['RC_EXCLUDE_PRODUCT_IDS'],
					'RW_EXCLUDE_PRODUCT_IDS' => $_POST['RW_EXCLUDE_PRODUCT_IDS'],
					'RC_USAGE_LIMIT' => $_POST['RC_USAGE_LIMIT'],
					'RW_USAGE_LIMIT' => $_POST['RW_USAGE_LIMIT'],
					'RC_USAGE_LIMIT_PER_USER' => $_POST['RC_USAGE_LIMIT_PER_USER'],
					'RW_USAGE_LIMIT_PER_USER' => $_POST['RW_USAGE_LIMIT_PER_USER'],
					'RC_APPLY_BEFORE_TAX' => $_POST['RC_APPLY_BEFORE_TAX'],
					'RW_APPLY_BEFORE_TAX' => $_POST['RW_APPLY_BEFORE_TAX'],
					'RC_FREE_SHIPPING' => $_POST['RC_FREE_SHIPPING'],
					'RW_FREE_SHIPPING' => $_POST['RW_FREE_SHIPPING']
				);

				$value = json_encode($admin_settings);

				//echo $value;

				//*
				global $wpdb;
				$updated = $wpdb->get_results("UPDATE rad_referral_coupon_settings SET value = '$value' WHERE setting = 'admin_settings' ");

				if ($wpdb->last_error)
					echo "<span class='px-2' style='border-right: 3px solid red';> ". __('ذخیره اطلاعات با مشکل مواجه شد', 'rad-ref-coupon').": ".$wpdb->last_error." </span>";
				else
					echo "<span class='px-2' style='border-right: 3px solid green'> ".__('اطلاعات با موفقیت ذخیره شدند.', 'rad-ref-coupon')." </span>";
				//*/
				?>
			</div>
		</div>
		<?
	} ?>


	<div class="container-fluid">
		<div class="rounded shadow p-4 my-3" style="background: rgba(200,200,200,0.2)">
			<h3 class="my-2"><? _e('تنظیمات افزونه کد تخفیف ارجاعی', 'rad-ref-coupon') ?></h3>

			<?php
			global $wpdb;
			$results = $wpdb->get_results("SELECT * FROM rad_referral_coupon_settings WHERE setting = 'admin_settings' LIMIT 1");
			$setting = json_decode($results[0]->value);
			?>
			<form action="" method="POST">
				<div class="row d-flex justify-content-around mt-5">
					<div class="col-sm-12 col-md-5 border rounded p-3 mb-4 shadow">
						<h6><? _e('تنظیمات ساخت کد تخفیف اختصاصی هر کاربر', 'rad-ref-coupon') ?></h6>
						<small style="font-size: 12px"><? _e('تنظیمات کد تخفیف پیش فرض هر کاربر (کد تخفیفی که هر سیستم به صورت پیش فرض به هر کاربر اختصاص می دهد)', 'rad-ref-coupon') ?></small>
						<div class="row d-flex justify-content-around mt-4" dir="ltr0">
							<div class="Col text-center" dir="ltr0">
								<div class="d-flex mb-3">
									<span class="rounded ms-1 p-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('نوع تخفیف', 'rad-ref-coupon') ?>  </span>
									<select name="RC_DISCOUNT_TYPE" class="form-select" aria-label="Default select example">
										<option <?= $setting->RC_DISCOUNT_TYPE === '' ? 'selected' : '' ?>><? _e('نوع کد تخفیف را انتخاب کنید', 'rad-ref-coupon') ?></option>
										<option value="percent" <?= $setting->RC_DISCOUNT_TYPE === 'percent' ? 'selected' : '' ?>><? _e('درصد تخفیف', 'rad-ref-coupon') ?></option>
										<option value="fixed_cart" <?= $setting->RC_DISCOUNT_TYPE === 'fixed_cart' ? 'selected' : '' ?>><? _e('تخفیف ثابت سبد خرید', 'rad-ref-coupon') ?></option>
										<option value="fixed_product" <?= $setting->RC_DISCOUNT_TYPE === 'fixed_product' ? 'selected' : '' ?>><? _e('تخفیف ثابت محصول', 'rad-ref-coupon') ?></option>
										<option value="percent_product" <?= $setting->RC_DISCOUNT_TYPE === 'percent_product' ? 'selected' : '' ?>><? _e('درصد تخفیف محصول', 'rad-ref-coupon') ?></option>
									</select>
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('مقدار تخفیف', 'rad-ref-coupon') ?>مقدار تخفیف</span>
									<input type="number" name="RC_AMOUNT" min="0" value="<?= $setting->RC_AMOUNT ?>" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('استفاده فردی', 'rad-ref-coupon') ?></span>
									<select name="RC_INDIVIDUAL_USE" class="form-select" aria-label="Default select example">
										<option <?= $setting->RC_INDIVIDUAL_USE === '' ? 'selected' : '' ?>><? _e('انتخاب کنید', 'rad-ref-coupon') ?></option>
										<option value="yes" <?= $setting->RC_INDIVIDUAL_USE === 'yes' ? 'selected' : '' ?>><? _e('بله', 'rad-ref-coupon') ?></option>
										<option value="no" <?= $setting->RC_INDIVIDUAL_USE === 'no' ? 'selected' : '' ?>><? _e('خیر', 'rad-ref-coupon') ?></option>
									</select>
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('تاریخ انقضا', 'rad-ref-coupon') ?></span>
									<input type="date" name="RC_EXPIRY_DATE" value="<?= $setting->RC_EXPIRY_DATE ?>" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('تخفیف محصولات', 'rad-ref-coupon') ?></span>
									<input type="text" name="RC_PRODUT_IDS" value="<?= $setting->RC_PRODUT_IDS ?>" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('تخفیف بجز محصولات', 'rad-ref-coupon') ?></span>
									<input type="text" name="RC_EXCLUDE_PRODUCT_IDS" value="<?= $setting->RC_EXCLUDE_PRODUCT_IDS ?>" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('محدودیت استفاده', 'rad-ref-coupon') ?></span>
									<input type="number" name="RC_USAGE_LIMIT" value="<?= $setting->RC_USAGE_LIMIT ?>" min="0" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('محدودیت استفاده هر کاربر', 'rad-ref-coupon') ?></span>
									<input type="number" name="RC_USAGE_LIMIT_PER_USER" value="<?= $setting->RC_USAGE_LIMIT_PER_USER ?>" min="0" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('اعمال قبل از مالیات', 'rad-ref-coupon') ?></span>
									<select name="RC_APPLY_BEFORE_TAX" class="form-select" aria-label="Default select example">
										<option <?= $setting->RC_APPLY_BEFORE_TAX === '' ? 'selected' : '' ?>><? _e('انتخاب کنید', 'rad-ref-coupon') ?></option>
										<option value="yes" <?= $setting->RC_APPLY_BEFORE_TAX === 'yes' ? 'selected' : '' ?>><? _e('بله', 'rad-ref-coupon') ?></option>
										<option value="no" <?= $setting->RC_APPLY_BEFORE_TAX === 'no' ? 'selected' : '' ?>><? _e('خیر', 'rad-ref-coupon') ?></option>
									</select>
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('اجازه حمل و نقل رایگان', 'rad-ref-coupon') ?></span>
									<select name="RC_FREE_SHIPPING" class="form-select" aria-label="Default select example">
										<option <?= $setting->RC_FREE_SHIPPING === '' ? 'selected' : '' ?>><? _e('انتخاب کنید', 'rad-ref-coupon') ?></option>
										<option value="yes" <?= $setting->RC_FREE_SHIPPING === 'yes' ? 'selected' : '' ?>><? _e('بله', 'rad-ref-coupon') ?></option>
										<option value="no" <?= $setting->RC_FREE_SHIPPING === 'no' ? 'selected' : '' ?>><? _e('خیر', 'rad-ref-coupon') ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>


					<div class="col-sm-12 col-md-5 border rounded p-3 shadow">
						<h6><? _e('تنظیمات ساخت کد تخفیف جایزه خرید', 'rad-ref-coupon') ?></h6>
						<small style="font-size: 12px"><? _e('تنظیمات کد تخفیفی که از از خرید دوستان (به دلیل به اشتراک گذاری کد تخفیف کاربر) به عنوان جایزه دریافت می شود.', 'rad-ref-coupon') ?></small>
						<div class="row d-flex justify-content-around mt-4" dir="ltr0">
							<div class="Col text-center" dir="ltr0">
								<div class="d-flex mb-3">
									<span class="rounded ms-1 p-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('نوع تخفیف', 'rad-ref-coupon') ?>  </span>
									<select name="RW_DISCOUNT_TYPE" class="form-select" aria-label="Default select example">
										<option <?= $setting->RW_DISCOUNT_TYPE === '' ? 'selected' : '' ?>><? _e('نوع کد تخفیف را انتخاب کنید', 'rad-ref-coupon') ?></option>
										<option value="percent" <?= $setting->RW_DISCOUNT_TYPE === 'percent' ? 'selected' : '' ?>><? _e('درصد تخفیف', 'rad-ref-coupon') ?></option>
										<option value="fixed_cart" <?= $setting->RW_DISCOUNT_TYPE === 'fixed_cart' ? 'selected' : '' ?>><? _e('تخفیف ثابت سبد خرید', 'rad-ref-coupon') ?></option>
										<option value="fixed_product" <?= $setting->RW_DISCOUNT_TYPE === 'fixed_product' ? 'selected' : '' ?>><? _e('تخفیف ثابت محصول', 'rad-ref-coupon') ?></option>
										<option value="percent_product" <?= $setting->RW_DISCOUNT_TYPE === 'percent_product' ? 'selected' : '' ?>><? _e('درصد تخفیف محصول', 'rad-ref-coupon') ?></option>
									</select>
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('مقدار تخفیف', 'rad-ref-coupon') ?>مقدار تخفیف</span>
									<input type="number" name="RW_AMOUNT" min="0" value="<?= $setting->RW_AMOUNT ?>" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('استفاده فردی', 'rad-ref-coupon') ?></span>
									<select name="RW_INDIVIDUAL_USE" class="form-select" aria-label="Default select example">
										<option <?= $setting->RW_INDIVIDUAL_USE === '' ? 'selected' : '' ?>><? _e('انتخاب کنید', 'rad-ref-coupon') ?></option>
										<option value="yes" <?= $setting->RW_INDIVIDUAL_USE === 'yes' ? 'selected' : '' ?>><? _e('بله', 'rad-ref-coupon') ?></option>
										<option value="no" <?= $setting->RW_INDIVIDUAL_USE === 'no' ? 'selected' : '' ?>><? _e('خیر', 'rad-ref-coupon') ?></option>
									</select>
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('تاریخ انقضا', 'rad-ref-coupon') ?></span>
									<input type="date" name="RW_EXPIRY_DATE" value="<?= $setting->RW_EXPIRY_DATE ?>" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('تخفیف محصولات', 'rad-ref-coupon') ?></span>
									<input type="text" name="RW_PRODUT_IDS" value="<?= $setting->RW_PRODUT_IDS ?>" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('تخفیف بجز محصولات', 'rad-ref-coupon') ?></span>
									<input type="text" name="RW_EXCLUDE_PRODUCT_IDS" value="<?= $setting->RW_EXCLUDE_PRODUCT_IDS ?>" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('محدودیت استفاده', 'rad-ref-coupon') ?></span>
									<input type="number" name="RW_USAGE_LIMIT" value="<?= $setting->RW_USAGE_LIMIT ?>" min="0" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('محدودیت استفاده هر کاربر', 'rad-ref-coupon') ?></span>
									<input type="number" name="RW_USAGE_LIMIT_PER_USER" value="<?= $setting->RW_USAGE_LIMIT_PER_USER ?>" min="0" class="form-control search-inp text-center">
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('اعمال قبل از مالیات', 'rad-ref-coupon') ?></span>
									<select name="RW_APPLY_BEFORE_TAX" class="form-select" aria-label="Default select example">
										<option <?= $setting->RW_APPLY_BEFORE_TAX === '' ? 'selected' : '' ?>><? _e('انتخاب کنید', 'rad-ref-coupon') ?></option>
										<option value="yes" <?= $setting->RW_APPLY_BEFORE_TAX === 'yes' ? 'selected' : '' ?>><? _e('بله', 'rad-ref-coupon') ?></option>
										<option value="no" <?= $setting->RW_APPLY_BEFORE_TAX === 'no' ? 'selected' : '' ?>><? _e('خیر', 'rad-ref-coupon') ?></option>
									</select>
								</div>
								<div class="d-flex mb-3">
									<span class="rounded ms-1" style="font-size: 12px; background: rgba(200,200,200,0.5);"><? _e('اجازه حمل و نقل رایگان', 'rad-ref-coupon') ?></span>
									<select name="RW_FREE_SHIPPING" class="form-select" aria-label="Default select example">
										<option <?= $setting->RW_FREE_SHIPPING === '' ? 'selected' : '' ?>><? _e('انتخاب کنید', 'rad-ref-coupon') ?></option>
										<option value="yes" <?= $setting->RW_FREE_SHIPPING === 'yes' ? 'selected' : '' ?>><? _e('بله', 'rad-ref-coupon') ?></option>
										<option value="no" <?= $setting->RW_FREE_SHIPPING === 'no' ? 'selected' : '' ?>><? _e('خیر', 'rad-ref-coupon') ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>
					<div class="row d-flex justify-content-center mt-4">
						<div class="col d-flex justify-content-center">
							<input type="submit" name="submit" value="<? _e('ذخیره', 'rad-ref-coupon') ?>" class="btn btn-primary" />
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<?php
}







/////////////////////////////////////////////////////////////////////////////////////////// woocommerce_order_status_completed
////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////

add_action("woocommerce_order_status_processing", "reward_coupon", 10, 1);
add_action("woocommerce_order_status_completed", "reward_coupon", 10, 1);
function reward_coupon($order_id) {
	try {
		// step 0: get user
		$UserId = get_current_user_id();
		$user = new WP_User($UserId);

		// get invoice
		$order = wc_get_order($order_id);

		// does order have coupon code?
		if (empty($order->get_coupon_codes()))
			throw new Exception(_e('کد تخفیفی استفاده نشده است', 'rad-ref-coupon'));

		foreach ($order->get_coupon_codes() as $coupon_code) {
			$coupon = new WC_Coupon($coupon_code);
			$discount_type = $coupon->get_id(); // Get coupon discount type
			$discount_type = $coupon->get_discount_type(); // Get coupon discount type
			$coupon_amount = $coupon->get_amount(); // Get coupon amount

			$rc_user_id = get_post_meta($coupon->get_id(), "coupon_owner", true);
			if (empty($rc_user_id))
				throw new Exception(_e('این کوپن ارجاعی نیست.', 'rad-ref-coupon'));

			$check_rewarded_coupon = get_post_meta($coupon->get_id(), "rewarded_by_order", true);
			if (!empty($check_rewarded_coupon))
				throw new Exception(_e('این کد تخفیف پاداش بوده و اجازه پاداش مجدد ندارد.', 'rad-ref-coupon'));

			//is coupon valid?
			$coupon_status = get_post($coupon->get_id());
			if ($coupon_status->post_status != "publish")
				throw new Exception(_e('کد تخفیف نامعتبر است.', 'rad-ref-coupon'));
		}

		//creete coupon reward
		$coupon = array(
			'post_title' => "RW-$user->ID",
			'post_content' => PLUGIN_NAME . " reward from $user->display_name",
			'post_excerpt' => PLUGIN_NAME . " reward from $user->display_name",
			'post_status' => 'publish',
			'post_author' => $user->ID,
			'post_type' => 'shop_coupon'
		);

		// create coupon
		$rw_coupon_id = wp_insert_post($coupon);

		// Add meta
		update_post_meta($rw_coupon_id, 'discount_type', RW_DISCOUNT_TYPE);
		update_post_meta($rw_coupon_id, 'coupon_amount', RW_AMOUNT);
		update_post_meta($rw_coupon_id, 'individual_use', 'no');
		update_post_meta($rw_coupon_id, 'product_ids', RW_PRODUT_IDS);
		update_post_meta($rw_coupon_id, 'exclude_product_ids', '');
		update_post_meta($rw_coupon_id, 'usage_limit', '1');
		update_post_meta($rw_coupon_id, 'usage_limit_per_user', '1');
		update_post_meta($rw_coupon_id, 'expiry_date', RW_EXPIRY_DATE);
		update_post_meta($rw_coupon_id, 'apply_before_tax', 'yes');
		update_post_meta($rw_coupon_id, 'free_shipping', 'no');
		update_post_meta($rw_coupon_id, "rewarded_by_order", $order_id);
		update_post_meta($rw_coupon_id, "rewarded_by_user", $user->ID);
		update_post_meta($rw_coupon_id, "coupon_owner", $rc_user_id);
	} catch (Exception $e) {
		global $wpdb;
		$wpdb->get_results("INSERT INTO `log` (`log`) VALUES ('{$e->getMessage()}');");
		//file_put_contents("plugin_log.txt", $e->getMessage(), FILE_APPEND | LOCK_EX);
	}
}