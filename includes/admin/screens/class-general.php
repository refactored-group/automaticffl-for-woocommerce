<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Admin\Screens;

defined( 'ABSPATH' ) || exit;

use RefactoredGroup\AutomaticFFL\Admin\Abstract_Settings_Screen;
use RefactoredGroup\AutomaticFFL\Helper\Config;

/**
 * General Screen Class
 */
class General extends Abstract_Settings_Screen {
	// @var string Screen ID.
	const ID = 'general';

	/**
	 * General screen constructor. This screen will be displayed in a tab.
	 */
	public function __construct() {

		$this->id    = self::ID;
		$this->label = __( 'General', 'automaticffl-for-woocommerce' );
		$this->title = __( 'General', 'automaticffl-for-woocommerce' );
	}

	/**
	 * Get Settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings() {

		return array(
			array(
				'type'  => 'title',
				'title' => __( 'Settings' ),
			),
			array(
				'id'      => Config::FFL_SANDBOX_MODE_CONFIG,
				'title'   => __( 'Sandbox Mode', 'automaticffl-for-woocommerce' ),
				'type'    => 'select',
				'desc'    => __( 'Enable the sandbox mode if you are testing or debugging this extension.', 'automaticffl-for-woocommerce' ),
				'default' => Config::SETTING_NO,
				'options' => array(
					Config::SETTING_YES => __( 'Yes', 'automaticffl-for-woocommerce' ),
					Config::SETTING_NO  => __( 'No', 'automaticffl-for-woocommerce' ),
				),
			),
			array(
				'id'    => Config::FFL_STORE_HASH_CONFIG,
				'title' => __( 'Store Hash', 'automaticffl-for-woocommerce' ),
				'type'  => 'text',
				'label' => 'Label',
				'desc'  => __( "Your subscription's store hash. If you still do not have one, <a href='https://www.automaticffl.com/' target='_blank'>click here</a>.", 'automaticffl-for-woocommerce' ),
			),
			array( 'type' => 'sectionend' ),
			array(
				'type'  => 'title',
				'title' => __( 'Google Maps', 'automaticffl-for-woocommerce' ),
				'desc'  => __( 'Google Maps is used to create the map experience during the checkout. It is mandatory to have an API Key in order for this plugin to work.</br>If you do not have one yet, <a href="https://console.cloud.google.com/projectselector2/google/maps-apis/credentials" target="_blank">click here</a>.', 'automaticffl-for-woocommerce' ),
			),
			array(
				'id'    => Config::FFL_GOOGLE_MAPS_API_KEY_CONFIG,
				'title' => __( 'API Key', 'automaticffl-for-woocommerce' ),
				'type'  => 'text',
				'label' => 'Label',
				'css'   => 'width: 250px;',
				'desc'  => __( 'Your Google Maps API Key.', 'automaticffl-for-woocommerce' ),
			),
			array( 'type' => 'sectionend' ),
		);
	}
}
