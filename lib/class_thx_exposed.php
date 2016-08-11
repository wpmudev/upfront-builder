<?php
/**
 * This class is also exposed even from doing AJAX
 */
class Thx_Exposed {

	const BOOTSTRAP_EXP_SLUG = 'upfront_show_builder_exp';

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
		$has_option = get_user_option(self::BOOTSTRAP_EXP_SLUG);
		if( $has_option ) delete_user_option( get_current_user_id(), self::BOOTSTRAP_EXP_SLUG, true );
	}
}
