<?php
/**
 * Admin controller for Mass SEO Editor.
 */
class MSE_Admin {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	private $menu_slug = 'mass-seo-editor';

	/**
	 * Supported custom meta keys.
	 */
	private const META_TITLE_KEY       = '_mse_meta_title';
	private const META_DESCRIPTION_KEY = '_mse_meta_description';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_mse_get_rows', array( $this, 'ajax_get_rows' ) );
		add_action( 'wp_ajax_mse_save_bulk', array( $this, 'ajax_save_bulk' ) );

		add_action( 'admin_post_mse_export_csv', array( $this, 'export_csv' ) );
		add_action( 'admin_post_mse_import_csv', array( $this, 'import_csv' ) );
	}

	/**
	 * Register admin page under Tools.
	 */
	public function register_menu() {
		add_management_page(
			__( 'Mass SEO Editor', 'mass-seo-editor' ),
			__( 'Mass SEO Editor', 'mass-seo-editor' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts/styles for plugin page.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tools_page_' . $this->menu_slug !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mse-admin',
			MSE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MSE_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'mse-admin',
			MSE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			MSE_PLUGIN_VERSION,
			true
		);

		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		$tags = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
			)
		);

		wp_localize_script(
			'mse-admin',
			'MSEAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'mse_nonce' ),
				'postTypes' => array_map(
					static function ( $post_type ) {
						return array(
							'value' => $post_type->name,
							'label' => $post_type->labels->singular_name,
						);
					},
					$post_types
				),
				'categories' => ! is_wp_error( $categories ) ? array_map(
					static function ( $term ) {
						return array(
							'id'   => $term->term_id,
							'name' => $term->name,
						);
					},
					$categories
				) : array(),
				'tags'       => ! is_wp_error( $tags ) ? array_map(
					static function ( $term ) {
						return array(
							'id'   => $term->term_id,
							'name' => $term->name,
						);
					},
					$tags
				) : array(),
				'statuses'   => array(
					array(
						'value' => 'publish',
						'label' => __( 'Published', 'mass-seo-editor' ),
					),
					array(
						'value' => 'draft',
						'label' => __( 'Draft', 'mass-seo-editor' ),
					),
					array(
						'value' => 'private',
						'label' => __( 'Private', 'mass-seo-editor' ),
					),
				),
				'i18n'       => array(
					'loading'      => __( 'Loading...', 'mass-seo-editor' ),
					'empty'        => __( 'No content found for filters.', 'mass-seo-editor' ),
					'saved'        => __( 'Changes saved successfully.', 'mass-seo-editor' ),
					'saveError'    => __( 'Could not save one or more rows.', 'mass-seo-editor' ),
					'invalidNonce' => __( 'Security validation failed.', 'mass-seo-editor' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'mass-seo-editor' ) );
		}
		?>
		<div class="wrap mse-wrap">
			<h1><?php esc_html_e( 'Mass SEO Editor', 'mass-seo-editor' ); ?></h1>
			<p><?php esc_html_e( 'Bulk edit SEO fields, slug, taxonomy terms and content headings.', 'mass-seo-editor' ); ?></p>

			<div class="mse-toolbar">
				<div class="mse-filters">
					<select id="mse-filter-post-type"><option value=""><?php esc_html_e( 'All post types', 'mass-seo-editor' ); ?></option></select>
					<select id="mse-filter-category"><option value=""><?php esc_html_e( 'All categories', 'mass-seo-editor' ); ?></option></select>
					<select id="mse-filter-tag"><option value=""><?php esc_html_e( 'All tags', 'mass-seo-editor' ); ?></option></select>
					<select id="mse-filter-status"><option value=""><?php esc_html_e( 'All statuses', 'mass-seo-editor' ); ?></option></select>
					<button class="button" id="mse-apply-filters"><?php esc_html_e( 'Filter', 'mass-seo-editor' ); ?></button>
				</div>
				<div class="mse-actions">
					<button class="button button-primary" id="mse-save-all"><?php esc_html_e( 'Guardar cambios masivos', 'mass-seo-editor' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mse_export_csv' ), 'mse_export_csv' ) ); ?>"><?php esc_html_e( 'Export CSV', 'mass-seo-editor' ); ?></a>
					<form id="mse-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="mse_import_csv" />
						<?php wp_nonce_field( 'mse_import_csv', 'mse_import_nonce' ); ?>
						<input type="file" name="mse_csv" accept=".csv" required />
						<button class="button" type="submit"><?php esc_html_e( 'Import CSV', 'mass-seo-editor' ); ?></button>
					</form>
				</div>
			</div>

			<div id="mse-message" aria-live="polite"></div>

			<div class="mse-table-wrapper">
				<table class="wp-list-table widefat fixed striped" id="mse-table">
					<thead>
						<tr>
							<th data-sort="title"><?php esc_html_e( 'Título', 'mass-seo-editor' ); ?></th>
							<th data-sort="slug"><?php esc_html_e( 'URL (slug)', 'mass-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'H1', 'mass-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Meta Title', 'mass-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Meta Description', 'mass-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Etiquetas', 'mass-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Categoría', 'mass-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Tipo', 'mass-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'mass-seo-editor' ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: load rows for table.
	 */
	public function ajax_get_rows() {
		$this->ensure_permissions();
		check_ajax_referer( 'mse_nonce', 'nonce' );

		$filters = array(
			'post_type' => isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '',
			'category'  => isset( $_POST['category'] ) ? (int) $_POST['category'] : 0,
			'tag'       => isset( $_POST['tag'] ) ? (int) $_POST['tag'] : 0,
			'status'    => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
		);

		$query_args = array(
			'post_type'      => $filters['post_type'] ? $filters['post_type'] : 'any',
			'post_status'    => $filters['status'] ? $filters['status'] : array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 300,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $filters['category'] ) {
			$query_args['cat'] = $filters['category'];
		}

		if ( $filters['tag'] ) {
			$query_args['tag_id'] = $filters['tag'];
		}

		$rows = array();
		$q    = new WP_Query( $query_args );

		if ( $q->have_posts() ) {
			foreach ( $q->posts as $post ) {
				$rows[] = $this->format_row( $post );
			}
		}

		wp_send_json_success(
			array(
				'rows' => $rows,
			)
		);
	}

	/**
	 * AJAX: save all rows.
	 */
	public function ajax_save_bulk() {
		$this->ensure_permissions();
		check_ajax_referer( 'mse_nonce', 'nonce' );

		$items = isset( $_POST['items'] ) ? json_decode( wp_unslash( $_POST['items'] ), true ) : array();

		if ( ! is_array( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'mass-seo-editor' ) ), 400 );
		}

		$errors = array();

		foreach ( $items as $item ) {
			$item_errors = $this->save_item( $item );
			if ( ! empty( $item_errors ) ) {
				$errors = array_merge( $errors, $item_errors );
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Saved with warnings.', 'mass-seo-editor' ),
					'errors'  => $errors,
				)
			);
		}

		wp_send_json_success( array( 'message' => __( 'Bulk update completed.', 'mass-seo-editor' ) ) );
	}

	/**
	 * Export CSV file.
	 */
	public function export_csv() {
		$this->ensure_permissions();
		check_admin_referer( 'mse_export_csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mass-seo-editor-export.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'id', 'title', 'slug', 'h1', 'meta_title', 'meta_description', 'tags', 'categories', 'post_type', 'status' ) );

		$q = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
			)
		);

		foreach ( $q->posts as $post ) {
			$row = $this->format_row( $post );
			fputcsv(
				$output,
				array(
					$row['id'],
					$row['title'],
					$row['slug'],
					$row['h1'],
					$row['meta_title'],
					$row['meta_description'],
					$row['tags'],
					implode( '|', wp_list_pluck( $row['categories'], 'id' ) ),
					$row['post_type'],
					$row['status'],
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Import CSV file.
	 */
	public function import_csv() {
		$this->ensure_permissions();
		check_admin_referer( 'mse_import_csv', 'mse_import_nonce' );

		if ( empty( $_FILES['mse_csv']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'tools.php?page=' . $this->menu_slug . '&mse_import=missing' ) );
			exit;
		}

		$handle = fopen( $_FILES['mse_csv']['tmp_name'], 'r' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! $handle ) {
			wp_safe_redirect( admin_url( 'tools.php?page=' . $this->menu_slug . '&mse_import=failed' ) );
			exit;
		}

		$header = fgetcsv( $handle );
		$map    = is_array( $header ) ? array_flip( $header ) : array();

		$errors = array();

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$post_id = isset( $row[ $map['id'] ] ) ? (int) $row[ $map['id'] ] : 0;
			if ( ! $post_id || ! get_post( $post_id ) ) {
				continue;
			}

			$item = array(
				'id'               => $post_id,
				'title'            => $row[ $map['title'] ] ?? '',
				'slug'             => $row[ $map['slug'] ] ?? '',
				'h1'               => $row[ $map['h1'] ] ?? '',
				'meta_title'       => $row[ $map['meta_title'] ] ?? '',
				'meta_description' => $row[ $map['meta_description'] ] ?? '',
				'tags'             => $row[ $map['tags'] ] ?? '',
				'categories'       => isset( $row[ $map['categories'] ] ) ? explode( '|', $row[ $map['categories'] ] ) : array(),
			);

			$item_errors = $this->save_item( $item );
			if ( ! empty( $item_errors ) ) {
				$errors = array_merge( $errors, $item_errors );
			}
		}

		fclose( $handle );

		wp_safe_redirect(
			admin_url(
				'tools.php?page=' . $this->menu_slug . '&mse_import=' . ( empty( $errors ) ? 'success' : 'warning' )
			)
		);
		exit;
	}

	/**
	 * Save one post row.
	 *
	 * @param array<string,mixed> $item Row payload.
	 * @return array<int,string>
	 */
	private function save_item( $item ) {
		$errors  = array();
		$post_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			$errors[] = sprintf( __( 'Post %d not found.', 'mass-seo-editor' ), $post_id );
			return $errors;
		}

		$raw_slug = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : $post->post_name;
		$slug     = wp_unique_post_slug( $raw_slug, $post_id, $post->post_status, $post->post_type, $post->post_parent );
		if ( $raw_slug && $raw_slug !== $slug ) {
			$errors[] = sprintf( __( 'Slug adjusted for post %d to keep uniqueness.', 'mass-seo-editor' ), $post_id );
		}

		$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : $post->post_title;
		$h1    = isset( $item['h1'] ) ? sanitize_text_field( $item['h1'] ) : $this->extract_first_h1( $post->post_content );

		$updated_content = $this->replace_or_prepend_h1( $post->post_content, $h1 );

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $updated_content,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			$errors[] = $updated->get_error_message();
			return $errors;
		}

		$meta_title       = isset( $item['meta_title'] ) ? sanitize_text_field( $item['meta_title'] ) : '';
		$meta_description = isset( $item['meta_description'] ) ? sanitize_textarea_field( $item['meta_description'] ) : '';

		update_post_meta( $post_id, self::META_TITLE_KEY, $meta_title );
		update_post_meta( $post_id, self::META_DESCRIPTION_KEY, $meta_description );

		$category_ids = isset( $item['categories'] ) ? array_map( 'intval', (array) $item['categories'] ) : array();
		$tag_names    = isset( $item['tags'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $item['tags'] ) ) ) : array();

		if ( taxonomy_exists( 'category' ) ) {
			wp_set_post_terms( $post_id, $category_ids, 'category', false );
		}

		if ( taxonomy_exists( 'post_tag' ) ) {
			wp_set_post_terms( $post_id, $tag_names, 'post_tag', false );
		}

		return $errors;
	}

	/**
	 * Build row object for table and CSV.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string,mixed>
	 */
	private function format_row( $post ) {
		$tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );
		$cats = wp_get_post_terms( $post->ID, 'category' );

		$meta_title = get_post_meta( $post->ID, self::META_TITLE_KEY, true );
		if ( '' === $meta_title ) {
			$meta_title = get_post_meta( $post->ID, '_yoast_wpseo_title', true );
		}

		$meta_description = get_post_meta( $post->ID, self::META_DESCRIPTION_KEY, true );
		if ( '' === $meta_description ) {
			$meta_description = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		}

		return array(
			'id'               => $post->ID,
			'title'            => $post->post_title,
			'slug'             => $post->post_name,
			'h1'               => $this->extract_first_h1( $post->post_content ),
			'meta_title'       => $meta_title,
			'meta_description' => $meta_description,
			'tags'             => implode( ', ', is_wp_error( $tags ) ? array() : $tags ),
			'categories'       => is_wp_error( $cats ) ? array() : array_map(
				static function ( $cat ) {
					return array(
						'id'   => (int) $cat->term_id,
						'name' => $cat->name,
					);
				},
				$cats
			),
			'post_type'        => $post->post_type,
			'status'           => $post->post_status,
			'edit_url'         => get_edit_post_link( $post->ID, '' ),
		);
	}

	/**
	 * Extract first h1 from content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	private function extract_first_h1( $content ) {
		if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $content, $matches ) ) {
			return trim( wp_strip_all_tags( $matches[1] ) );
		}
		return '';
	}

	/**
	 * Replace existing first h1 or prepend one if absent.
	 *
	 * @param string $content Content.
	 * @param string $new_h1 New h1.
	 * @return string
	 */
	private function replace_or_prepend_h1( $content, $new_h1 ) {
		$new_h1 = trim( $new_h1 );

		if ( '' === $new_h1 ) {
			return $content;
		}

		if ( preg_match( '/<h1[^>]*>.*?<\/h1>/is', $content ) ) {
			return preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '<h1>' . esc_html( $new_h1 ) . '</h1>', $content, 1 );
		}

		return '<h1>' . esc_html( $new_h1 ) . '</h1>' . "\n" . $content;
	}

	/**
	 * Verify capability.
	 */
	private function ensure_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'mass-seo-editor' ) ), 403 );
		}
	}
}
