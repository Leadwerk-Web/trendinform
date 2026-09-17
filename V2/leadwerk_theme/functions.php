<?php
/**
 * Trend in Form Leadwerk theme.
 *
 * @package Leadwerk_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEADWERK_THEME_VERSION', '3.0.4' );
define( 'LEADWERK_THEME_DIR', get_template_directory() );
define( 'LEADWERK_THEME_URI', get_template_directory_uri() );

require_once LEADWERK_THEME_DIR . '/inc/page-renderer.php';
require_once LEADWERK_THEME_DIR . '/inc/yoast-integration.php';

/** @return void */
function leadwerk_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	register_nav_menus( array( 'primary' => 'Hauptnavigation', 'footer' => 'Footer-Navigation' ) );
}
add_action( 'after_setup_theme', 'leadwerk_theme_setup' );

/** Remove WordPress' legacy external emoji assets. @return void */
function leadwerk_theme_disable_emoji_assets() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'leadwerk_theme_disable_emoji_assets' );

/** @return void */
function leadwerk_theme_enqueue_assets() {
	wp_enqueue_style( 'leadwerk-fonts', LEADWERK_THEME_URI . '/assets/fonts/fonts.css', array(), LEADWERK_THEME_VERSION );
	wp_enqueue_style( 'leadwerk-trend-in-form', LEADWERK_THEME_URI . '/assets/style.css', array( 'leadwerk-fonts' ), LEADWERK_THEME_VERSION );
	wp_enqueue_script( 'leadwerk-trend-in-form', LEADWERK_THEME_URI . '/assets/script.js', array(), LEADWERK_THEME_VERSION, true );
	wp_localize_script(
		'leadwerk-trend-in-form',
		'leadwerkTheme',
		array(
			'assetsUrl'  => trailingslashit( LEADWERK_THEME_URI . '/assets/' ),
			'homeUrl'    => leadwerk_theme_page_url( 'trend-home', home_url( '/' ) ),
			'privacyUrl' => leadwerk_theme_page_url( 'trend-datenschutz', home_url( '/datenschutz/' ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'leadwerk_theme_enqueue_assets' );

/** @return array<string,string> */
function leadwerk_theme_page_map() {
	return array(
		'index.html'                    => 'trend-home',
		'home'                          => 'trend-home',
		'danke.html'                    => 'trend-danke',
		'legal.html'                    => 'trend-legal',
		'rechtliches.html'              => 'trend-legal',
		'impressum.html'                => 'trend-impressum',
		'datenschutz.html'              => 'trend-datenschutz',
		'datenschutzerklaerung.html'    => 'trend-datenschutz',
	);
}

/** Preserve the established legacy privacy URL. @return void */
function leadwerk_theme_legacy_legal_redirect() {
	if ( ! is_404() ) {
		return;
	}
	global $wp;
	if ( 'datenschutzerklaerung' === trim( (string) ( $wp->request ?? '' ), '/' ) ) {
		wp_safe_redirect( leadwerk_theme_page_url( 'trend-datenschutz', home_url( '/datenschutz/' ) ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'leadwerk_theme_legacy_legal_redirect' );

/** @param string $source_key Source key. @return int */
function leadwerk_theme_get_page_id( $source_key ) {
	$source_key = sanitize_key( (string) $source_key );
	if ( class_exists( 'Leadwerk_Translation_API' ) ) {
		$lang = Leadwerk_Translation_API::get_current_request_language();
		$lang = $lang ?: Leadwerk_Translation_API::get_default_language();
		$id   = Leadwerk_Translation_API::get_post_by_source_key( $source_key, $lang, 'page' );
		if ( $id ) {
			return (int) $id;
		}
	}
	$query = new WP_Query( array( 'post_type' => 'page', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => 'leadwerk_source_key', 'meta_value' => $source_key ) );
	return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
}

/** @param string $source_key Key. @param string $fallback Fallback. @return string */
function leadwerk_theme_page_url( $source_key, $fallback = '' ) {
	$id = leadwerk_theme_get_page_id( $source_key );
	return $id ? get_permalink( $id ) : ( $fallback ?: home_url( '/' ) );
}

/** @param string $anchor Anchor without #. @return string */
function leadwerk_theme_home_anchor( $anchor = '' ) {
	return leadwerk_theme_page_url( 'trend-home', home_url( '/' ) ) . ( $anchor ? '#' . ltrim( $anchor, '#' ) : '' );
}

/** @param string $source_key Key. @return bool */
function leadwerk_theme_is_source( $source_key ) {
	return is_singular() && sanitize_key( (string) get_post_meta( get_queried_object_id(), 'leadwerk_source_key', true ) ) === sanitize_key( $source_key );
}

/** Resolve static HTML references to language-aware WordPress permalinks. @param string $href Original URL. @return string */
function leadwerk_theme_resolve_url( $href ) {
	$href = trim( html_entity_decode( (string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( '' === $href || '#' === $href[0] || preg_match( '~^(?:https?:)?//|^(?:mailto|tel|data|blob):~i', $href ) ) {
		return $href;
	}
	$parts    = wp_parse_url( $href );
	$path     = isset( $parts['path'] ) ? ltrim( (string) $parts['path'], './' ) : '';
	$fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
	$query    = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
	$map      = leadwerk_theme_page_map();
	if ( isset( $map[ $path ] ) ) {
		return leadwerk_theme_page_url( $map[ $path ] ) . $query . $fragment;
	}
	if ( '' !== $path && preg_match( '~^([a-z0-9-]+)/?$~i', $path, $match ) ) {
		$file_key = $match[1] . '.html';
		if ( isset( $map[ $file_key ] ) ) {
			return leadwerk_theme_page_url( $map[ $file_key ] ) . $query . $fragment;
		}
		return trailingslashit( home_url( '/' . sanitize_title( $match[1] ) ) ) . ltrim( $query . $fragment, '/' );
	}
	return $href;
}

/** @param string $path Relative asset. @return string */
function leadwerk_theme_asset_url( $path ) {
	$path = ltrim( str_replace( '\\', '/', (string) $path ), '/' );
	if ( 0 === strpos( $path, 'assets/' ) ) {
		$path = substr( $path, 7 );
	}
	return LEADWERK_THEME_URI . '/assets/' . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
}

/** @param string $name Name. @param mixed $default Default. @return mixed */
function leadwerk_theme_option( $name, $default = '' ) {
	$value = function_exists( 'get_field' ) ? get_field( $name, 'option' ) : get_option( 'leadwerk_opt_' . $name, '' );
	return null === $value || '' === $value ? $default : $value;
}

/** @param string $name Name. @param string $fallback Fallback. @return string */
function leadwerk_theme_option_image( $name, $fallback ) {
	$id  = absint( leadwerk_theme_option( $name, 0 ) );
	$url = $id ? wp_get_attachment_image_url( $id, 'full' ) : '';
	return $url ?: leadwerk_theme_asset_url( $fallback );
}

/** @return string */
function leadwerk_theme_logo_dark() {
	return leadwerk_theme_option_image( 'logo_dark_id', 'logo.webp' );
}

/** @return string */
function leadwerk_theme_logo_light() {
	return leadwerk_theme_option_image( 'logo_light_id', 'logo_negativ.webp' );
}

/** @return void */
function leadwerk_theme_meta_description() {
	if ( ! is_singular() ) {
		return;
	}
	$description = trim( (string) get_post_meta( get_queried_object_id(), 'leadwerk_meta_description', true ) );
	if ( '' !== $description && ! defined( 'WPSEO_VERSION' ) ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'leadwerk_theme_meta_description', 2 );

/** @return void */
function leadwerk_theme_favicons() {
	if ( ! has_site_icon() ) {
		echo '<link rel="icon" href="' . esc_url( leadwerk_theme_asset_url( 'logo.webp' ) ) . '" type="image/webp">' . "\n";
	}
}
add_action( 'wp_head', 'leadwerk_theme_favicons', 3 );

/** @param array<int,string> $classes Classes. @return array<int,string> */
function leadwerk_theme_body_classes( $classes ) {
	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$key     = sanitize_html_class( (string) get_post_meta( $post_id, 'leadwerk_source_key', true ) );
		if ( $key ) {
			$classes[] = 'leadwerk-' . $key;
		}
		foreach ( preg_split( '/\s+/', (string) get_post_meta( $post_id, 'leadwerk_body_classes', true ) ) as $class ) {
			$class = sanitize_html_class( $class );
			if ( $class ) {
				$classes[] = $class;
			}
		}
	}
	return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'leadwerk_theme_body_classes' );

/** @param string $title Title. @return string */
function leadwerk_theme_document_title( $title ) {
	if ( is_singular() ) {
		$custom = trim( (string) get_post_meta( get_queried_object_id(), 'leadwerk_document_title', true ) );
		if ( $custom ) {
			return $custom;
		}
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'leadwerk_theme_document_title' );

/** Keep the WPForms confirmation portable across domains/subfolders. */
function leadwerk_theme_wpforms_danke_redirect( $url, $form_id, $fields, $form_data ) {
	$title = (string) ( $form_data['settings']['form_title'] ?? '' );
	if ( 'Trend in Form Projektanfrage' === $title ) {
		return leadwerk_theme_page_url( 'trend-danke', home_url( '/danke/' ) );
	}
	return $url;
}
add_filter( 'wpforms_process_redirect_url', 'leadwerk_theme_wpforms_danke_redirect', 10, 4 );
