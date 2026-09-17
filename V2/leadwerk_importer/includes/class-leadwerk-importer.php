<?php
/**
 * Batched static-site importer for Trend in Form.
 *
 * @package Leadwerk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Importer {

	/** @var bool */
	private $apply;

	/** @var string */
	private $source_root;

	/** @var array<string,mixed> */
	private $manifest;

	/** @var Leadwerk_Media_Importer */
	private $media;

	/** @param bool $apply Apply changes rather than dry run. */
	public function __construct( $apply = false ) {
		$this->apply       = (bool) $apply;
		$this->source_root = trailingslashit( LEADWERK_IMPORTER_PATH . 'source_assets' );
		$this->manifest    = $this->load_manifest();
		$this->media       = new Leadwerk_Media_Importer( $this->source_root, ! $this->apply );
	}

	/** @return array<string,mixed> */
	private function load_manifest() {
		$path = LEADWERK_IMPORTER_PATH . 'manifest/mapping.json';
		$data = is_file( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array();
		return is_array( $data ) ? $data : array();
	}

	/** @return array<string,mixed> */
	public function build_initial_job_state() {
		$pages = array_values( (array) ( $this->manifest['pages'] ?? array() ) );
		$media = $this->collect_media_queue();
		$state = array(
			'job_id'          => wp_generate_uuid4(),
			'status'          => 'running',
			'dry_run'         => ! $this->apply,
			'current_step'    => 'preflight',
			'current_item'    => '',
			'processed'       => 0,
			'success_count'   => 0,
			'warning_count'   => 0,
			'error_count'     => 0,
			'started_at'      => current_time( 'mysql', true ),
			'finished_at'     => '',
			'steps'           => array(
				'preflight' => array( 'label' => 'Sistem ön kontrolü', 'total' => 1, 'processed' => 0, 'status' => 'pending' ),
				'media'     => array( 'label' => 'Medya Kütüphanesi', 'total' => count( $media ), 'processed' => 0, 'status' => 'pending' ),
				'pages'     => array( 'label' => 'Sayfalar ve bireysel alanlar', 'total' => count( $pages ), 'processed' => 0, 'status' => 'pending' ),
				'finalize'  => array( 'label' => 'WordPress ayarları ve doğrulama', 'total' => 1, 'processed' => 0, 'status' => 'pending' ),
			),
			'queues'          => array( 'pages' => $pages, 'media' => $media ),
			'cursor'          => array( 'media' => 0, 'pages' => 0 ),
			'results'         => array( 'pages' => array(), 'media' => array(), 'summary' => array(), 'blocking' => array() ),
			'log_tail'        => array(),
		);
		$this->add_log( $state, sprintf( 'Import hazır: %d sayfa, %d medya (%s).', count( $pages ), count( $media ), $this->apply ? 'LIVE' : 'DRY RUN' ) );
		Leadwerk_Logger::set_state( $state );
		return Leadwerk_Logger::get_state();
	}

	/**
	 * Run one bounded AJAX batch.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @return array<string,mixed>
	 */
	public function run_next_batch( $state ) {
		if ( empty( $state['job_id'] ) || ! in_array( (string) ( $state['status'] ?? '' ), array( 'running', 'booting' ), true ) ) {
			return $state;
		}
		if ( 'preflight' === (string) $state['current_step'] ) {
			$state = $this->run_preflight( $state );
		} elseif ( 'media' === (string) $state['current_step'] ) {
			$state = $this->run_media_batch( $state, 8 );
		} elseif ( 'pages' === (string) $state['current_step'] ) {
			$state = $this->run_page_batch( $state, 1 );
		} elseif ( 'finalize' === (string) $state['current_step'] ) {
			$state = $this->run_finalize( $state );
		}
		Leadwerk_Logger::set_state( $state );
		return Leadwerk_Logger::get_state();
	}

	/** @return array<string,mixed> */
	public function run() {
		$state = $this->build_initial_job_state();
		$guard = 0;
		while ( in_array( (string) ( $state['status'] ?? '' ), array( 'running', 'booting' ), true ) && $guard < 10000 ) {
			$state = $this->run_next_batch( $state );
			++$guard;
		}
		return $state;
	}

	/** @param array<string,mixed> $state State. @return array<string,mixed> */
	private function run_preflight( $state ) {
		$state['steps']['preflight']['status'] = 'running';
		$issues = array();
		$seen_keys = array();
		$seen_slugs = array();
		if ( ! class_exists( 'DOMDocument' ) ) {
			$issues[] = 'PHP DOM extension eksik.';
		}
		if ( ! class_exists( 'Leadwerk_Content_Schema' ) || ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			$issues[] = 'Leadwerk Fields aktif değil veya alan API’si yüklenemedi.';
		}
		if ( defined( 'ACF_VERSION' ) || defined( 'ACF_PRO' ) ) {
			$issues[] = 'Advanced Custom Fields aynı anda aktif; Leadwerk Fields ile depolama çakışması oluşur.';
		}
		if ( empty( $this->manifest['pages'] ) ) {
			$issues[] = 'mapping.json içinde sayfa yok.';
		}
		foreach ( (array) ( $this->manifest['pages'] ?? array() ) as $page ) {
			$key  = sanitize_key( (string) ( $page['source_key'] ?? '' ) );
			$slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );
			$file = $this->source_root . (string) ( $page['source_file'] ?? '' );
			if ( '' === $key || isset( $seen_keys[ $key ] ) ) {
				$issues[] = 'Eksik veya yinelenen source_key: ' . $key;
			}
			if ( '' === $slug || isset( $seen_slugs[ $slug ] ) ) {
				$issues[] = 'Eksik veya yinelenen slug: ' . $slug;
			}
			$seen_keys[ $key ] = true;
			$seen_slugs[ $slug ] = true;
			if ( ! is_file( $file ) ) {
				$issues[] = 'Eksik HTML: ' . (string) ( $page['source_file'] ?? '' );
				continue;
			}
			$parsed = ( new Leadwerk_Static_Parser( $key, (string) ( $page['source_file'] ?? '' ) ) )->parse_file( $file );
			if ( is_wp_error( $parsed ) ) {
				$issues[] = $parsed->get_error_message();
				continue;
			}
			foreach ( (array) ( $parsed['fields']['media_items'] ?? array() ) as $media ) {
				$path = (string) ( $media['source_path'] ?? '' );
				if ( '' !== $path && ! is_file( $this->source_root . $path ) ) {
					$issues[] = sprintf( '%s içinde eksik medya: %s', (string) ( $page['source_file'] ?? '' ), $path );
				}
			}
		}
		if ( ! is_file( $this->source_root . '404.html' ) ) {
			$issues[] = 'Eksik statik 404.html.';
		}
		if ( ! function_exists( 'leadwerk_theme_render_page' ) ) {
			$issues[] = 'Leadwerk Trend in Form teması etkin değil.';
		}

		$state['steps']['preflight']['processed'] = 1;
		if ( $issues ) {
			$state['steps']['preflight']['status'] = 'failed';
			$state['status'] = 'failed';
			$state['error_count'] += count( $issues );
			$state['results']['blocking'] = $issues;
			foreach ( $issues as $issue ) {
				$this->add_log( $state, $issue, 'error' );
			}
			return $state;
		}

		$state['steps']['preflight']['status'] = 'completed';
		$state['current_step'] = 'media';
		$state['steps']['media']['status'] = 'running';
		$this->add_log( $state, 'Ön kontrol başarılı. HTML, DOM ve alan bağımlılıkları hazır.' );
		return $state;
	}

	/** @param array<string,mixed> $state State. @param int $limit Limit. @return array<string,mixed> */
	private function run_media_batch( $state, $limit ) {
		$queue  = (array) ( $state['queues']['media'] ?? array() );
		$cursor = (int) ( $state['cursor']['media'] ?? 0 );
		$end    = min( count( $queue ), $cursor + max( 1, $limit ) );
		for ( $i = $cursor; $i < $end; ++$i ) {
			$path = (string) $queue[ $i ];
			$state['current_item'] = $path;
			if ( $this->apply ) {
				$id = $this->media->import_file( $path );
				if ( $id > 0 ) {
					$state['success_count']++;
					$state['results']['media'][ $path ] = $id;
					$this->add_log( $state, 'Medya hazır: ' . $path . ' → #' . $id );
				} else {
					$state['error_count']++;
					$state['results']['blocking'][] = 'Medya içe aktarılamadı: ' . $path;
					$this->add_log( $state, 'Medya içe aktarılamadı: ' . $path, 'error' );
				}
			} else {
				$state['success_count']++;
				$this->add_log( $state, '[DRY] Medya: ' . $path );
			}
			$state['steps']['media']['processed']++;
			$state['processed']++;
		}
		$state['cursor']['media'] = $end;
		if ( $end >= count( $queue ) ) {
			$state['steps']['media']['status'] = 'completed';
			$state['current_step'] = 'pages';
			$state['steps']['pages']['status'] = 'running';
			$this->add_log( $state, 'Medya adımı tamamlandı.' );
		}
		return $state;
	}

	/** @param array<string,mixed> $state State. @param int $limit Limit. @return array<string,mixed> */
	private function run_page_batch( $state, $limit ) {
		$queue  = (array) ( $state['queues']['pages'] ?? array() );
		$cursor = (int) ( $state['cursor']['pages'] ?? 0 );
		$end    = min( count( $queue ), $cursor + max( 1, $limit ) );
		for ( $i = $cursor; $i < $end; ++$i ) {
			$page = (array) $queue[ $i ];
			$key  = sanitize_key( (string) ( $page['source_key'] ?? '' ) );
			$state['current_item'] = (string) ( $page['source_file'] ?? $key );
			$result = $this->import_page( $page );
			if ( is_wp_error( $result ) ) {
				$state['error_count']++;
				$state['results']['pages'][ $key ] = array( 'title' => $page['title'] ?? $key, 'de' => array( 'field_status' => 'failed', 'failure_reason' => $result->get_error_message() ) );
				$this->add_log( $state, $result->get_error_message(), 'error' );
			} else {
				$state['success_count']++;
				$state['results']['pages'][ $key ] = array(
					'title' => $page['title'] ?? $key,
					'de'    => array( 'field_status' => $this->apply ? 'success' : 'dry-run', 'field_message' => sprintf( '%d text, %d link, %d media field', $result['text_count'], $result['link_count'], $result['media_count'] ) ),
					'en'    => array( 'translation_status' => class_exists( 'Leadwerk_Translation_API' ) ? 'ready-for-clone' : 'plugin-unavailable' ),
				);
				$this->add_log( $state, sprintf( '%s: %d ayrı alan hazır.', $page['title'] ?? $key, $result['field_count'] ) );
			}
			$state['steps']['pages']['processed']++;
			$state['processed']++;
		}
		$state['cursor']['pages'] = $end;
		if ( $end >= count( $queue ) ) {
			$state['steps']['pages']['status'] = 'completed';
			$state['current_step'] = 'finalize';
			$state['steps']['finalize']['status'] = 'running';
		}
		return $state;
	}

	/**
	 * Parse and upsert one page.
	 *
	 * @param array<string,mixed> $config Page config.
	 * @return array<string,int>|WP_Error
	 */
	private function import_page( $config ) {
		$source_key  = sanitize_key( (string) ( $config['source_key'] ?? '' ) );
		$source_file = (string) ( $config['source_file'] ?? '' );
		$parser      = new Leadwerk_Static_Parser( $source_key, $source_file );
		$parsed      = $parser->parse_file( $this->source_root . $source_file );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		$fields = $this->attach_media_ids( $parsed['fields'] );
		if ( $this->apply ) {
			$missing_media = array();
			foreach ( $fields['media_items'] as $media ) {
				if ( empty( $media['attachment_id'] ) ) {
					$missing_media[] = (string) ( $media['source_path'] ?? $media['key'] ?? '' );
				}
			}
			if ( $missing_media ) {
				return new WP_Error( 'leadwerk_media_assignment_failed', sprintf( '%s: Medya alanı eşlenemedi: %s', $source_file, implode( ', ', array_unique( $missing_media ) ) ) );
			}
		}
		$counts = array(
			'text_count'  => count( $fields['text_items'] ),
			'link_count'  => count( $fields['link_items'] ),
			'media_count' => count( $fields['media_items'] ),
		);
		$counts['field_count'] = array_sum( $counts );
		if ( ! $this->apply ) {
			return $counts;
		}

		$existing = $this->find_page_id( $source_key, (string) ( $config['slug'] ?? '' ) );
		$postarr  = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( (string) ( $config['title'] ?? $parsed['document_title'] ?? $source_key ) ),
			'post_name'    => sanitize_title( (string) ( $config['slug'] ?? $source_key ) ),
			'post_content' => '<!-- Content is rendered from Leadwerk individual fields. -->',
		);
		if ( $existing > 0 ) {
			$postarr['ID'] = $existing;
			$post_id = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post_id = (int) $post_id;
		$existing_fields = get_field( Leadwerk_Content_Schema::FIELD_NAME, $post_id );
		$fields = $this->merge_existing_values( $fields, $existing_fields );

		update_post_meta( $post_id, 'leadwerk_source_key', $source_key );
		update_post_meta( $post_id, 'leadwerk_source_file', $source_file );
		update_post_meta( $post_id, 'leadwerk_body_classes', sanitize_text_field( (string) ( $parsed['body_classes'] ?? '' ) ) );
		update_post_meta( $post_id, 'leadwerk_page_template_html', wp_slash( (string) $parsed['template_html'] ) );
		update_post_meta( $post_id, 'leadwerk_document_title', sanitize_text_field( (string) $parsed['document_title'] ) );
		update_post_meta( $post_id, 'leadwerk_meta_description', sanitize_text_field( (string) $parsed['meta_description'] ) );
		update_post_meta( $post_id, 'leadwerk_imported_at', current_time( 'mysql', true ) );
		update_field( Leadwerk_Content_Schema::FIELD_NAME, $fields, $post_id );
		$this->update_yoast_seo_meta( $post_id, (array) ( $config['seo'] ?? array() ), (string) $parsed['meta_description'] );
		if ( ! empty( $config['is_front'] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $post_id );
		}
		if ( class_exists( 'Leadwerk_Translation_API' ) ) {
			Leadwerk_Translation_API::ensure_post_record(
				$post_id,
				array(
					'language_code' => Leadwerk_Translation_API::get_default_language(),
					'source_key'    => $source_key,
					'public_slug'   => ! empty( $config['is_front'] ) ? '' : sanitize_title( (string) $config['slug'] ),
					'is_home'       => ! empty( $config['is_front'] ),
					'status'        => 'complete',
				)
			);
		}
		return $counts;
	}

	/**
	 * Persist the Yoast fields supplied by the portable import manifest.
	 *
	 * @param int                 $post_id              Imported page ID.
	 * @param array<string,mixed> $seo                  SEO manifest values.
	 * @param string              $fallback_description Parsed HTML meta description.
	 * @return void
	 */
	private function update_yoast_seo_meta( $post_id, $seo, $fallback_description ) {
		$focus_keyphrase = sanitize_text_field( (string) ( $seo['focus_keyphrase'] ?? '' ) );
		$seo_title       = sanitize_text_field( (string) ( $seo['title'] ?? '' ) );
		$description     = sanitize_text_field( (string) ( $seo['meta_description'] ?? $fallback_description ) );

		if ( '' !== $focus_keyphrase ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_keyphrase );
		}
		if ( '' !== $seo_title ) {
			update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
		}
		if ( '' !== $description ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );
		}

		if ( ! empty( $seo['noindex'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
		} else {
			delete_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex' );
		}
	}

	/** @param array<string,mixed> $state State. @return array<string,mixed> */
	private function run_finalize( $state ) {
		if ( (int) ( $state['error_count'] ?? 0 ) > 0 ) {
			$state['steps']['finalize']['processed'] = 1;
			$state['steps']['finalize']['status'] = 'failed';
			$state['status'] = 'failed';
			$state['finished_at'] = current_time( 'mysql', true );
			$state['current_item'] = 'Import hatalar nedeniyle tamamlanmadı; site ayarları değiştirilmedi.';
			$this->add_log( $state, $state['current_item'], 'error' );
			return $state;
		}
		if ( $this->apply ) {
			update_option( 'blogname', sanitize_text_field( (string) ( $this->manifest['site_title'] ?? 'Trend in Form GmbH' ) ) );
			update_option( 'blogdescription', sanitize_text_field( (string) ( $this->manifest['site_tagline'] ?? '' ) ) );
			update_option( 'permalink_structure', '/%postname%/' );
			$form_query = new WP_Query( array( 'post_type' => 'wpforms', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'title' => 'Trend in Form Projektanfrage' ) );
			if ( ! empty( $form_query->posts ) ) {
				update_option( 'leadwerk_opt_wpforms_form_id_de', (int) $form_query->posts[0], false );
				$wpforms_settings = get_option( 'wpforms_settings', array() );
				$wpforms_settings = is_array( $wpforms_settings ) ? $wpforms_settings : array();
				$wpforms_settings['gdpr'] = '1';
				update_option( 'wpforms_settings', $wpforms_settings, false );
			}
			flush_rewrite_rules( false );
			update_option( LEADWERK_IMPORTER_OPTION_POST_STEPS_NOTICE, '1', false );
		}
		$state['steps']['finalize']['processed'] = 1;
		$state['steps']['finalize']['status'] = 'completed';
		$state['status'] = 'completed';
		$state['overall_percent'] = 100;
		$state['finished_at'] = current_time( 'mysql', true );
		$state['current_item'] = $this->apply ? 'Import tamamlandı.' : 'Dry run tamamlandı; WordPress değiştirilmedi.';
		$this->add_log( $state, $state['current_item'] );
		return $state;
	}

	/** @return array<int,string> */
	private function collect_media_queue() {
		$paths = array();
		if ( ! is_dir( $this->source_root ) ) {
			return $paths;
		}
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->source_root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
				continue;
			}
			$ext = strtolower( $file->getExtension() );
			if ( ! in_array( $ext, array( 'webp', 'mp4', 'webm' ), true ) ) {
				continue;
			}
			$paths[] = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $this->source_root ) ) );
		}
		sort( $paths, SORT_NATURAL | SORT_FLAG_CASE );
		return array_values( array_unique( $paths ) );
	}

	/** @param array<string,mixed> $fields Fields. @return array<string,mixed> */
	private function attach_media_ids( $fields ) {
		$fields = Leadwerk_Content_Schema::normalize_payload( $fields );
		foreach ( $fields['media_items'] as &$row ) {
			$row['attachment_id'] = $this->apply ? $this->media->get_attachment_id_by_source( (string) $row['source_path'] ) : 0;
		}
		unset( $row );
		return $fields;
	}

	/**
	 * Preserve editor changes during safe re-imports while accepting new fields.
	 *
	 * @param array<string,mixed> $fresh Fresh fields.
	 * @param mixed               $existing Existing fields.
	 * @return array<string,mixed>
	 */
	private function merge_existing_values( $fresh, $existing ) {
		$existing = Leadwerk_Content_Schema::normalize_payload( $existing );
		foreach ( array( 'text_items', 'link_items', 'media_items' ) as $bucket ) {
			$lookup = array();
			foreach ( $existing[ $bucket ] as $row ) {
				$lookup[ $row['key'] ] = $row;
			}
			foreach ( $fresh[ $bucket ] as &$row ) {
				if ( ! isset( $lookup[ $row['key'] ] ) ) {
					continue;
				}
				$old = $lookup[ $row['key'] ];
				if ( 'text_items' === $bucket ) {
					$fresh_content  = (string) $row['content'];
					$legacy_content = wp_kses_post( $fresh_content );
					$old_content    = (string) $old['content'];
					// Repair SVG icons stripped by schema versions before 3.0.2,
					// while continuing to preserve genuine editor changes.
					$row['content'] = false !== stripos( $fresh_content, '<svg' ) && $old_content === $legacy_content
						? $fresh_content
						: $old_content;
				} elseif ( 'link_items' === $bucket ) {
					$row['href'] = $old['href'];
				} else {
					$row['attachment_id'] = $old['attachment_id'] ?: $row['attachment_id'];
					$row['alt'] = $old['alt'];
				}
			}
			unset( $row );
		}
		return $fresh;
	}

	/** @param string $source_key Key. @param string $slug Slug. @return int */
	private function find_page_id( $source_key, $slug ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => 'leadwerk_source_key',
				'meta_value'     => $source_key,
			)
		);
		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}
		$page = get_page_by_path( sanitize_title( $slug ), OBJECT, 'page' );
		return $page instanceof WP_Post ? (int) $page->ID : 0;
	}

	/** @param array<string,mixed> $state State. @param string $message Message. @param string $level Level. @return void */
	private function add_log( &$state, $message, $level = 'info' ) {
		$state['log_tail'][] = array( 'time' => gmdate( 'Y-m-d H:i:s' ), 'level' => $level, 'message' => (string) $message );
		if ( count( $state['log_tail'] ) > 80 ) {
			$state['log_tail'] = array_slice( $state['log_tail'], -80 );
		}
	}
}
