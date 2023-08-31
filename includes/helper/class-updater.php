<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

class Updater {
    
	const FFL_REPOSITORY	=	'https://api.github.com/repos/refactored-group/automaticffl-for-woocommerce/releases/latest';
	const FFL_RELEASES	=	'https://github.com/refactored-group/automaticffl-for-woocommerce/releases';
	const PLUGIN_SLUG	=	'automaticffl-for-woocommerce/automaticffl-for-woocommerce.php';

    /**
	 * Check if Automatic FFL for WooCommerce is updated.
	 * This function only returns if it's updated or not with a redirection link to Automatic FFL GitHub Repo.
	 *
	 * @return void
	 */
	public static function get_ffl_version() {

		if ( !is_admin() ) {
			return;
		}

		$response = wp_remote_get(self::FFL_REPOSITORY);

		if (is_wp_error($response)) {
			return false;
		}
	
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
	
		if (!$data || !isset($data['tag_name'])) {
			return false;
		}
	
		$latest_version = $data['tag_name'];

		$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . self::PLUGIN_SLUG);

		$latest_text_link = '<p><a href="' . self::FFL_RELEASES . '" target="_blank">Click here to check out the latest release</a></p>';
		$new_version_available = '<p> Currently using Automatic FFL for WooCommerce <span style="color:red;">'. $plugin_data['Version'] .'</span>. The latest is <span style="color:green;"><b>' . $latest_version . '</b></span<</p>';
		
		$is_outdated = '<b>New version available for Automatic FFL</b> &#x2757' . $new_version_available . $latest_text_link;
		$is_updated = 'Plugin is up to date. &#x2705 <p>Automatic FFL for WooCommerce <span style="color:green;">'. $plugin_data['Version'] .'</span>.</p>';

		$unknown_version = '<b><p>ATTENTION! You may using a non-official Version of Automatic FFL for WooCommerce.</p>
		<p>Please visit <a href="https://automaticffl.com/">our website</a> to get more information.</p></b>';

		if ($latest_version) {
			if ($plugin_data && isset($plugin_data['Version'])) {
				if (version_compare($plugin_data['Version'], $latest_version, '<')) {
					return $is_outdated;
				} else if (version_compare($plugin_data['Version'], $latest_version, '>')) {
					return $unknown_version;
				} else {
					return $is_updated;
				}
			} else {
				return "Unable to retrieve plugin data or version.";
			}
		} else {
			return "Unable to retrieve latest release version. Please try again later or contact our support.";
		}

	}

}