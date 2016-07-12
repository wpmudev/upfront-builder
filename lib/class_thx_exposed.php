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
	
	public function delete_user_options ($store_key) {
		// removing user site option for Builder Getting Started exp
		$store_key = (preg_match('/uf-/', $store_key))
			? $store_key
			: 'uf-' . $store_key
		;
		delete_user_option( get_current_user_id(), $store_key . self::BOOTSTRAP_EXP_SLUG, true );
	}
}
