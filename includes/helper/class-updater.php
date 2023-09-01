<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined('ABSPATH') || exit;

class Updater {
    
	const FFL_REPOSITORY	=	'https://api.github.com/repos/refactored-group/automaticffl-for-woocommerce/releases/latest';
	const FFL_RELEASES	=	'https://github.com/refactored-group/automaticffl-for-woocommerce/releases';
	const PLUGIN_PATH	=	'automaticffl-for-woocommerce/automaticffl-for-woocommerce.php';
	const PLUGIN_SLUG	=	'automaticffl-for-woocommerce';

	public $plugin_slug;
    public $version;
    public $cache_key;
    public $cache_allowed;

    public function __construct() {

        $this->plugin_slug   = self::PLUGIN_SLUG;
        $this->version       = '1.0.1';
        $this->cache_key     = 'ffl_updater';
        $this->cache_allowed = false;
        add_filter( 'https_local_ssl_verify', '__return_false' );
        add_filter( 'https_ssl_verify', '__return_false' );
        add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
        add_filter( 'site_transient_update_plugins', [ $this, 'update' ] );
        add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
    }

    public function request(){

        $remote = get_transient( $this->cache_key );

        if( false === $remote || ! $this->cache_allowed ) {

            // URL Placeholder
            $remote = wp_remote_get( 'URL/ffl-updater.json', [
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json'
                    ]
                ]
            );

            if ( is_wp_error( $remote ) || 200 !== wp_remote_retrieve_response_code( $remote ) || empty( wp_remote_retrieve_body( $remote ) ) ) {
                return false;
            }

            set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );

        }

        $remote = json_decode( wp_remote_retrieve_body( $remote ) );

        return $remote;

    }

    public function info( $response, $action, $args ) {

        // do nothing if you're not getting plugin information right now
        if ( 'plugin_information' !== $action ) {
            return $response;
        }

        // do nothing if it is not our plugin
        if ( empty( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
            return $response;
        }

        // get updates
        $remote = $this->request();

        if ( ! $remote ) {
            return $response;
        }

        $response = new \stdClass();

        $response->name           = $remote->name;
        $response->slug           = $remote->slug;
        $response->version        = $remote->version;
        $response->tested         = $remote->tested;
        $response->requires       = $remote->requires;
        $response->author         = $remote->author;
        $response->download_link  = $remote->download_url;
        $response->trunk          = $remote->download_url;
        $response->requires_php   = $remote->requires_php;
        $response->last_updated   = $remote->last_updated;

        $response->sections = [
            'description'  => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog'    => $remote->sections->changelog
        ];

        if ( ! empty( $remote->banners ) ) {
            $response->banners = [
                'low'  => $remote->banners->low,
                'high' => $remote->banners->high
            ];
        }

        return $response;

    }

    public function update( $transient ) {

        if ( empty($transient->checked ) ) {
            return $transient;
        }

        $remote = $this->request();

        if ( 
            $remote && version_compare( $this->version, $remote->version, '<' ) 
            && version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' ) 
            && version_compare( $remote->requires_php, PHP_VERSION, '<' ) ) {
            $response              = new \stdClass();
            $response->slug        = self::PLUGIN_SLUG;
            $response->plugin      = self::PLUGIN_PATH;
            $response->new_version = $remote->version;
            $response->tested      = $remote->tested;
            $response->package     = $remote->download_url;

            $transient->response[ $response->plugin ] = $response;

        }

        return $transient;

    }

    public function purge( $upgrader, $options ) {

        if ( $this->cache_allowed && 'update' === $options['action'] && 'plugin' === $options[ 'type' ] ) {
            // just clean the cache when new plugin version is installed
            delete_transient( $this->cache_key );
        }

    }

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

		$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . self::PLUGIN_PATH);

		$latest_text_link = '<p><a href="' . self::FFL_RELEASES . '" target="_blank">Click here to check out the latest release</a></p>';
		$new_version_available = '<p> Currently using Automatic FFL for WooCommerce <span style="color:red;">'. $plugin_data['Version'] .'</span>. The latest is <span style="color:green;"><b>' . $latest_version . '</b></span<</p>';
		
		$is_outdated = '<b>New version available for Automatic FFL</b> &#x2757' . $new_version_available . $latest_text_link;
		$is_updated = 'Plugin is up to date. &#x2705 <p>Automatic FFL for WooCommerce <span style="color:green;">'. $plugin_data['Version'] .'</span>.</p>';

		$development_version = '<p>Plugin is on a development version.</p>
		<p>Automatic FFL for WooCommerce '. $plugin_data['Version'] . '.</p></b>';

		if ($latest_version) {
			if ($plugin_data && isset($plugin_data['Version'])) {
				if (version_compare($plugin_data['Version'], $latest_version, '<')) {
					return $is_outdated;
				} else if (version_compare($plugin_data['Version'], $latest_version, '>')) {
					return $development_version;
				} else {
					return $is_updated;
				}
			} else {
				return '<p><b>Unable to retrieve the version of Automatic FFL</b> &#x2757</p>' . $latest_text_link;
			}
		} else {
			return "Unable to retrieve latest release version. Please try again later or contact our support.";
		}

	}

}
