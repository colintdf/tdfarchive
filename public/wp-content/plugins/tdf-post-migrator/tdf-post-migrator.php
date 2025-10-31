<?php
/*
Plugin Name: TDF Post Migrator
Description: Import TDF content into WordPress. All content becomes standard posts with taxonomies for content_type and section.
Version:     0.2.0
Author:      Your Name
Text Domain: tdf-post-migrator
Domain Path: /languages
License:     GPLv2 or later
*/

if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! class_exists( 'TDF_Post_Migrator' ) ) {

    final class TDF_Post_Migrator {

        const VERSION = '0.2.0';
        const TEXT_DOMAIN = 'tdf-post-migrator';
        private static $instance = null;

        /**
         * Singleton instance
         *
         * @return TDF_Post_Migrator
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->define_constants();

            add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
            add_action( 'init', array( $this, 'init' ) );

            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

            add_action( 'admin_post_tdf_pm_migrate_posts', array( $this, 'handle_migrate_posts' ) );
        }

        private function define_constants() {
            if ( ! defined( 'TDF_PM_FILE' ) )  define( 'TDF_PM_FILE', __FILE__ );
            if ( ! defined( 'TDF_PM_PATH' ) )  define( 'TDF_PM_PATH', plugin_dir_path( __FILE__ ) );
            if ( ! defined( 'TDF_PM_URL' ) )   define( 'TDF_PM_URL', plugin_dir_url( __FILE__ ) );
            if ( ! defined( 'TDF_PM_VERSION' ) ) define( 'TDF_PM_VERSION', self::VERSION );
        }

        public function load_textdomain() {
            load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        public function init() {
            // Register taxonomies used by the importer.
            $this->register_content_type_taxonomy();
            $this->register_section_taxonomy();
        }

        public function register_content_type_taxonomy() {
            if ( ! taxonomy_exists( 'content_type' ) ) {
                register_taxonomy( 'content_type', 'post', array(
                    'label'        => __( 'Content Type', self::TEXT_DOMAIN ),
                    'public'       => true,
                    'hierarchical' => false,
                    'show_in_rest' => true,
                    'rewrite'      => array( 'slug' => 'content-type' ),
                ) );
            }
            // Ensure taxonomy is linked to posts
            register_taxonomy_for_object_type( 'content_type', 'post' );
            register_taxonomy_for_object_type( 'post_tag', 'post' );
        }

        public function register_section_taxonomy() {
            if ( ! taxonomy_exists( 'section' ) ) {
                register_taxonomy( 'section', 'post', array(
                    'label'        => __( 'Sections', self::TEXT_DOMAIN ),
                    'public'       => true,
                    'hierarchical' => false,
                    'show_in_rest' => true,
                    'rewrite'      => array( 'slug' => 'section' ),
                ) );
            }
            register_taxonomy_for_object_type( 'section', 'post' );
        }

        public function add_admin_menu() {
            add_menu_page(
                __( 'TDF Post Migrator', self::TEXT_DOMAIN ),
                __( 'Post Migrator', self::TEXT_DOMAIN ),
                'manage_options',
                'tdf-post-migrator',
                array( $this, 'admin_page' ),
                'dashicons-migrate',
                80
            );
        }

        public function admin_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( isset( $_GET['start'] ) ) {
                $start = intval( $_GET['start'] );
                echo '<div class="notice notice-info"><p>' . sprintf( esc_html__( 'Processing batch starting at %d...', self::TEXT_DOMAIN ), $start ) . '</p></div>';
            }

            if ( isset( $_GET['success'] ) && $_GET['success'] == 1 ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Migration complete.', self::TEXT_DOMAIN ) . '</p></div>';
            }

            ?>
            <div class="wrap">
                <h1><?php echo esc_html( __( 'TDF Post Migrator', self::TEXT_DOMAIN ) ); ?></h1>
                <p><?php echo esc_html( __( 'Run the migration. The process imports in batches and auto-continues to avoid timeouts.', self::TEXT_DOMAIN ) ); ?></p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'tdf_pm_migrate_posts', 'tdf_pm_nonce' ); ?>
                    <input type="hidden" name="action" value="tdf_pm_migrate_posts">
                    <p>
                        <label>
                            <input type="checkbox" name="tdf_pm_reset" value="1" checked>
                            <?php echo esc_html__( 'Reset previously imported content before importing (start from scratch)', self::TEXT_DOMAIN ); ?>
                        </label>
                    </p>
                    <p>
                        <input type="submit" class="button button-primary" value="<?php echo esc_attr( __( 'Migrate Posts', self::TEXT_DOMAIN ) ); ?>">
                    </p>
                </form>
            </div>
            <?php
        }

        public function handle_migrate_posts() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Unauthorized request', self::TEXT_DOMAIN ) );
            }
            // First request is POST from the form; chained steps are GET.
            if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

                if ( ! isset( $_POST['tdf_pm_nonce'] ) || ! wp_verify_nonce( $_POST['tdf_pm_nonce'], 'tdf_pm_migrate_posts' ) ) {
                    wp_die( __( 'Invalid or expired nonce.', self::TEXT_DOMAIN ) );
                }

                // If requested, reset imported content before starting.
              if ( isset( $_POST['tdf_pm_reset'] ) && $_POST['tdf_pm_reset'] == '1' ) {
                $next_reset = isset( $_GET['reset_offset'] ) ? intval( $_GET['reset_offset'] ) : 0;
                $next_reset = $this->reset_previous_import( $next_reset );

                if ( $next_reset !== false ) {
                    $next_nonce = wp_create_nonce( 'tdf_pm_migrate_posts' );
                    $next_url   = admin_url( 'admin-post.php?action=tdf_pm_migrate_posts&_wpnonce=' . $next_nonce . '&tdf_pm_reset=1&reset_offset=' . $next_reset );
                    echo '<div class="wrap"><h1>Resetting Imported Content</h1>';
                    echo '<p>Deleted a batch of posts (offset ' . esc_html( $next_reset ) . '). Continuing automatically...</p>';
                    echo '<meta http-equiv="refresh" content="0.1;url=' . esc_url( $next_url ) . '">';
                    echo '<p><a href="' . esc_url( $next_url ) . '">Click here if not redirected</a></p></div>';
                    exit;
                }

                    // Reset complete, start fresh import
                }

            }

            $start = isset( $_GET['start'] ) ? intval( $_GET['start'] ) : 0;

            $next  = $this->migrate_posts( $start );
            if ( $next === false ) {
                wp_safe_redirect( admin_url( 'admin.php?page=tdf-post-migrator&success=1' ) );
                exit;
            }

            $next_nonce = wp_create_nonce( 'tdf_pm_migrate_posts' );
            $next_url   = admin_url( 'admin-post.php?action=tdf_pm_migrate_posts&start=' . $next . '&_wpnonce=' . $next_nonce );

            // Output a small progress page and auto-advance
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'TDF Post Migrator', self::TEXT_DOMAIN ) . '</h1>';
            echo '<p>' . sprintf( esc_html__( 'Processed batch starting at %d. Continuing automatically...', self::TEXT_DOMAIN ), $start ) . '</p>';
            echo '<meta http-equiv="refresh" content="1;url=' . esc_url( $next_url ) . '">';
            echo '<p><a href="' . esc_url( $next_url ) . '">' . esc_html__( 'Click here if not redirected', self::TEXT_DOMAIN ) . '</a></p>';
            echo '</div>';
            exit;
        }

        /**
         * Remove previously imported content so we can start fresh.
         * Deletes posts that have our tdf_pm_imported flag or external_unique_id meta.
         * Also deletes attached featured images for those posts.
         */
       private function reset_previous_import( $offset = 0 ) {
    $batch_size = 100;

    $to_delete = get_posts( array(
        'post_type'      => 'any',
        'posts_per_page' => $batch_size,
        'offset'         => $offset,
        'post_status'    => 'any',
        'meta_query'     => array(
            'relation' => 'OR',
            array(
                'key'   => 'tdf_pm_imported',
                'value' => '1',
            ),
            array(
                'key'     => 'external_unique_id',
                'compare' => 'EXISTS',
            ),
        ),
        'fields' => 'ids',
    ) );

    if ( empty( $to_delete ) ) {
        return false;
    }

    foreach ( $to_delete as $pid ) {
        $thumb_id = get_post_thumbnail_id( $pid );
        if ( $thumb_id ) {
            wp_delete_attachment( $thumb_id, true );
        }
        wp_delete_post( $pid, true );
    }

    wp_cache_flush();
    return ( count( $to_delete ) === $batch_size ) ? $offset + $batch_size : false;
}



        public function enqueue_admin_assets( $hook ) {
            if ( strpos( $hook, 'tdf-post-migrator' ) === false ) {
                return;
            }
            wp_enqueue_style( 'tdf-pm-admin', TDF_PM_URL . 'assets/css/admin.css', array(), TDF_PM_VERSION );
            wp_enqueue_script( 'tdf-pm-admin', TDF_PM_URL . 'assets/js/admin.js', array( 'jquery' ), TDF_PM_VERSION, true );
            wp_localize_script( 'tdf-pm-admin', 'TDF_PM', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'tdf_pm_nonce' ),
            ) );
        }

        public function enqueue_public_assets() {
            wp_enqueue_style( 'tdf-pm-public', TDF_PM_URL . 'assets/css/public.css', array(), TDF_PM_VERSION );
            wp_enqueue_script( 'tdf-pm-public', TDF_PM_URL . 'assets/js/public.js', array(), TDF_PM_VERSION, true );
        }

        // =========================
        // Core Migration
        // =========================
public function migrate_posts( $start = 0 ) {
    $batchSize = 50;
    $apidomain = 'https://api.thedigitalfix.com';

    // -------------------
    // Helper Functions
    // -------------------

    $map_section_host_to_label = function( $host ) {
        $host = strtolower( trim( $host ) );
        if ( strpos( $host, 'film.' ) === 0 )        return 'Film';
        if ( strpos( $host, 'music.' ) === 0 )       return 'Music';
        if ( strpos( $host, 'television.' ) === 0 )  return 'Television';
        if ( strpos( $host, 'gaming.' ) === 0 )      return 'Gaming';
        if ( strpos( $host, 'geeklife.' ) === 0 )    return 'Life';
        if ( $host === 'www.thedigitalfix.com' || $host === 'www' ) return 'Life';
        return ucfirst( preg_replace( '/\..*$/', '', $host ) );
    };

    $ensure_author = function( $slug, $display_name ) {
        $slug = sanitize_user( $slug, true );
        if ( empty( $slug ) ) {
            return get_current_user_id();
        }
        $user = get_user_by( 'slug', $slug );
        if ( ! $user ) {
            $user = get_user_by( 'login', $slug );
        }
        if ( $user ) {
            return $user->ID;
        }
        $user_id = wp_insert_user( array(
            'user_login'    => $slug,
            'user_nicename' => sanitize_title( $slug ),
            'display_name'  => $display_name ?: $slug,
            'role'          => 'author',
            'user_pass'     => wp_generate_password(),
        ) );
        if ( is_wp_error( $user_id ) ) {
            return get_current_user_id();
        }
        return $user_id;
    };

    $ensure_term = function( $taxonomy, $name ) {
        $name = sanitize_text_field( $name );
        if ( $name === '' ) return 0;
        $term = term_exists( $name, $taxonomy );
        if ( ! $term ) {
            $term = wp_insert_term( $name, $taxonomy );
        }
        if ( is_wp_error( $term ) ) return 0;
        return is_array( $term ) ? intval( $term['term_id'] ) : intval( $term );
    };

    $build_sections = function( $site_id, $additional_sections, $full_json ) use ( $map_section_host_to_label ) {
        $sections = array();
        if ( ! empty( $site_id ) ) {
            $sections[] = $map_section_host_to_label( $site_id );
        }
        if ( is_string( $additional_sections ) && strpos( $additional_sections, '|' ) !== false ) {
            $parts = array_map( 'trim', explode( '|', $additional_sections ) );
            foreach ( $parts as $p ) {
                if ( $p !== '' ) $sections[] = $map_section_host_to_label( $p );
            }
        }
        if ( is_array( $full_json ) && isset( $full_json['additional_sections'] ) ) {
            $maybe_ser = $full_json['additional_sections'];
            if ( is_array( $maybe_ser ) ) {
                foreach ( $maybe_ser as $s ) {
                    $s_un = maybe_unserialize( $s );
                    if ( is_array( $s_un ) ) {
                        foreach ( $s_un as $host ) {
                            $sections[] = $map_section_host_to_label( $host );
                        }
                    } elseif ( is_string( $s ) ) {
                        $sections[] = $map_section_host_to_label( $s );
                    }
                }
            }
        }
        return array_values( array_unique( array_filter( array_map( 'trim', $sections ) ) ) );
    };

    $parse_tags = function( $raw, $full_json ) {
        $tags = array();
        if ( is_string( $raw ) ) {
            $maybe = maybe_unserialize( $raw );
            if ( is_array( $maybe ) ) {
                foreach ( $maybe as $t ) {
                    if ( is_string( $t ) && $t !== '' ) $tags[] = $t;
                }
            }
        } elseif ( is_array( $raw ) ) {
            foreach ( $raw as $t ) {
                if ( is_string( $t ) && $t !== '' ) $tags[] = $t;
            }
        }
        if ( empty( $tags ) && is_array( $full_json ) && isset( $full_json['post_tag'] ) && is_array( $full_json['post_tag'] ) ) {
            foreach ( $full_json['post_tag'] as $obj ) {
                if ( isset( $obj['name'] ) && is_string( $obj['name'] ) ) {
                    $tags[] = $obj['name'];
                }
            }
        }
        return array_values( array_unique( array_filter( array_map( 'trim', $tags ) ) ) );
    };

    $attach_featured_image = function( $post_id, $url, $filename_hint = 'image.jpg' ) {
        if ( empty( $url ) ) return;

        // If it’s a full URL, try to find existing attachment
        $attachment_id = attachment_url_to_postid( $url );
        if ( $attachment_id ) {
            set_post_thumbnail( $post_id, $attachment_id );
            return;
        }

        // Try matching by relative path
        $parsed = wp_parse_url( $url, PHP_URL_PATH );
        if ( $parsed && strpos( $parsed, '/wp-content/uploads/' ) !== false ) {
            $upload_dir = wp_upload_dir();
            $local_path = $upload_dir['basedir'] . str_replace( '/wp-content/uploads', '', $parsed );
            if ( file_exists( $local_path ) ) {
                $wp_filetype   = wp_check_filetype( basename( $local_path ), null );
                $attachment_id = wp_insert_attachment( array(
                    'post_mime_type' => $wp_filetype['type'] ?: 'image/jpeg',
                    'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $local_path ) ),
                    'post_status'    => 'inherit',
                ), $local_path, $post_id );
                if ( ! is_wp_error( $attachment_id ) ) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $attach_data = wp_generate_attachment_metadata( $attachment_id, $local_path );
                    wp_update_attachment_metadata( $attachment_id, $attach_data );
                    set_post_thumbnail( $post_id, $attachment_id );
                }
                return;
            }
        }

        // Otherwise, fallback to base64 handling
        if ( preg_match( '/^data:image/', $url ) ) {
            $decoded = preg_replace( '/^data:image\/[a-z]+;base64,/', '', $url );
        } else {
            $decoded = base64_decode( $url );
        }
        if ( ! $decoded ) return;

        $upload = wp_upload_bits( sanitize_file_name( $filename_hint ), null, $decoded );
        if ( empty( $upload['error'] ) ) {
            $wp_filetype   = wp_check_filetype( $upload['file'], null );
            $attachment_id = wp_insert_attachment( array(
                'post_mime_type' => $wp_filetype['type'] ?: 'image/jpeg',
                'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $upload['file'] ) ),
                'post_status'    => 'inherit',
            ), $upload['file'], $post_id );
            if ( ! is_wp_error( $attachment_id ) ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
                wp_update_attachment_metadata( $attachment_id, $attach_data );
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }
    };

    $find_existing_by_unique = function( $site_id, $content_id ) {
        $unique_key = $site_id . '-' . $content_id;
        $existing = get_posts( array(
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_key'       => 'external_unique_id',
            'meta_value'     => $unique_key,
            'fields'         => 'ids',
        ) );
        return $existing ? intval( $existing[0] ) : 0;
    };

    // -------------------
    // Fetch Data
    // -------------------
    $response = wp_remote_get( "{$apidomain}/?action=list&start={$start}&rows={$batchSize}", array(
        'timeout'   => 30,
        'sslverify' => false,
        'headers'   => array( 'Accept' => 'application/json' ),
    ) );




    if ( is_wp_error( $response ) ) {
        error_log( 'Error fetching posts: ' . $response->get_error_message() );
        echo $response->get_error_message();exit;
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );



    if ( empty( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
        error_log( "No more data at start={$start}" );
        return false;
    }

    // -------------------
    // Process Each Post
    // -------------------
    foreach ( $data['data'] as $item ) {
        $title       = $item['content_title'] ?? '';
        $slug        = $item['content_slug'] ?? '';
        $excerpt     = $item['content_excerpt'] ?? '';
        $site_id     = $item['site_id'] ?? '';
        $content_id  = $item['content_id'] ?? '';
        $updated_at  = $item['content_update_date'] ?? '';
        $live_at     = $item['content_live_date'] ?? '';
        $ctype       = $item['content_definition'] ?? '';
        $ctype_l = strtolower( trim( $ctype ) );
        if ( in_array( $ctype_l, array( 'film', 'game', 'series' ), true ) ) {
            // Skip items of these content types
            echo 'Skipping content type: ' . esc_html( $ctype ) . "\n";
            continue;
        }

        $full_json = array();
        if ( ! empty( $item['full_json_content'] ) && is_string( $item['full_json_content'] ) ) {
            $full_json = json_decode( $item['full_json_content'], true );
            if ( ! is_array( $full_json ) ) {
                $full_json = array();
            }
        }
        $content = $full_json['post_content'] ?? '';
        if (empty($content)) {
            $fallbackapi = wp_remote_get( "{$apidomain}/?action=getArticle&index={$site_id}&contentid={$content_id}", array(
                'timeout'   => 30,
                'sslverify' => false,
                'headers'   => array( 'Accept' => 'application/json' ),
            ) );
            if ( !is_wp_error( $fallbackapi ) ) {
                $fallbackbody = wp_remote_retrieve_body( $fallbackapi );
                $fallbackdata = json_decode( $fallbackbody, true );
                var_dump($fallbackdata);exit;
                if (is_array($fallbackdata) && isset($fallbackdata['content_text'])) {
                    $content = $fallbackdata['content_text'];
                }
         
            }
        }
        echo $content;exit;
        // --- Replace in-body image links ---
if ( ! empty( $content ) ) {
    // 1. Replace any old subdomains (film, music, television, gaming, geeklife, life)
    //    with the unified domain
    $content = preg_replace(
        '#https?://(?:www\.)?(?:film|music|television|gaming|geeklife|life)\.thedigitalfix\.com#i',
        'https://tdf.croftsoftsoftware.com',
        $content
    );

    // 2. Normalize paths:
    //    Remove any intermediate section directory before wp-content
    //    Example: /music/wp-content/ → /wp-content/
    $content = preg_replace(
        '#/(?:film|music|television|gaming|geeklife|life)/wp-content/#i',
        '/wp-content/',
        $content
    );

    // 3. Replace any full absolute URLs (old domains) that still include wp-content
    //    Example: https://film.thedigitalfix.com/wp-content/... → https://tdf.croftsoftsoftware.com/wp-content/...
    $content = preg_replace(
        '#https?://[^/]+/wp-content/#i',
        'https://tdf.croftsoftsoftware.com/wp-content/',
        $content
    );
}


        $this->register_content_type_taxonomy();
        $this->register_section_taxonomy();

        $author_slug = $item['content_username_slug'] ?? '';
        $author_name = $item['username'] ?? '';
        $author_id   = $ensure_author( $author_slug, $author_name );

        $existing_id   = $find_existing_by_unique( $site_id, $content_id );
        $should_update = false;
        if ( $existing_id ) {
            $existing_updated = get_post_meta( $existing_id, 'content_update_date', true );
            $should_update = ( $updated_at && strtotime( $updated_at ) > strtotime( $existing_updated ) );
        }

        $postarr = array(
            'post_type'    => 'post',
            'post_author'  => $author_id,
            'post_title'   => $title,
            'post_name'    => sanitize_title( $slug ?: $title ),
            'post_excerpt' => $excerpt,
            'post_content' => $content,
            'post_status'  => 'publish',
        );
        if ( $live_at && strtotime( $live_at ) ) {
            $postarr['post_date']     = $live_at;
            $postarr['post_date_gmt'] = get_gmt_from_date( $live_at );
        }
        if ( $updated_at && strtotime( $updated_at ) ) {
            $postarr['post_modified']     = $updated_at;
            $postarr['post_modified_gmt'] = get_gmt_from_date( $updated_at );
        }

        if ( $existing_id && $should_update ) {
            $postarr['ID'] = $existing_id;
            $post_id = wp_update_post( $postarr, true );
        } elseif ( $existing_id ) {
            $post_id = $existing_id;
        } else {
            $post_id = wp_insert_post( $postarr, true );
        }

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            error_log( 'Insert/Update failed: ' . ( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'unknown error' ) );
            continue;
        }

        update_post_meta( $post_id, 'tdf_pm_imported', '1' );
        update_post_meta( $post_id, 'external_unique_id', $site_id . '-' . $content_id );
        update_post_meta( $post_id, 'content_update_date', $updated_at );

        if ( $ctype !== '' ) {
            $term_id = $ensure_term( 'content_type', $ctype );
            if ( $term_id ) {
                wp_set_object_terms( $post_id, array( $term_id ), 'content_type', false );
            }
        }

        $sections = $build_sections( $site_id, $item['additional_sections'] ?? '', $full_json );
        if ( ! empty( $sections ) ) {
            $term_ids = array();
            foreach ( $sections as $sname ) {
                $tid = $ensure_term( 'section', $sname );
                if ( $tid ) $term_ids[] = $tid;
            }
            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $post_id, $term_ids, 'section', false );
            }
        }

        $tags = $parse_tags( $item['content_tags'] ?? '', $full_json );
        if ( ! empty( $tags ) ) {
            wp_set_post_terms( $post_id, $tags, 'post_tag', false );
        }

       // --- Featured image logic ---
// --- Featured image logic ---
if ( ! has_post_thumbnail( $post_id ) ) {
    $default_image_url = '';
    if ( isset( $full_json['images']['default'] ) && is_string( $full_json['images']['default'] ) ) {
        $default_image_url = $full_json['images']['default'];
    }

    if ( $default_image_url ) {
        // Convert URL to relative path
        $relative_path = preg_replace( '#^https?://[^/]+#', '', $default_image_url );
        $local_path = ABSPATH . ltrim( $relative_path, '/' );

        // --- IMPORTANT FIX ---
        // Ensure the path includes /public/wp-content/ and strip anything in between
        // Example: /public/music/wp-content/ → /public/wp-content/
        $local_path = preg_replace( '#/public/.*?/wp-content/#', '/public/wp-content/', $local_path );
        //echo $local_path . '<br />';
        // If the default image doesn't exist locally, try a slugified title version in the same folder
        if ( ! file_exists( $local_path ) ) {
            // Slugify title and replace apostrophes with hyphens
            $slugified_title = $title ?: 'image';
            $slugified_title = str_replace( ["'", "’"], '-', $slugified_title );
            $slugified_title = sanitize_title( $slugified_title );

            $path_info = pathinfo( $local_path );

            if ( isset( $path_info['dirname'] ) ) {
                $alt_local_path = trailingslashit( $path_info['dirname'] ) . $slugified_title . '.jpg';

                if ( file_exists( $alt_local_path ) ) {
                    // Build proper web URL from corrected path
                    $relative_url = preg_replace( '#^.*?/public/wp-content/#', 'wp-content/', $alt_local_path );
                    $default_image_url = home_url( '/' . $relative_url );
                } else {
                    $default_image_url = '';
                }
            } else {
                $default_image_url = '';
            }
        } else {
            // File exists — rebuild valid URL
            $relative_url = preg_replace( '#^.*?/public/wp-content/#', 'wp-content/', $local_path );
            $default_image_url = home_url( '/' . $relative_url );
        }
    }

    // Attach whichever image is valid
    if ( $default_image_url ) {
        $attach_featured_image( $post_id, $default_image_url, $slug ?: ( $title ?: 'image' ) );
    } elseif ( ! empty( $item['thumb_encoded'] ) ) {
        $attach_featured_image( $post_id, $item['thumb_encoded'], $slug ?: ( $title ?: 'image' ) );
    }
}

    }

    error_log( "Batch imported: start={$start}, count=" . count( $data['data'] ) );

    // Continue as long as data exists
    if ( empty( $data['data'] ) ) {
        return false;
    }
    return $start + $batchSize;
}


        // Activation/Deactivation
        public static function activate() {
            flush_rewrite_rules();
        }

        public static function deactivate() {
            flush_rewrite_rules();
        }

        public static function uninstall() {
            // Intentionally no destructive actions on uninstall.
        }
    }

    // Bootstrap the plugin
    TDF_Post_Migrator::instance();

    // Register hooks
    register_activation_hook( __FILE__, array( 'TDF_Post_Migrator', 'activate' ) );
    register_deactivation_hook( __FILE__, array( 'TDF_Post_Migrator', 'deactivate' ) );
    register_uninstall_hook( __FILE__, array( 'TDF_Post_Migrator', 'uninstall' ) );
}

