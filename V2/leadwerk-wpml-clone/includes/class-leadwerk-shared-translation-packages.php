<?php
/**
 * Compatibility bridge for shared translation packages.
 *
 * Trend in Form keeps shared header/footer strings in central Leadwerk options,
 * so there are no page-specific modal packages to translate. The no-op bridge
 * preserves the stable public API used by the translation editor.
 *
 * @package Leadwerk_WPML_Clone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Shared_Translation_Packages {

	/** @param string $target_lang Target. @return array<int,mixed> */
	public static function sync_dashboard_packages( $target_lang = 'en' ) {
		return array();
	}

	/** @param int $source_post_id Source. @param string $target_lang Target. @return array<int,mixed> */
	public static function get_editor_sections( $source_post_id, $target_lang = 'en' ) {
		return array();
	}

	/** @param mixed $submitted Submitted. @param int $source_post_id Source. @param string $target_lang Target. @return array<int,mixed> */
	public static function save_editor_submissions( $submitted, $source_post_id, $target_lang = 'en' ) {
		return array();
	}

	/** @param array<int,mixed> $sections Sections. @return string */
	public static function get_sections_status( $sections ) {
		return 'complete';
	}

	/** @param string $html HTML. @param string|null $lang Lang. @return string */
	public static function localize_modals_html( $html, $lang = null ) {
		return (string) $html;
	}
}
