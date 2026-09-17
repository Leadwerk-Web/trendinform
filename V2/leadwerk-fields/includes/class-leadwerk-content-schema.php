<?php
/**
 * Generic content schema for Trend in Form pages.
 *
 * The importer stores three repeaters in one native post-meta value. Keeping
 * text, links and media separate lets the translation plugin translate copy
 * while sharing URLs and attachment IDs between languages.
 *
 * @package Leadwerk_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Content_Schema {

	const FIELD_NAME = 'leadwerk_content_fields';
	const VERSION    = 3;

	/**
	 * Return the ACF-like group used by Leadwerk WPML Clone.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_group_definition() {
		return array(
			'label'      => __( 'Individuelle Seiteninhalte', 'leadwerk-fields' ),
			'field_name' => self::FIELD_NAME,
			'fields'     => array(
				'schema_version' => array( 'label' => 'Schema version', 'type' => 'number' ),
				'text_items'     => array(
					'label'  => __( 'Texte', 'leadwerk-fields' ),
					'type'   => 'repeater',
					'fields' => array(
						'key'     => array( 'label' => 'Key', 'type' => 'text' ),
						'label'   => array( 'label' => 'Label', 'type' => 'text' ),
						'group'   => array( 'label' => 'Group', 'type' => 'text' ),
						'content' => array( 'label' => 'Content', 'type' => 'wysiwyg' ),
					),
				),
				'link_items'     => array(
					'label'  => __( 'Links', 'leadwerk-fields' ),
					'type'   => 'repeater',
					'fields' => array(
						'key'   => array( 'label' => 'Key', 'type' => 'text' ),
						'label' => array( 'label' => 'Label', 'type' => 'text' ),
						'group' => array( 'label' => 'Group', 'type' => 'text' ),
						'href'  => array( 'label' => 'URL', 'type' => 'url' ),
					),
				),
				'media_items'    => array(
					'label'  => __( 'Bilder', 'leadwerk-fields' ),
					'type'   => 'repeater',
					'fields' => array(
						'key'           => array( 'label' => 'Key', 'type' => 'text' ),
						'label'         => array( 'label' => 'Label', 'type' => 'text' ),
						'group'         => array( 'label' => 'Group', 'type' => 'text' ),
						'kind'          => array( 'label' => 'Kind', 'type' => 'text' ),
						'attribute'     => array( 'label' => 'HTML attribute', 'type' => 'text' ),
						'source_path'   => array( 'label' => 'Source', 'type' => 'text' ),
						'attachment_id' => array( 'label' => 'Image', 'type' => 'image' ),
						'alt'           => array( 'label' => 'Alternative text', 'type' => 'text' ),
					),
				),
			),
		);
	}

	/**
	 * Compatibility method for importer and translation plugin.
	 *
	 * @param string $field_name Field name.
	 * @return array<string,mixed>|null
	 */
	public static function get_group( $field_name ) {
		return self::FIELD_NAME === (string) $field_name ? self::get_group_definition() : null;
	}

	/** @return array<string,array<string,mixed>> */
	public static function get_groups() {
		return array( self::FIELD_NAME => self::get_group_definition() );
	}

	/**
	 * Imported pages always use the generic group.
	 *
	 * @param int|WP_Post $post Post or ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_group_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return null;
		}
		$source_key = (string) get_post_meta( $post->ID, 'leadwerk_source_key', true );
		$payload    = get_post_meta( $post->ID, self::FIELD_NAME, true );
		return ( '' !== $source_key || ! empty( $payload ) ) ? self::get_group_definition() : null;
	}

	/** @return array<string,mixed> */
	public static function empty_payload() {
		return array(
			'schema_version' => self::VERSION,
			'text_items'     => array(),
			'link_items'     => array(),
			'media_items'    => array(),
		);
	}

	/**
	 * Normalize stored/imported data without losing stable keys.
	 *
	 * @param mixed $payload Raw payload.
	 * @return array<string,mixed>
	 */
	public static function normalize_payload( $payload ) {
		$out = self::empty_payload();
		if ( ! is_array( $payload ) ) {
			return $out;
		}

		foreach ( array( 'text_items', 'link_items', 'media_items' ) as $bucket ) {
			$rows = isset( $payload[ $bucket ] ) && is_array( $payload[ $bucket ] ) ? $payload[ $bucket ] : array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$key = sanitize_key( (string) ( $row['key'] ?? '' ) );
				if ( '' === $key ) {
					continue;
				}
				$base = array(
					'key'   => $key,
					'label' => sanitize_text_field( (string) ( $row['label'] ?? $key ) ),
					'group' => sanitize_text_field( (string) ( $row['group'] ?? __( 'Allgemein', 'leadwerk-fields' ) ) ),
				);

				if ( 'text_items' === $bucket ) {
					$base['content'] = self::sanitize_content_html( (string) ( $row['content'] ?? '' ) );
				} elseif ( 'link_items' === $bucket ) {
					$base['href'] = self::sanitize_link_value( (string) ( $row['href'] ?? '' ) );
				} else {
					$base['kind']          = in_array( (string) ( $row['kind'] ?? '' ), array( 'image', 'background', 'poster', 'video' ), true ) ? (string) $row['kind'] : 'image';
					$base['attribute']     = sanitize_key( (string) ( $row['attribute'] ?? ( 'background' === $base['kind'] ? 'style' : 'src' ) ) );
					$base['source_path']   = sanitize_text_field( (string) ( $row['source_path'] ?? '' ) );
					$base['attachment_id'] = absint( $row['attachment_id'] ?? 0 );
					$base['alt']           = sanitize_text_field( (string) ( $row['alt'] ?? '' ) );
				}
				$out[ $bucket ][] = $base;
			}
		}

		return $out;
	}

	/**
	 * Sanitize editable rich text while retaining the source site's inline icons.
	 *
	 * WordPress' default post allowlist removes SVG markup. The imported static
	 * pages use a deliberately small, URL-free SVG subset for decorative icons,
	 * so extend that allowlist without permitting scripts, event handlers or
	 * foreign content.
	 *
	 * @param string $html Rich-text HTML.
	 * @return string
	 */
	public static function sanitize_content_html( $html ) {
		$allowed          = wp_kses_allowed_html( 'post' );
		$paint_attributes = array(
			'class'            => true,
			'fill'             => true,
			'fill-rule'        => true,
			'opacity'          => true,
			'stroke'           => true,
			'stroke-dasharray' => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'stroke-width'     => true,
			'transform'        => true,
			'vector-effect'    => true,
		);
		$allowed['svg'] = array_merge(
			$paint_attributes,
			array(
				'aria-hidden'         => true,
				'focusable'           => true,
				'height'              => true,
				'preserveaspectratio' => true,
				'role'                => true,
				'viewbox'             => true,
				'width'               => true,
				'xmlns'               => true,
			)
		);
		$allowed['g']        = $paint_attributes;
		$allowed['path']     = array_merge( $paint_attributes, array( 'd' => true, 'pathlength' => true ) );
		$allowed['rect']     = array_merge( $paint_attributes, array( 'height' => true, 'rx' => true, 'ry' => true, 'width' => true, 'x' => true, 'y' => true ) );
		$allowed['circle']   = array_merge( $paint_attributes, array( 'cx' => true, 'cy' => true, 'r' => true ) );
		$allowed['ellipse']  = array_merge( $paint_attributes, array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ) );
		$allowed['line']     = array_merge( $paint_attributes, array( 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true ) );
		$allowed['polyline'] = array_merge( $paint_attributes, array( 'points' => true ) );
		$allowed['polygon'] = array_merge( $paint_attributes, array( 'points' => true ) );
		$allowed['title']    = array();
		$allowed['desc']     = array();

		return wp_kses( (string) $html, $allowed );
	}

	/**
	 * Keep WordPress-internal references, anchors, phone and mail links intact.
	 *
	 * @param string $value Link value.
	 * @return string
	 */
	public static function sanitize_link_value( $value ) {
		$value = trim( wp_unslash( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '~^(?:#|/|\./|\.\./|[a-z0-9-]+\.html(?:[#?].*)?$)~i', $value ) ) {
			return sanitize_text_field( $value );
		}
		return esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/** @param array<string,mixed> $definition Definition. @return mixed */
	public static function get_default_value( $definition ) {
		$type = (string) ( $definition['type'] ?? 'text' );
		if ( in_array( $type, array( 'repeater', 'gallery', 'select_options' ), true ) ) {
			return array();
		}
		if ( 'image' === $type || 'number' === $type ) {
			return 0;
		}
		if ( 'checkbox' === $type ) {
			return false;
		}
		return '';
	}

	/** @param string $html Heading HTML. @return string */
	public static function sanitize_heading_html( $html ) {
		return wp_kses( (string) $html, array( 'br' => array(), 'strong' => array(), 'em' => array(), 'span' => array( 'class' => true ) ) );
	}
}
