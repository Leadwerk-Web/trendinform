<?php
/** Theme header. @package Leadwerk_Theme */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$is_home   = leadwerk_theme_is_source( 'trend-home' ) || is_front_page();
$nav_class = $is_home ? '' : ' is-scrolled legal-nav';
$phone     = (string) leadwerk_theme_option( 'company_phone', '07248 927 30 50' );
$phone_url = (string) leadwerk_theme_option( 'company_phone_link', 'tel:+4972489273050' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="nav<?php echo esc_attr( $nav_class ); ?>" id="nav">
	<div class="nav__inner">
		<a href="<?php echo esc_url( leadwerk_theme_home_anchor() ); ?>" class="nav__logo" aria-label="Trend in Form Startseite">
			<img src="<?php echo esc_url( leadwerk_theme_logo_light() ); ?>" alt="Trend in Form GmbH | Licht, Spanndecken, Akustik" class="nav__logo--dark">
			<img src="<?php echo esc_url( leadwerk_theme_logo_dark() ); ?>" alt="" aria-hidden="true" class="nav__logo--light">
		</a>
		<nav class="nav__links" id="navLinks" aria-label="Hauptnavigation">
			<a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'leistungen' ) ); ?>"><?php echo esc_html( (string) leadwerk_theme_option( 'nav_spanndecken_label', 'Spanndecken' ) ); ?></a>
			<a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'lichtlinie' ) ); ?>"><?php echo esc_html( (string) leadwerk_theme_option( 'nav_lichtlinie_label', 'Lichtlinie' ) ); ?></a>
			<a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'vergleich' ) ); ?>"><?php echo esc_html( (string) leadwerk_theme_option( 'nav_compare_label', 'Vorher Nachher' ) ); ?></a>
			<a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'referenzen' ) ); ?>"><?php echo esc_html( (string) leadwerk_theme_option( 'nav_references_label', 'Referenzen' ) ); ?></a>
			<a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'ablauf' ) ); ?>"><?php echo esc_html( (string) leadwerk_theme_option( 'nav_process_label', 'Ablauf' ) ); ?></a>
			<a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'b2b' ) ); ?>"><?php echo esc_html( (string) leadwerk_theme_option( 'nav_b2b_label', 'Objekt und Gewerbe' ) ); ?></a>
			<?php if ( class_exists( 'Leadwerk_Language_Switcher' ) ) : ?><?php echo Leadwerk_Language_Switcher::render( array( 'show_labels' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
		</nav>
		<div class="nav__actions">
			<a href="<?php echo esc_url( $phone_url ); ?>" class="nav__phone"><span><?php echo esc_html( $phone ); ?></span></a>
			<a href="<?php echo esc_url( leadwerk_theme_home_anchor( 'kontakt' ) ); ?>" class="btn btn--primary btn--nav"><?php echo esc_html( (string) leadwerk_theme_option( 'nav_cta_label', 'Termin vor Ort anfragen' ) ); ?></a>
			<button class="nav__burger" id="burger" aria-label="Menü öffnen" aria-expanded="false" aria-controls="navLinks"><span></span><span></span><span></span></button>
		</div>
	</div>
</header>
