<?php
/**
 * FFL for WooCommerce Plugin
 * @author    Refactored Group
 * @copyright Copyright (c) 2023
 * @license   @TODO: Find appropriate license
 */
namespace RefactoredGroup\AutomaticFFL\Helper;

class Config {
    const FFL_STATUS = 'wc_ffl_status';
    const FFL_STORE_HASH_CONFIG = 'wc_ffl_store_hash';
    const FFL_SANDBOX_MODE_CONFIG = 'wc_ffl_sandbox_mode';
    const FFL_GOOGLE_MAPS_API_KEY_CONFIG = 'wc_ffl_google_maps_api_key';
    const FFL_ATTRIBUTE_LABEL = 'FFL Required';
    const FFL_ATTRIBUTE_NAME= 'ffl_required';
    const FFL_ATTRIBUTE_ENABLED = 'Yes';
    const FFL_ATTRIBUTE_DISABLED = 'No';

    /** Permanent Settings */
    const SETTING_GOOGLE_MAPS_URL = 'https://maps.googleapis.com/maps/api/js';
    const SETTING_FFL_PRODUCTION_URL = 'https://app.automaticffl.com/store-front/api';
    const SETTING_FFL_SANDBOX_URL = 'https://sandbox.automaticffl.com/store-front/api';
    const SETTING_YES = 1;
    const SETTING_NO = 0;

     public static function get_ffl_api_url() {
        if ( get_option( self::FFL_SANDBOX_MODE_CONFIG, true ) == 1 ) {
            return self::SETTING_FFL_SANDBOX_URL;
        }
        return self::SETTING_FFL_PRODUCTION_URL;
    }

    public static function get_ffl_dealers_url() {
        return sprintf('%s/%s/%s', self::get_ffl_api_url(), self::get_store_hash(), 'dealers');
    }

    public static function get_ffl_store_url() {
        return sprintf('%s/%s/%s', self::get_ffl_api_url(), 'stores', self::get_store_hash());
    }

    public static function get_store_hash() {
        return get_option( self::FFL_STORE_HASH_CONFIG, true );
    }

    public static function get_google_maps_api_key() {
        return get_option ( self::FFL_GOOGLE_MAPS_API_KEY_CONFIG, true );
    }

    public static function get_google_maps_api_url() {
        return self::SETTING_GOOGLE_MAPS_URL;
    }
}
