<?php
/** Minimal interface used by the standalone cart integration tests. */
namespace Automattic\WooCommerce\Blocks\Integrations;

interface IntegrationInterface {
	public function get_name();
	public function initialize();
	public function get_script_handles();
	public function get_editor_script_handles();
	public function get_script_data();
}
