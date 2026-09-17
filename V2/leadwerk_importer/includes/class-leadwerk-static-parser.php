<?php
/**
 * Convert one static Trend in Form HTML document into a render template and
 * individually editable native fields.
 *
 * @package Leadwerk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Static_Parser {

	/** @var string */
	private $source_key;

	/** @var string */
	private $source_file;

	/** @var DOMDocument */
	private $dom;

	/** @var DOMXPath */
	private $xpath;

	/** @var DOMElement|null */
	private $body;

	/** @param string $source_key Source key. @param string $source_file Source file. */
	public function __construct( $source_key, $source_file ) {
		$this->source_key  = sanitize_key( (string) $source_key );
		$this->source_file = (string) $source_file;
	}

	/**
	 * Parse an HTML file.
	 *
	 * @param string $absolute_path File path.
	 * @return array<string,mixed>|WP_Error
	 */
	public function parse_file( $absolute_path ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'leadwerk_dom_missing', 'PHP DOM extension is required.' );
		}
		$html = file_get_contents( $absolute_path );
		if ( false === $html || '' === trim( $html ) ) {
			return new WP_Error( 'leadwerk_source_unreadable', 'Source HTML is empty: ' . $this->source_file );
		}

		$this->dom = new DOMDocument( '1.0', 'UTF-8' );
		$previous  = libxml_use_internal_errors( true );
		$this->dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$this->xpath = new DOMXPath( $this->dom );
		$this->body  = $this->dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $this->body instanceof DOMElement ) {
			return new WP_Error( 'leadwerk_source_body_missing', 'No body element in ' . $this->source_file );
		}

		$title            = $this->first_node_text( '//title' );
		$meta_description = '';
		$meta             = $this->xpath->query( '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]' )->item( 0 );
		if ( $meta instanceof DOMElement ) {
			$meta_description = trim( $meta->getAttribute( 'content' ) );
		}

		$this->remove_chrome();
		$this->replace_forms();
		$payload = Leadwerk_Content_Schema::empty_payload();
		$this->collect_text_fields( $payload );
		$this->collect_link_fields( $payload );
		$this->collect_media_fields( $payload );

		return array(
			'document_title'   => $title,
			'meta_description' => $meta_description,
			'body_classes'     => sanitize_text_field( $this->body->getAttribute( 'class' ) ),
			'template_html'    => $this->inner_html( $this->body ),
			'fields'           => Leadwerk_Content_Schema::normalize_payload( $payload ),
		);
	}

	/** @return void */
	private function remove_chrome() {
		$queries = array(
			'//nav[contains(concat(" ", normalize-space(@class), " "), " main-nav ")]',
			'//header[contains(concat(" ", normalize-space(@class), " "), " nav ")]',
			'//footer[contains(concat(" ", normalize-space(@class), " "), " footer ")]',
			'//*[contains(concat(" ", normalize-space(@class), " "), " floating-cta ")]',
			'//*[contains(concat(" ", normalize-space(@class), " "), " sticky-cta ")]',
			'//*[contains(concat(" ", normalize-space(@class), " "), " wa-float ")]',
			'//*[contains(concat(" ", normalize-space(@class), " "), " custom-cursor ")]',
			'//script',
		);
		foreach ( $queries as $query ) {
			$nodes = $this->xpath->query( $query );
			foreach ( iterator_to_array( $nodes ) as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}
	}

	/** @return void */
	private function replace_forms() {
		$nodes = $this->xpath->query( '//*[@data-wpforms-replace]' );
		foreach ( iterator_to_array( $nodes ) as $node ) {
			if ( ! $node instanceof DOMElement || ! $node->parentNode ) {
				continue;
			}
			$slot = $this->dom->createElement( 'div' );
			$slot->setAttribute( 'class', 'leadwerk-wpforms-slot' );
			$slot->setAttribute( 'data-leadwerk-wpforms', sanitize_key( $node->getAttribute( 'data-wpforms-replace' ) ?: 'contact' ) );
			$node->parentNode->replaceChild( $slot, $node );
		}
	}

	/** @param array<string,mixed> $payload Payload. @return void */
	private function collect_text_fields( &$payload ) {
		$nodes = $this->xpath->query( '//body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::p or self::li or self::button or self::label or self::figcaption or self::strong or self::em or self::span]' );
		foreach ( iterator_to_array( $nodes ) as $node ) {
			if ( ! $node instanceof DOMElement || $this->has_text_field_ancestor( $node ) || $this->is_decorative_text_node( $node ) ) {
				continue;
			}
			$content = trim( $this->inner_html( $node ) );
			$text    = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
			if ( '' === $text || '' === $content ) {
				continue;
			}
			$key = $this->stable_key( 'text', $node );
			$node->setAttribute( 'data-leadwerk-text', $key );
			$payload['text_items'][] = array(
				'key'     => $key,
				'label'   => $this->field_label( $node, $text ),
				'group'   => $this->group_label( $node ),
				'content' => $content,
			);
		}
	}

	/** @param array<string,mixed> $payload Payload. @return void */
	private function collect_link_fields( &$payload ) {
		$nodes = $this->xpath->query( '//body//a[@href]' );
		foreach ( iterator_to_array( $nodes ) as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$href = trim( $node->getAttribute( 'href' ) );
			if ( '' === $href ) {
				continue;
			}
			$key = $this->stable_key( 'link', $node );
			$node->setAttribute( 'data-leadwerk-link', $key );
			$text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
			$payload['link_items'][] = array(
				'key'   => $key,
				'label' => $this->field_label( $node, $text ?: $href ),
				'group' => $this->group_label( $node ),
				'href'  => $href,
			);
		}
	}

	/** @param array<string,mixed> $payload Payload. @return void */
	private function collect_media_fields( &$payload ) {
		$attribute_specs = array(
			array( 'query' => '//body//img[@src]', 'attribute' => 'src', 'kind' => 'image' ),
			array( 'query' => '//body//video[@poster]', 'attribute' => 'poster', 'kind' => 'poster' ),
			array( 'query' => '//body//video[@src] | //body//source[@src]', 'attribute' => 'src', 'kind' => 'video' ),
			array( 'query' => '//body//*[@data-vorher]', 'attribute' => 'data-vorher', 'kind' => 'image' ),
			array( 'query' => '//body//*[@data-nachher]', 'attribute' => 'data-nachher', 'kind' => 'image' ),
		);

		foreach ( $attribute_specs as $spec ) {
			$nodes = $this->xpath->query( $spec['query'] );
			foreach ( iterator_to_array( $nodes ) as $node ) {
				if ( ! $node instanceof DOMElement ) {
					continue;
				}
				$attribute = (string) $spec['attribute'];
				$source    = $this->normalize_source_path( $node->getAttribute( $attribute ) );
				if ( '' === $source || $this->is_external_url( $source ) ) {
					continue;
				}
				$marker = 'data-leadwerk-media-' . sanitize_key( str_replace( '_', '-', $attribute ) );
				$key    = $this->stable_key( 'media-' . $attribute, $node );
				$node->setAttribute( $marker, $key );
				$payload['media_items'][] = array(
					'key'           => $key,
					'label'         => $this->field_label( $node, $node->getAttribute( 'alt' ) ?: wp_basename( $source ) ),
					'group'         => $this->group_label( $node ),
					'kind'          => (string) $spec['kind'],
					'attribute'     => $attribute,
					'source_path'   => $source,
					'attachment_id' => 0,
					'alt'           => 'img' === strtolower( $node->tagName ) ? $node->getAttribute( 'alt' ) : '',
				);
			}
		}

		$styled = $this->xpath->query( '//body//*[@style and contains(translate(@style,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"background-image")]' );
		foreach ( iterator_to_array( $styled ) as $node ) {
			if ( ! $node instanceof DOMElement || ! preg_match( '~background-image\s*:\s*url\([\'\"]?([^\'\")]+)~i', $node->getAttribute( 'style' ), $match ) ) {
				continue;
			}
			$source = $this->normalize_source_path( $match[1] );
			if ( '' === $source || $this->is_external_url( $source ) ) {
				continue;
			}
			$key = $this->stable_key( 'background', $node );
			$node->setAttribute( 'data-leadwerk-background', $key );
			$payload['media_items'][] = array(
				'key'           => $key,
				'label'         => $this->field_label( $node, wp_basename( $source ) ),
				'group'         => $this->group_label( $node ),
				'kind'          => 'background',
				'attribute'     => 'style',
				'source_path'   => $source,
				'attachment_id' => 0,
				'alt'           => '',
			);
		}
	}

	/** @param DOMElement $node Node. @return bool */
	private function has_text_field_ancestor( $node ) {
		$parent = $node->parentNode;
		while ( $parent instanceof DOMElement && $parent !== $this->body ) {
			if ( $parent->hasAttribute( 'data-leadwerk-text' ) ) {
				return true;
			}
			$parent = $parent->parentNode;
		}
		return false;
	}

	/** @param DOMElement $node Node. @return bool */
	private function is_decorative_text_node( $node ) {
		$class = ' ' . preg_replace( '/\s+/', ' ', $node->getAttribute( 'class' ) ) . ' ';
		return false !== strpos( $class, ' progress-dot ' )
			|| false !== strpos( $class, ' count ' )
			|| $node->hasAttribute( 'aria-hidden' )
			|| $node->hasAttribute( 'data-slide' );
	}

	/** @param DOMElement $node Node. @return string */
	private function group_label( $node ) {
		$current = $node;
		while ( $current instanceof DOMElement && $current !== $this->body ) {
			if ( 'section' === strtolower( $current->tagName ) || 'main' === strtolower( $current->tagName ) ) {
				$id = trim( $current->getAttribute( 'id' ) );
				if ( '' !== $id ) {
					return ucwords( str_replace( array( '-', '_' ), ' ', $id ) );
				}
				$class = trim( preg_replace( '/\s+.*/', '', $current->getAttribute( 'class' ) ) );
				if ( '' !== $class ) {
					return ucwords( str_replace( array( '-', '_' ), ' ', $class ) );
				}
			}
			$current = $current->parentNode;
		}
		return __( 'Allgemein', 'leadwerk-importer' );
	}

	/** @param DOMElement $node Node. @param string $text Text. @return string */
	private function field_label( $node, $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 58 ) {
			$text = mb_substr( $text, 0, 55 ) . '…';
		} elseif ( strlen( $text ) > 58 ) {
			$text = substr( $text, 0, 55 ) . '…';
		}
		return strtoupper( $node->tagName ) . ' · ' . $text;
	}

	/** @param string $prefix Prefix. @param DOMElement $node Node. @return string */
	private function stable_key( $prefix, $node ) {
		return sanitize_key( $prefix . '-' . substr( md5( $this->source_key . '|' . $this->dom_path( $node ) ), 0, 12 ) );
	}

	/** @param DOMElement $node Node. @return string */
	private function dom_path( $node ) {
		$parts   = array();
		$current = $node;
		while ( $current instanceof DOMElement && $current !== $this->body ) {
			$index   = 1;
			$sibling = $current->previousSibling;
			while ( $sibling ) {
				if ( $sibling instanceof DOMElement && $sibling->tagName === $current->tagName ) {
					++$index;
				}
				$sibling = $sibling->previousSibling;
			}
			array_unshift( $parts, strtolower( $current->tagName ) . '[' . $index . ']' );
			$current = $current->parentNode;
		}
		return implode( '/', $parts );
	}

	/** @param string $query XPath. @return string */
	private function first_node_text( $query ) {
		$node = $this->xpath->query( $query )->item( 0 );
		return $node ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : '';
	}

	/** @param DOMNode $node Node. @return string */
	private function inner_html( $node ) {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $this->dom->saveHTML( $child );
		}
		return $html;
	}

	/** @param string $path Path. @return string */
	private function normalize_source_path( $path ) {
		$path = rawurldecode( trim( html_entity_decode( (string) $path, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$path = preg_replace( '~^\./~', '', $path );
		return ltrim( str_replace( '\\', '/', $path ), '/' );
	}

	/** @param string $url URL. @return bool */
	private function is_external_url( $url ) {
		return (bool) preg_match( '~^(?:https?:)?//|^(?:data|blob):~i', (string) $url );
	}
}
