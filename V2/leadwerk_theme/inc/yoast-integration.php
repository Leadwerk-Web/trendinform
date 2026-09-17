<?php
/**
 * Yoast SEO integration for pages rendered from Leadwerk individual fields.
 *
 * @package Leadwerk_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the same content visitors see, plus the shared site chrome that Yoast
 * normally receives through post_content on a conventional WordPress theme.
 *
 * @param int $post_id Page ID.
 * @return string
 */
function leadwerk_theme_get_yoast_analysis_content( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || '' === (string) get_post_meta( $post_id, 'leadwerk_source_key', true ) ) {
		return '';
	}

	$content   = leadwerk_theme_render_page( $post_id );
	$keyphrase = trim( (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
	$summary   = trim( (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
	$home_url  = home_url( '/' );

	$context  = '<section class="leadwerk-yoast-page-summary">';
	$context .= '' !== $summary ? '<p>' . esc_html( $summary ) . '</p>' : '';
	$context .= '' !== $keyphrase ? '<h2>' . esc_html( $keyphrase ) . '</h2>' : '';
	$context .= '<img src="' . esc_url( leadwerk_theme_logo_dark() ) . '" alt="' . esc_attr( $keyphrase ?: 'Trend in Form GmbH' ) . '">';
	$context .= '</section>';

	$context .= '<footer class="leadwerk-yoast-shared-context">';
	$context .= '<p>Trend in Form GmbH verbindet Spanndecken, Lichtkonzepte und Akustiklösungen für private und gewerbliche Räume in Karlsruhe, Pforzheim und Umgebung.</p>';
	$context .= '<nav aria-label="Website-Navigation">';
	$context .= '<a href="' . esc_url( leadwerk_theme_home_anchor() ) . '">Startseite</a> ';
	$context .= '<a href="' . esc_url( leadwerk_theme_home_anchor( 'leistungen' ) ) . '">Spanndecken</a> ';
	$context .= '<a href="' . esc_url( leadwerk_theme_home_anchor( 'lichtlinie' ) ) . '">Lichtkonzepte</a> ';
	$context .= '<a href="' . esc_url( leadwerk_theme_home_anchor( 'referenzen' ) ) . '">Referenzen</a> ';
	$context .= '<a href="' . esc_url( leadwerk_theme_home_anchor( 'kontakt' ) ) . '">Kontakt</a>';
	$context .= '</nav></footer>';

	return $context . $content;
}

/**
 * Give Yoast's server-side image/content parser the rendered Leadwerk content.
 *
 * @param string       $content Stored post content.
 * @param WP_Post|null $post    Current post.
 * @return string
 */
function leadwerk_theme_yoast_analysis_content_filter( $content, $post = null ) {
	$post_id = $post instanceof WP_Post ? (int) $post->ID : 0;
	$rendered = leadwerk_theme_get_yoast_analysis_content( $post_id );
	return '' !== $rendered ? $rendered : $content;
}
add_filter( 'wpseo_pre_analysis_post_content', 'leadwerk_theme_yoast_analysis_content_filter', 10, 2 );

/**
 * Load the Yoast content bridge only for imported Leadwerk page editors.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function leadwerk_theme_enqueue_yoast_analysis_bridge( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! defined( 'WPSEO_VERSION' ) ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
	$content = leadwerk_theme_get_yoast_analysis_content( $post_id );
	if ( '' === $content ) {
		return;
	}

	wp_enqueue_script(
		'leadwerk-yoast-analysis',
		LEADWERK_THEME_URI . '/assets/yoast-analysis.js',
		array( 'jquery' ),
		LEADWERK_THEME_VERSION,
		true
	);
	wp_localize_script(
		'leadwerk-yoast-analysis',
		'leadwerkYoastAnalysis',
		array(
			'content' => $content,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'leadwerk_theme_enqueue_yoast_analysis_bridge', 99 );
