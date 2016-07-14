<?php
/**
 * This class is also exposed even from doing AJAX
 */
class Thx_Exposed {

	const BOOTSTRAP_EXP_SLUG = '_show_builder_exp';

	private function __construct () {}
	private function __clone () {}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		if (!is_admin()) return false;
		add_action('upfront_cleanup_user_options', array($this, 'delete_user_options'));
	}
	
	/*
	* Removing user site option for Builder Getting Started exp
	*/
	public function delete_user_options ($store_key) {
		// check first if option existing
		$store_key = $store_key . self::BOOTSTRAP_EXP_SLUG;
		$has_option = get_user_option($store_key);
		if ( !$has_option ) {
			// if not found, append "uf-"
			$store_key = 'uf-' . $store_key;
			$has_option = get_user_option($store_key);
		}
		if( $has_option ) delete_user_option( get_current_user_id(), $store_key, true );
	}
}
