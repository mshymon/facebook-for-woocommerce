<?php
declare(strict_types=1);


class fbproductTest extends WP_UnitTestCase {
	private $parent_fb_product;

	/**
	 * Test it gets description from post meta.
	 * @return void
	 */
	public function test_get_fb_description_from_post_meta() {
		$product = WC_Helper_Product::create_simple_product();

		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_description( 'fb description' );
		$description = $facebook_product->get_fb_description();

		$this->assertEquals( $description, 'fb description');
	}

	/**
	 * Test it gets description from parent product if it is a variation.
	 * @return void
	 */
	public function test_get_fb_description_variable_product() {
		$variable_product = WC_Helper_Product::create_variation_product();
		$variable_product->set_description('parent description');
		$variable_product->save();

		$parent_fb_product = new \WC_Facebook_Product($variable_product);
		$variation         = wc_get_product($variable_product->get_children()[0]);

		$facebook_product = new \WC_Facebook_Product( $variation, $parent_fb_product );
		$description      = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'parent description' );

		$variation->set_description( 'variation description' );
		$variation->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'variation description' );
	}

	/**
	 * Tests that if no description is found from meta or variation, it gets description from post
	 *
	 * @return void
	 */
	public function test_get_fb_description_from_post_content() {
		$product = WC_Helper_Product::create_simple_product();

		// Gets description from title
		$facebook_product = new \WC_Facebook_Product( $product );
		$description      = $facebook_product->get_fb_description();

		$this->assertEquals( $description, get_post( $product->get_id() )->post_title );

		// Gets description from excerpt (product short description)
		$product->set_short_description( 'short description' );
		$product->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_excerpt );

		// Gets description from content (product description)
		$product->set_description( 'product description' );
		$product->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_content );

		// Gets description from excerpt ignoring content when short mode is set
		add_option(
			WC_Facebookcommerce_Integration::SETTING_PRODUCT_DESCRIPTION_MODE,
			WC_Facebookcommerce_Integration::PRODUCT_DESCRIPTION_MODE_SHORT
		);

		$facebook_product = new \WC_Facebook_Product( $product );
		$description      = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_excerpt );
	}

	/**
	 * Test it filters description.
	 * @return void
	 */
	public function test_filter_fb_description() {
		$product = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_description( 'fb description' );

		add_filter( 'facebook_for_woocommerce_fb_product_description', function( $description ) {
			return 'filtered description';
		});

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'filtered description' );

		remove_all_filters( 'facebook_for_woocommerce_fb_product_description' );

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'fb description' );

	}

	/**
	 * Test quantity_to_sell_on_facebook is populated when manage stock is enabled for simple product
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_on_for_simple_product() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$woo_product->set_manage_stock('yes');
		$woo_product->set_stock_quantity(128);

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['quantity_to_sell_on_facebook'], 128 );
	}

	/**
	 * Test quantity_to_sell_on_facebook is not populated when manage stock is disabled for simple product
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_off_for_simple_product() {
		$woo_product = WC_Helper_Product::create_simple_product();
		$woo_product->set_manage_stock('no');

		$fb_product = new \WC_Facebook_Product( $woo_product );
		$data = $fb_product->prepare_product();

		$this->assertEquals(isset($data['quantity_to_sell_on_facebook']), false);
	}

	/**
	 * Test quantity_to_sell_on_facebook is populated when manage stock is enabled for variable product
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_on_for_variable_product() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_product->set_manage_stock('yes');
		$woo_product->set_stock_quantity(128);
		
		$woo_variation = wc_get_product($woo_product->get_children()[0]);
		$woo_variation->set_manage_stock('yes');
		$woo_variation->set_stock_quantity(23);		

		$fb_parent_product = new \WC_Facebook_Product($woo_product);
		$fb_product = new \WC_Facebook_Product( $woo_variation, $fb_parent_product );

		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['quantity_to_sell_on_facebook'], 23 );
	}

	/**
	 * Test quantity_to_sell_on_facebook is not populated when manage stock is disabled for variable product and disabled for its parent
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_off_for_variable_product_and_off_for_parent() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_product->set_manage_stock('no');
		
		$woo_variation = wc_get_product($woo_product->get_children()[0]);
		$woo_product->set_manage_stock('no');

		$fb_parent_product = new \WC_Facebook_Product($woo_product);
		$fb_product = new \WC_Facebook_Product( $woo_variation, $fb_parent_product );

		$data = $fb_product->prepare_product();

		$this->assertEquals(isset($data['quantity_to_sell_on_facebook']), false);
	}

	/**
	 * Test quantity_to_sell_on_facebook is not populated when manage stock is disabled for variable product and enabled for its parent
	 * @return void
	 */
	public function test_quantity_to_sell_on_facebook_when_manage_stock_is_off_for_variable_product_and_on_for_parent() {
		$woo_product = WC_Helper_Product::create_variation_product();
		$woo_product->set_manage_stock('yes');
		$woo_product->set_stock_quantity(128);
		$woo_product->save();

		$woo_variation = wc_get_product($woo_product->get_children()[0]);
		$woo_variation->set_manage_stock('no');
		$woo_variation->save();

		$fb_parent_product = new \WC_Facebook_Product($woo_product);
		$fb_product = new \WC_Facebook_Product( $woo_variation, $fb_parent_product );
		
		$data = $fb_product->prepare_product();

		$this->assertEquals( $data['quantity_to_sell_on_facebook'], 128 );
	}
}
