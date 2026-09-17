<?php
/**
 * Plugin Name: Leadwerk Importer
 * Description: Imports the Trend in Form static site into WordPress pages, the Media Library and individually editable Leadwerk fields.
 * Version: 3.0.3
 * Author: Leadwerk
 * Text Domain: leadwerk-importer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: leadwerk-fields, leadwerk-wpml-clone
 *
 * @package Leadwerk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEADWERK_IMPORTER_VERSION', '3.0.3' );
define( 'LEADWERK_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEADWERK_IMPORTER_URL', plugin_dir_url( __FILE__ ) );
define( 'LEADWERK_IMPORTER_OPTION_POST_STEPS_NOTICE', 'leadwerk_import_needs_post_steps' );

$fields_bootstrap = dirname( LEADWERK_IMPORTER_PATH ) . '/leadwerk-fields/leadwerk-fields.php';
if ( ! class_exists( 'Leadwerk_Content_Schema' ) && is_file( $fields_bootstrap ) ) {
	require_once $fields_bootstrap;
}

$translation_root = dirname( LEADWERK_IMPORTER_PATH ) . '/leadwerk-wpml-clone';
if ( ! class_exists( 'Leadwerk_Translation_API' ) && is_file( $translation_root . '/includes/class-leadwerk-translation-api.php' ) ) {
	require_once $translation_root . '/includes/class-leadwerk-translation-api.php';
}

require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-logger.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-media-importer.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-static-parser.php';
require_once LEADWERK_IMPORTER_PATH . 'includes/class-leadwerk-importer.php';

/** @return void */
function leadwerk_importer_menu() {
	add_management_page( 'Leadwerk Import', 'Leadwerk Import', 'manage_options', 'leadwerk-import', 'leadwerk_importer_admin_page' );
}
add_action( 'admin_menu', 'leadwerk_importer_menu' );

/** @return void */
function leadwerk_importer_admin_assets( $hook ) {
	if ( 'tools_page_leadwerk-import' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'leadwerk-importer-admin', LEADWERK_IMPORTER_URL . 'assets/admin-import.css', array(), LEADWERK_IMPORTER_VERSION );
	wp_enqueue_script( 'leadwerk-importer-admin', LEADWERK_IMPORTER_URL . 'assets/admin-import.js', array(), LEADWERK_IMPORTER_VERSION, true );
	wp_localize_script(
		'leadwerk-importer-admin',
		'leadwerkImporter',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'leadwerk_import_ajax' ),
			'state'   => Leadwerk_Logger::get_state(),
			'strings' => array( 'idle' => 'Henüz import çalıştırılmadı.' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'leadwerk_importer_admin_assets' );

/** @return void */
function leadwerk_importer_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$state = Leadwerk_Logger::get_state();
	?>
	<div class="wrap leadwerk-importer-admin">
		<h1>Trend in Form WordPress Import</h1>
		<p>Statik HTML sayfalarını, WebP medyaları, arkaplan görsellerini ve her sayfaya özel düzenlenebilir alanları güvenli, tekrar çalıştırılabilir gruplar halinde içe aktarır.</p>
		<div class="notice notice-info inline"><p><strong>Sıra:</strong> Leadwerk WPML Clone → Leadwerk Fields → Leadwerk Importer eklentilerini etkinleştir, <strong>Leadwerk Trend in Form</strong> temasını seç, önce Dry Run sonra Live Import çalıştır. WPForms JSON dosyasını ayrıca WPForms → Tools → Import altında içe aktar ve oluşan form ID’sini Ayarlar → Trend in Form Website alanına yaz.</p></div>
		<div class="leadwerk-importer-toolbar">
			<button type="button" class="button" data-leadwerk-start-import="dry-run">Dry Run</button>
			<button type="button" class="button button-primary" data-leadwerk-start-import="apply">Live Import</button>
			<button type="button" class="button" data-leadwerk-reset-progress>İlerlemeyi temizle</button>
		</div>
		<div class="leadwerk-importer-progress" data-leadwerk-importer-app>
			<div class="leadwerk-importer-progress__summary"><div><strong>Durum</strong><div data-import-status><?php echo esc_html( (string) ( $state['status'] ?? 'idle' ) ); ?></div></div><div><strong>Adım</strong><div data-import-step><?php echo esc_html( (string) ( $state['current_step'] ?? 'preflight' ) ); ?></div></div><div><strong>Öğe</strong><div data-import-item><?php echo esc_html( (string) ( $state['current_item'] ?? '' ) ); ?></div></div></div>
			<div class="leadwerk-importer-progress__bar"><div class="leadwerk-importer-progress__bar-fill" data-import-overall-fill style="width:<?php echo esc_attr( (string) (int) ( $state['overall_percent'] ?? 0 ) ); ?>%"></div></div>
			<div class="leadwerk-importer-progress__percent"><span data-import-overall-percent><?php echo esc_html( (string) (int) ( $state['overall_percent'] ?? 0 ) ); ?></span>%</div>
			<div class="leadwerk-importer-steps" data-import-steps></div>
			<div class="leadwerk-importer-counters"><div class="leadwerk-importer-counter"><span>Başarılı</span><strong data-import-success><?php echo esc_html( (string) (int) ( $state['success_count'] ?? 0 ) ); ?></strong></div><div class="leadwerk-importer-counter"><span>Uyarı</span><strong data-import-warnings><?php echo esc_html( (string) (int) ( $state['warning_count'] ?? 0 ) ); ?></strong></div><div class="leadwerk-importer-counter"><span>Hata</span><strong data-import-errors><?php echo esc_html( (string) (int) ( $state['error_count'] ?? 0 ) ); ?></strong></div></div>
			<div class="leadwerk-importer-log"><h2>Live Log</h2><div class="leadwerk-importer-log__list" data-import-log></div></div>
			<div class="leadwerk-importer-summary" data-import-summary></div>
		</div>
	</div>
	<?php
}

/** @return void */
function leadwerk_importer_verify_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
	}
	check_ajax_referer( 'leadwerk_import_ajax', 'nonce' );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
}

/** @return void */
function leadwerk_importer_ajax_start() {
	leadwerk_importer_verify_ajax();
	if ( Leadwerk_Logger::has_active_job() ) {
		wp_send_json_success( array( 'state' => Leadwerk_Logger::get_state() ) );
	}
	$importer = new Leadwerk_Importer( empty( $_POST['dry_run'] ) );
	wp_send_json_success( array( 'state' => $importer->build_initial_job_state() ) );
}
add_action( 'wp_ajax_leadwerk_import_start', 'leadwerk_importer_ajax_start' );

/** @return void */
function leadwerk_importer_ajax_step() {
	leadwerk_importer_verify_ajax();
	try {
		$state    = Leadwerk_Logger::get_state();
		$importer = new Leadwerk_Importer( empty( $state['dry_run'] ) );
		wp_send_json_success( array( 'state' => $importer->run_next_batch( $state ) ) );
	} catch ( Throwable $error ) {
		$state = Leadwerk_Logger::get_state();
		$state['status'] = 'failed';
		$state['current_item'] = $error->getMessage();
		$state['error_count'] = (int) ( $state['error_count'] ?? 0 ) + 1;
		$state['results']['blocking'][] = $error->getMessage();
		Leadwerk_Logger::set_state( $state );
		wp_send_json_error( array( 'message' => $error->getMessage(), 'state' => $state ), 500 );
	}
}
add_action( 'wp_ajax_leadwerk_import_step', 'leadwerk_importer_ajax_step' );

/** @return void */
function leadwerk_importer_ajax_state() {
	leadwerk_importer_verify_ajax();
	wp_send_json_success( array( 'state' => Leadwerk_Logger::get_state() ) );
}
add_action( 'wp_ajax_leadwerk_import_state', 'leadwerk_importer_ajax_state' );

/** @return void */
function leadwerk_importer_ajax_reset() {
	leadwerk_importer_verify_ajax();
	if ( Leadwerk_Logger::has_active_job() ) {
		wp_send_json_error( array( 'message' => 'Import hâlâ çalışıyor.' ), 409 );
	}
	Leadwerk_Logger::reset_job();
	wp_send_json_success( array( 'state' => array() ) );
}
add_action( 'wp_ajax_leadwerk_import_reset', 'leadwerk_importer_ajax_reset' );

/** @return void */
function leadwerk_importer_dependency_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! class_exists( 'Leadwerk_Content_Schema' ) ) {
		echo '<div class="notice notice-error"><p><strong>Leadwerk Importer:</strong> Leadwerk Fields bulunamadı.</p></div>';
	}
	if ( defined( 'ACF_VERSION' ) || defined( 'ACF_PRO' ) ) {
		echo '<div class="notice notice-error"><p><strong>Leadwerk Importer:</strong> ACF aktif. Leadwerk Fields ile çakışmaması için import sırasında ACF’yi kapatın.</p></div>';
	}
}
add_action( 'admin_notices', 'leadwerk_importer_dependency_notice' );
