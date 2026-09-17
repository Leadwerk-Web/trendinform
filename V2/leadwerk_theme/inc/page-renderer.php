<?php
/**
 * Render imported HTML shells from individual Leadwerk fields.
 *
 * @package Leadwerk_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @param DOMDocument $dom DOM. @param DOMNode $node Node. @param string $html HTML. @return void */
function leadwerk_theme_set_inner_html( $dom, $node, $html ) {
	while ( $node->firstChild ) {
		$node->removeChild( $node->firstChild );
	}
	$tmp = new DOMDocument( '1.0', 'UTF-8' );
	$old = libxml_use_internal_errors( true );
	$tmp->loadHTML( '<?xml encoding="UTF-8"><div id="leadwerk-fragment">' . (string) $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	$container = ( new DOMXPath( $tmp ) )->query( '//*[@id="leadwerk-fragment"]' )->item( 0 );
	if ( ! $container ) {
		return;
	}
	foreach ( iterator_to_array( $container->childNodes ) as $child ) {
		$node->appendChild( $dom->importNode( $child, true ) );
	}
}

/** @param DOMDocument $dom DOM. @param DOMNode $node Node. @return string */
function leadwerk_theme_inner_html( $dom, $node ) {
	$html = '';
	foreach ( $node->childNodes as $child ) {
		$html .= $dom->saveHTML( $child );
	}
	return $html;
}

/** @param array<int,array<string,mixed>> $rows Rows. @return array<string,array<string,mixed>> */
function leadwerk_theme_index_fields( $rows ) {
	$out = array();
	foreach ( $rows as $row ) {
		if ( is_array( $row ) && ! empty( $row['key'] ) ) {
			$out[ (string) $row['key'] ] = $row;
		}
	}
	return $out;
}

/** @param string $html Editable content HTML. @return string */
function leadwerk_theme_sanitize_content_html( $html ) {
	if ( class_exists( 'Leadwerk_Content_Schema' ) && is_callable( array( 'Leadwerk_Content_Schema', 'sanitize_content_html' ) ) ) {
		return Leadwerk_Content_Schema::sanitize_content_html( $html );
	}
	return wp_kses_post( (string) $html );
}

/** @param int $post_id Post ID. @return string */
function leadwerk_theme_render_page( $post_id ) {
	$template = (string) get_post_meta( $post_id, 'leadwerk_page_template_html', true );
	if ( '' === trim( $template ) ) {
		return apply_filters( 'the_content', (string) get_post_field( 'post_content', $post_id ) );
	}
	$fields = function_exists( 'get_field' ) ? get_field( 'leadwerk_content_fields', $post_id ) : get_post_meta( $post_id, 'leadwerk_content_fields', true );
	if ( class_exists( 'Leadwerk_Content_Schema' ) ) {
		$fields = Leadwerk_Content_Schema::normalize_payload( $fields );
	}
	$fields = is_array( $fields ) ? $fields : array();
	$text   = leadwerk_theme_index_fields( (array) ( $fields['text_items'] ?? array() ) );
	$links  = leadwerk_theme_index_fields( (array) ( $fields['link_items'] ?? array() ) );
	$media  = leadwerk_theme_index_fields( (array) ( $fields['media_items'] ?? array() ) );

	$dom = new DOMDocument( '1.0', 'UTF-8' );
	$old = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8"><div id="leadwerk-page-root">' . $template . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	$xpath = new DOMXPath( $dom );
	$root  = $xpath->query( '//*[@id="leadwerk-page-root"]' )->item( 0 );
	if ( ! $root ) {
		return '';
	}

	foreach ( iterator_to_array( $xpath->query( '//*[@data-leadwerk-text]' ) ) as $node ) {
		$key = $node->getAttribute( 'data-leadwerk-text' );
		if ( isset( $text[ $key ] ) ) {
			leadwerk_theme_set_inner_html( $dom, $node, leadwerk_theme_sanitize_content_html( (string) $text[ $key ]['content'] ) );
		}
	}
	foreach ( iterator_to_array( $xpath->query( '//a[@data-leadwerk-link]' ) ) as $node ) {
		$key = $node->getAttribute( 'data-leadwerk-link' );
		if ( isset( $links[ $key ] ) ) {
			$node->setAttribute( 'href', leadwerk_theme_resolve_url( (string) $links[ $key ]['href'] ) );
		}
	}
	foreach ( $media as $key => $row ) {
		if ( 'background' === (string) ( $row['kind'] ?? '' ) ) {
			continue;
		}
		$attribute = sanitize_key( (string) ( $row['attribute'] ?? 'src' ) );
		$marker    = 'data-leadwerk-media-' . sanitize_key( str_replace( '_', '-', $attribute ) );
		$nodes     = $xpath->query( '//*[@' . $marker . '="' . $key . '"]' );
		if ( ! $nodes || 0 === $nodes->length ) {
			$nodes = $xpath->query( '//*[@data-leadwerk-media="' . $key . '"]' );
		}
		foreach ( iterator_to_array( $nodes ) as $node ) {
			$is_video = 'video' === (string) ( $row['kind'] ?? '' );
			$url      = ! empty( $row['attachment_id'] ) ? ( $is_video ? wp_get_attachment_url( (int) $row['attachment_id'] ) : wp_get_attachment_image_url( (int) $row['attachment_id'], 'full' ) ) : '';
			$url      = $url ?: leadwerk_theme_asset_url( (string) $row['source_path'] );
			$node->setAttribute( $attribute ?: 'src', $url );
			if ( 'img' === strtolower( $node->nodeName ) ) {
				$node->setAttribute( 'alt', (string) ( $row['alt'] ?? '' ) );
			}
			$node->removeAttribute( $marker );
			$node->removeAttribute( 'data-leadwerk-media' );
		}
	}
	foreach ( iterator_to_array( $xpath->query( '//*[@data-leadwerk-background]' ) ) as $node ) {
		$key = $node->getAttribute( 'data-leadwerk-background' );
		if ( ! isset( $media[ $key ] ) ) {
			continue;
		}
		$row   = $media[ $key ];
		$url   = ! empty( $row['attachment_id'] ) ? wp_get_attachment_image_url( (int) $row['attachment_id'], 'full' ) : '';
		$url   = $url ?: leadwerk_theme_asset_url( (string) $row['source_path'] );
		$style = (string) $node->getAttribute( 'style' );
		$style = preg_replace( '~background-image\s*:\s*url\([^\)]*\)~i', 'background-image:url("' . esc_url_raw( $url ) . '")', $style );
		$node->setAttribute( 'style', $style );
	}

	foreach ( iterator_to_array( $xpath->query( '//a[@href]' ) ) as $node ) {
		$node->setAttribute( 'href', leadwerk_theme_resolve_url( $node->getAttribute( 'href' ) ) );
	}
	foreach ( iterator_to_array( $xpath->query( '//*[@data-leadwerk-wpforms]' ) ) as $node ) {
		$form = trim( (string) leadwerk_theme_option( 'wpforms_form_id_de', '' ) );
		if ( preg_match( '/^\d+$/', $form ) ) {
			$form = '[wpforms id="' . absint( $form ) . '" title="false" description="false"]';
		}
		if ( '' !== $form && shortcode_exists( 'wpforms' ) ) {
			$form_markup = '<h3 class="leadwerk-wpforms-title">' . esc_html__( 'Kontaktformular', 'leadwerk-theme' ) . '</h3>' . do_shortcode( $form );
			leadwerk_theme_set_inner_html( $dom, $node, $form_markup );
		} else {
			$message = current_user_can( 'manage_options' ) ? 'WPForms Formular-ID unter Einstellungen → Trend in Form Website eintragen.' : 'Das Kontaktformular ist momentan nicht verfügbar. Bitte rufen Sie uns an oder schreiben Sie eine E-Mail.';
			leadwerk_theme_set_inner_html( $dom, $node, '<p class="leadwerk-form-notice">' . esc_html( $message ) . '</p>' );
		}
	}

	foreach ( iterator_to_array( $xpath->query( '//*[@data-leadwerk-text or @data-leadwerk-link or @data-leadwerk-media or @data-leadwerk-background]' ) ) as $node ) {
		foreach ( array( 'data-leadwerk-text', 'data-leadwerk-link', 'data-leadwerk-media', 'data-leadwerk-background' ) as $attribute ) {
			$node->removeAttribute( $attribute );
		}
	}
	return leadwerk_theme_inner_html( $dom, $root );
}
