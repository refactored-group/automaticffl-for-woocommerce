<?php
/**
 * Product FFL Meta Handler
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_FFL_Meta
 *
 * Handles FFL product metadata including:
 * - Product editor checkbox
 * - CSV export/import
 * - Bulk/Quick edit functionality
 *
 * @since 1.0.14
 */
class Product_FFL_Meta {

	/**
	 * Constructor - registers all hooks.
	 *
	 * @since 1.0.14
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register all hooks for FFL product meta handling.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Product editor actions.
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_ffl_required' ), 10, 1 );

		// Bulk/Quick edit actions.
		add_action( 'woocommerce_product_bulk_edit_start', array( $this, 'add_admin_edit_checkbox' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'save_admin_edit_checkbox' ) );
		add_action( 'woocommerce_product_quick_edit_start', array( $this, 'add_admin_edit_checkbox' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( $this, 'save_admin_edit_checkbox' ) );

		// Product type options filter.
		add_filter( 'product_type_options', array( $this, 'add_product_type_option' ), 100, 1 );

		// CSV Export filters.
		add_filter( 'woocommerce_product_export_column_names', array( $this, 'add_export_column' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'add_export_column' ) );
		add_filter( 'woocommerce_product_export_product_column_ffl_required', array( $this, 'get_export_data' ), 10, 2 );

		// CSV Import filters.
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'add_import_mapping' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'add_import_default_columns' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'process_import' ), 10, 2 );
	}

	/**
	 * Add FFL Required checkbox to product type options.
	 *
	 * @since 1.0.14
	 *
	 * @param array $product_type_options Existing product type options.
	 * @return array Modified product type options.
	 */
	public function add_product_type_option( array $product_type_options ): array {
		$product_type_options['ffl_required'] = array(
			'id'            => '_ffl_required',
			'value'         => get_post_meta( get_the_ID(), '_ffl_required', true ),
			'wrapper_class' => 'show_if_simple show_if_variable show_if_grouped',
			'label'         => __( 'FFL Required', 'automaticffl-for-wc' ),
			'description'   => __( 'Check this box if the product requires shipment to an FFL dealer', 'automaticffl-for-wc' ),
			'default'       => 'no',
		);

		return $product_type_options;
	}

	/**
	 * Save FFL Required meta when product is saved.
	 *
	 * @since 1.0.14
	 *
	 * @param int $id Product ID.
	 * @return void
	 */
	public function save_ffl_required( int $id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce.
		$is_ffl = isset( $_POST['_ffl_required'] ) && 'on' === $_POST['_ffl_required'] ? 'yes' : 'no';
		update_post_meta( $id, '_ffl_required', $is_ffl );
	}

	/**
	 * Add FFL Required column to export columns.
	 *
	 * @since 1.0.14
	 *
	 * @param array $columns Export columns.
	 * @return array Modified export columns.
	 */
	public function add_export_column( array $columns ): array {
		$columns['ffl_required'] = 'FFL Required';
		return $columns;
	}

	/**
	 * Get FFL Required data for export.
	 *
	 * @since 1.0.14
	 *
	 * @param mixed       $value   Default value.
	 * @param \WC_Product $product Product object.
	 * @return string Export value ('yes' or 'no').
	 */
	public function get_export_data( $value, \WC_Product $product ): string {
		return $product->get_meta( '_ffl_required', true, 'edit' );
	}

	/**
	 * Add FFL Required to import mapping options.
	 *
	 * @since 1.0.14
	 *
	 * @param array $options Import mapping options.
	 * @return array Modified import mapping options.
	 */
	public function add_import_mapping( array $options ): array {
		$options['ffl_required'] = 'FFL Required';
		return $options;
	}

	/**
	 * Add default column mapping for FFL Required import.
	 *
	 * @since 1.0.14
	 *
	 * @param array $columns Default column mappings.
	 * @return array Modified column mappings.
	 */
	public function add_import_default_columns( array $columns ): array {
		$columns['FFL Required'] = 'ffl_required';
		$columns['ffl required'] = 'ffl_required';
		return $columns;
	}

	/**
	 * Process FFL Required data during import.
	 *
	 * @since 1.0.14
	 *
	 * @param \WC_Product $object Product object being imported.
	 * @param array       $data   CSV data for the product.
	 * @return \WC_Product Modified product object.
	 */
	public function process_import( \WC_Product $object, array $data ): \WC_Product {
		if ( ! empty( $data['ffl_required'] ) ) {
			$object->update_meta_data( '_ffl_required', $data['ffl_required'] );
		}
		return $object;
	}

	/**
	 * Add FFL Required checkbox to Bulk/Quick Edit.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public function add_admin_edit_checkbox(): void {
		?>
		<div class="inline-edit-group">
			<label class="alignleft ffl_required" style="width: 100%">
				<span class="title" style="width: fit-content;"><?php esc_html_e( 'FFL Required', 'automaticffl-for-wc' ); ?></span>
				<span class="input-text-wrap">
					<select class="ffl_required change_to" name="_ffl_required" style="margin-left: 10px">
						<option value=""><?php esc_html_e( '— No Change —', 'automaticffl-for-wc' ); ?></option>
						<option value="1"><?php esc_html_e( 'Yes', 'automaticffl-for-wc' ); ?></option>
						<option value="0"><?php esc_html_e( 'No', 'automaticffl-for-wc' ); ?></option>
					</select>
				</span>
			</label>
		</div>
		<br /><br />
		<?php
	}

	/**
	 * Save FFL Required from Bulk/Quick Edit.
	 *
	 * @since 1.0.14
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	public function save_admin_edit_checkbox( \WC_Product $product ): void {
		$product_id = $product->get_id();

		// Verify user has permission to edit this product.
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified by WooCommerce in bulk/quick edit handler.
		if ( isset( $_REQUEST['_ffl_required'] ) ) {
			$ffl_required = sanitize_text_field( wp_unslash( $_REQUEST['_ffl_required'] ) );
			if ( '1' === $ffl_required ) {
				update_post_meta( $product_id, '_ffl_required', 'yes' );
			} elseif ( '0' === $ffl_required ) {
				update_post_meta( $product_id, '_ffl_required', 'no' );
			}
		}
	}
}
