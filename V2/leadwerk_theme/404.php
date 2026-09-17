<?php
/** Trend in Form 404 template. @package Leadwerk_Theme */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main class="status-main status-main--404">
	<section class="status-card" aria-labelledby="error-title">
		<span class="status-card__code" aria-hidden="true">404</span>
		<p class="eyebrow"><?php echo esc_html( (string) leadwerk_theme_option( 'not_found_eyebrow', 'Seite nicht gefunden' ) ); ?></p>
		<h1 id="error-title"><?php echo esc_html( (string) leadwerk_theme_option( 'not_found_title', 'Diese Lichtlinie führt gerade ins Leere.' ) ); ?></h1>
		<p class="status-card__lead"><?php echo esc_html( (string) leadwerk_theme_option( 'not_found_text', 'Die gesuchte Seite wurde verschoben, umbenannt oder existiert nicht mehr. Über die Startseite findest du schnell zurück zu unseren Leistungen, Referenzen und deinem Ansprechpartner.' ) ); ?></p>
		<div class="status-card__actions"><a href="<?php echo esc_url( leadwerk_theme_home_anchor() ); ?>" class="btn btn--primary"><?php echo esc_html( (string) leadwerk_theme_option( 'not_found_home_label', 'Zur Startseite' ) ); ?></a><a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'kontakt' ) ); ?>" class="btn btn--ghost"><?php echo esc_html( (string) leadwerk_theme_option( 'not_found_contact_label', 'Projekt anfragen' ) ); ?></a></div>
		<nav class="status-card__links" aria-label="Beliebte Bereiche"><a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'leistungen' ) ); ?>">Spanndecken</a><a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'lichtlinie' ) ); ?>">Lichtkonzepte</a><a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'referenzen' ) ); ?>">Referenzen</a><a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'faq' ) ); ?>">Häufige Fragen</a></nav>
	</section>
</main>
<?php get_footer();
