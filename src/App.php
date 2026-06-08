<?php

namespace Akirk\Wordopedia;

use WpApp\BaseApp;
use WpApp\WpApp;

class App extends BaseApp {
    use Snippets;

    const POST_TYPE = 'wordopedia_article';
    const POST_TYPE_SNIPPET = 'wordopedia_snippet';
    const TAX_LIST  = 'wordopedia_list';

    const USER_META_LANGUAGES = '_wordopedia_preferred_languages';

    const META_PAGE_ID        = '_wordopedia_page_id';
    const META_LANGUAGE       = '_wordopedia_language';
    const META_SOURCE_URL     = '_wordopedia_source_url';
    const META_THUMBNAIL_URL  = '_wordopedia_thumbnail_url';
    const META_LAST_REVISION  = '_wordopedia_last_revision';
    const META_REMOTE_TOUCHED = '_wordopedia_remote_touched';
    const META_SAVED_AT       = '_wordopedia_saved_at';
    const META_REFETCHED_AT   = '_wordopedia_refetched_at';
    const META_SNIPPET_ORIGINAL_TEXT = '_wordopedia_snippet_original_text';
    const META_SNIPPET_CREATED_AT    = '_wordopedia_snippet_created_at';
    const META_SNIPPET_UPDATED_AT    = '_wordopedia_snippet_updated_at';

    const NONCE_SAVE_ARTICLE    = 'wordopedia_save_article';
    const NONCE_REFETCH_ARTICLE = 'wordopedia_refetch_article';
    const NONCE_SAVE_SNIPPET    = 'wordopedia_save_snippet';
    const NONCE_UPDATE_SNIPPET  = 'wordopedia_update_snippet';
    const NONCE_DELETE_SNIPPET  = 'wordopedia_delete_snippet';
    const NONCE_SAVE_SETTINGS   = 'wordopedia_save_settings';
    const NONCE_SIDELOAD_IMAGES = 'wordopedia_sideload_images';

    const WORDOPEDIA_CACHE_SEARCH   = 300;
    const WORDOPEDIA_CACHE_ARTICLE  = 3600;
    const WORDOPEDIA_CACHE_LANGUAGE = 86400;
    const WORDOPEDIA_CACHE_MEDIA    = 3600;

    public function __construct() {
        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            'require_login' => true,
            'app_name'      => 'Wordopedia',
            'my_apps'       => true,
        ] );

        $this->enqueue_assets();

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'admin_post_wordopedia_save_article', [ $this, 'handle_save_article' ] );
        add_action( 'admin_post_wordopedia_refetch_article', [ $this, 'handle_refetch_article' ] );
        add_action( 'admin_post_wordopedia_sideload_article_images', [ $this, 'handle_sideload_article_images' ] );
        add_action( 'admin_post_wordopedia_save_snippet', [ $this, 'handle_save_snippet' ] );
        add_action( 'admin_post_wordopedia_update_snippet', [ $this, 'handle_update_snippet' ] );
        add_action( 'admin_post_wordopedia_delete_snippet', [ $this, 'handle_delete_snippet' ] );
        add_action( 'wp_ajax_wordopedia_save_snippet', [ $this, 'ajax_save_snippet' ] );
        add_action( 'wp_ajax_wordopedia_update_snippet', [ $this, 'ajax_update_snippet' ] );
        add_action( 'wp_ajax_wordopedia_delete_snippet', [ $this, 'ajax_delete_snippet' ] );
        add_action( 'admin_post_wordopedia_save_settings', [ $this, 'handle_save_settings' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'register_admin_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_admin_column' ], 10, 2 );
        add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_category' ] );
        add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
        add_filter( 'ai_assistant_ability_domains', [ $this, 'register_ai_assistant_ability_domains' ] );
        add_filter( 'ai_assistant_ability_instructions', [ $this, 'get_ai_assistant_ability_instructions' ], 10, 4 );
        add_filter( 'ai_assistant_welcome_tips', [ $this, 'register_ai_assistant_welcome_tips' ], 10, 2 );
    }

    protected function get_url_path(): string {
        return 'wordopedia';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    private function enqueue_assets(): void {
        $plugin_file = dirname( __DIR__ ) . '/wordopedia.php';
        $style_path  = dirname( __DIR__ ) . '/assets/css/app.css';
        $script_path = dirname( __DIR__ ) . '/assets/js/app.js';

        wp_app_enqueue_style(
            'wordopedia-app',
            plugins_url( 'assets/css/app.css', $plugin_file ),
            [],
            file_exists( $style_path ) ? (string) filemtime( $style_path ) : false
        );

        wp_app_enqueue_script(
            'wordopedia-app',
            plugins_url( 'assets/js/app.js', $plugin_file ),
            [],
            file_exists( $script_path ) ? (string) filemtime( $script_path ) : false,
            true
        );

        wp_localize_script(
            'wordopedia-app',
            'wordopediaAppConfig',
            [
                'apiUserAgent' => self::wordopedia_user_agent(),
                'isPlayground' => self::is_wordpress_playground(),
            ]
        );
    }

    protected function setup_database(): void {
        // Native WordPress storage: private CPT plus origin/refetch post meta.
    }

    protected function setup_routes(): void {
        $this->app->route( 'article/{language}', 'article.php' );
        $this->app->route( 'saved', 'saved-list.php' );
        $this->app->route( 'snippets', 'snippets-list.php' );
        $this->app->route( 'list/{slug}', 'saved-list.php' );
        $this->app->route( 'saved/{id}', 'saved.php' );
        $this->app->route( 'saved/{slug}', 'saved.php' );
        $this->app->route( 'settings', 'settings.php' );
    }

    protected function setup_menu(): void {
        $register_menu = function(): void {
            $home = self::get_app_url();

            $this->app->add_menu_item( 'search', __( 'Search', 'wordopedia' ), $home );
            $this->app->add_menu_item( 'saved', __( 'Saved articles', 'wordopedia' ), self::get_saved_articles_url() );
            $this->app->add_menu_item( 'snippets', __( 'Saved snippets', 'wordopedia' ), self::get_saved_snippets_url() );
            $this->app->add_menu_item( 'settings', __( 'Settings', 'wordopedia' ), self::get_settings_url() );
        };

        if ( did_action( 'init' ) ) {
            $register_menu();
            return;
        }

        add_action( 'init', $register_menu );
    }

    public function register_post_types(): void {
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'               => __( 'Wordopedia Articles', 'wordopedia' ),
                'singular_name'      => __( 'Wordopedia Article', 'wordopedia' ),
                'add_new_item'       => __( 'Add New Wordopedia Article', 'wordopedia' ),
                'edit_item'          => __( 'Edit Wordopedia Article', 'wordopedia' ),
                'new_item'           => __( 'New Wordopedia Article', 'wordopedia' ),
                'view_item'          => __( 'View Wordopedia Article', 'wordopedia' ),
                'search_items'       => __( 'Search Wordopedia Articles', 'wordopedia' ),
                'not_found'          => __( 'No Wordopedia articles found.', 'wordopedia' ),
                'not_found_in_trash' => __( 'No Wordopedia articles found in Trash.', 'wordopedia' ),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-welcome-learn-more',
            'supports'            => [ 'title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields' ],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'exclude_from_search' => true,
            'rewrite'             => false,
        ] );

        register_post_type( self::POST_TYPE_SNIPPET, [
            'labels' => [
                'name'               => __( 'Wordopedia Snippets', 'wordopedia' ),
                'singular_name'      => __( 'Wordopedia Snippet', 'wordopedia' ),
                'add_new_item'       => __( 'Add New Wordopedia Snippet', 'wordopedia' ),
                'edit_item'          => __( 'Edit Wordopedia Snippet', 'wordopedia' ),
                'new_item'           => __( 'New Wordopedia Snippet', 'wordopedia' ),
                'view_item'          => __( 'View Wordopedia Snippet', 'wordopedia' ),
                'search_items'       => __( 'Search Wordopedia Snippets', 'wordopedia' ),
                'not_found'          => __( 'No Wordopedia snippets found.', 'wordopedia' ),
                'not_found_in_trash' => __( 'No Wordopedia snippets found in Trash.', 'wordopedia' ),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=' . self::POST_TYPE,
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-excerpt-view',
            'supports'            => [ 'title', 'editor', 'author', 'revisions', 'custom-fields' ],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'exclude_from_search' => true,
            'rewrite'             => false,
        ] );

        register_taxonomy( self::TAX_LIST, self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Lists', 'wordopedia' ),
                'singular_name' => __( 'List', 'wordopedia' ),
                'search_items'  => __( 'Search Lists', 'wordopedia' ),
                'all_items'     => __( 'All Lists', 'wordopedia' ),
                'edit_item'     => __( 'Edit List', 'wordopedia' ),
                'update_item'   => __( 'Update List', 'wordopedia' ),
                'add_new_item'  => __( 'Add New List', 'wordopedia' ),
                'new_item_name' => __( 'New List Name', 'wordopedia' ),
                'menu_name'     => __( 'Lists', 'wordopedia' ),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'rewrite'           => false,
        ] );

        $this->register_post_meta();
    }

    private function register_post_meta(): void {
        if ( ! function_exists( 'register_post_meta' ) ) {
            return;
        }

        $auth_callback = function() {
            return current_user_can( 'edit_posts' );
        };

        foreach ( [ self::POST_TYPE, self::POST_TYPE_SNIPPET ] as $post_type ) {
            register_post_meta( $post_type, self::META_PAGE_ID, [
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
                'auth_callback'     => $auth_callback,
            ] );

            foreach ( [ self::META_LANGUAGE, self::META_LAST_REVISION, self::META_REMOTE_TOUCHED, self::META_SAVED_AT, self::META_REFETCHED_AT ] as $meta_key ) {
                register_post_meta( $post_type, $meta_key, [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'auth_callback'     => $auth_callback,
                ] );
            }

            foreach ( [ self::META_SOURCE_URL, self::META_THUMBNAIL_URL ] as $meta_key ) {
                register_post_meta( $post_type, $meta_key, [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'auth_callback'     => $auth_callback,
                ] );
            }
        }

        foreach ( [ self::META_SNIPPET_ORIGINAL_TEXT ] as $meta_key ) {
            register_post_meta( self::POST_TYPE_SNIPPET, $meta_key, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => $auth_callback,
            ] );
        }

        foreach ( [ self::META_SNIPPET_CREATED_AT, self::META_SNIPPET_UPDATED_AT ] as $meta_key ) {
            register_post_meta( self::POST_TYPE_SNIPPET, $meta_key, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => $auth_callback,
            ] );
        }
    }

    public function register_admin_columns( array $columns ): array {
        $next = [];
        foreach ( $columns as $key => $label ) {
            $next[ $key ] = $label;
            if ( 'title' === $key ) {
                $next['wordopedia_language'] = __( 'Language', 'wordopedia' );
                $next['wordopedia_source']   = __( 'Origin', 'wordopedia' );
                $next['wordopedia_refetch']  = __( 'Refetched', 'wordopedia' );
            }
        }

        return $next;
    }

    public function render_admin_column( string $column, int $post_id ): void {
        if ( 'wordopedia_language' === $column ) {
            $language = (string) get_post_meta( $post_id, self::META_LANGUAGE, true );
            echo esc_html( self::get_language_label( $language ) . ' (' . $language . ')' );
            return;
        }

        if ( 'wordopedia_source' === $column ) {
            $source_url = (string) get_post_meta( $post_id, self::META_SOURCE_URL, true );
            if ( $source_url ) {
                printf( '<a href="%s" target="_blank" rel="noreferrer">%s</a>', esc_url( $source_url ), esc_html__( 'Wikipedia', 'wordopedia' ) );
            }
            return;
        }

        if ( 'wordopedia_refetch' === $column ) {
            $refetched = (string) get_post_meta( $post_id, self::META_REFETCHED_AT, true );
            echo esc_html( $refetched ?: __( 'Never', 'wordopedia' ) );
        }
    }

    public function handle_save_article(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to save Wikipedia articles.', 'wordopedia' ) );
        }

        check_admin_referer( self::NONCE_SAVE_ARTICLE );

        $input = [
            'page_id'     => isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0,
            'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
            'language'    => isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : self::get_default_language(),
        ];

        $result = self::save_wordopedia_article( $input );
        $referer = wp_get_referer() ?: self::get_app_url();

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'wordopedia_error', rawurlencode( $result->get_error_message() ), $referer ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'saved', 1, $result['view_url'] ) );
        exit;
    }

    public function handle_refetch_article(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to refetch Wikipedia articles.', 'wordopedia' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        check_admin_referer( self::NONCE_REFETCH_ARTICLE . '_' . $post_id );

        $result = self::refetch_saved_article( $post_id );
        $referer = wp_get_referer() ?: self::get_app_url();

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'wordopedia_error', rawurlencode( $result->get_error_message() ), $referer ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'refetched', 1, $result['view_url'] ) );
        exit;
    }

    public function handle_sideload_article_images(): void {
        if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You are not allowed to download Wikipedia article images.', 'wordopedia' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        check_admin_referer( self::NONCE_SIDELOAD_IMAGES . '_' . $post_id );

        $image_urls = isset( $_POST['image_urls'] ) && is_array( $_POST['image_urls'] )
            ? wp_unslash( $_POST['image_urls'] )
            : [];

        $result = self::sideload_saved_article_images( $post_id, $image_urls );
        $referer = wp_get_referer() ?: self::get_app_url();

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'wordopedia_error', rawurlencode( $result->get_error_message() ), $referer ) );
            exit;
        }

        $args = [
            'images_imported' => isset( $result['imported'] ) ? absint( $result['imported'] ) : 0,
        ];
        if ( ! empty( $result['failed'] ) ) {
            $args['images_failed'] = absint( $result['failed'] );
        }

        wp_safe_redirect( add_query_arg( $args, $result['view_url'] ?? $referer ) );
        exit;
    }

    public function handle_save_settings(): void {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You are not allowed to update Wikipedia settings.', 'wordopedia' ) );
        }

        check_admin_referer( self::NONCE_SAVE_SETTINGS );

        $languages = isset( $_POST['languages'] ) && is_array( $_POST['languages'] )
            ? wp_unslash( $_POST['languages'] )
            : [];
        $languages = self::normalize_language_list( $languages );

        update_user_meta( get_current_user_id(), self::USER_META_LANGUAGES, $languages );
        wp_safe_redirect( add_query_arg( 'settings_saved', 1, self::get_settings_url() ) );
        exit;
    }

    public function register_ability_category(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        wp_register_ability_category( 'wordopedia', [
            'label'       => __( 'Wordopedia', 'wordopedia' ),
            'description' => __( 'Search, browse, inspect media, save, refetch, and annotate Wikipedia articles.', 'wordopedia' ),
        ] );
    }

    public function register_abilities(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        wp_register_ability( 'wordopedia/search-wikipedia', [
            'label'               => __( 'Search Wikipedia Articles', 'wordopedia' ),
            'description'         => 'Search Wikipedia articles.',
            'category'            => 'wordopedia',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'query'    => [
                        'type'        => 'string',
                        'description' => 'Search phrase to send to Wikipedia.',
                    ],
                    'language' => [
                        'type'        => 'string',
                        'description' => 'Wikipedia language subdomain. Defaults to the current user locale when omitted.',
                    ],
                    'limit'    => [
                        'type'        => 'integer',
                        'description' => 'Maximum number of results, from 1 to 20.',
                    ],
                ],
                'required'             => [ 'query' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => self::article_search_output_schema(),
            'execute_callback'    => [ $this, 'ability_search_articles' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Use app_url to open search results in Wordopedia. Use page_id and language with wordopedia/get-article or wordopedia/save-article.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/get-article', [
            'label'               => __( 'Get Wikipedia Article', 'wordopedia' ),
            'description'         => 'Fetch a Wikipedia article.',
            'category'            => 'wordopedia',
            'input_schema'        => self::article_lookup_input_schema(),
            'output_schema'       => self::article_detail_output_schema(),
            'execute_callback'    => [ $this, 'ability_get_article' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'If both page_id and title are present, page_id is authoritative. Present app_url for reading inside the app.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/list-article-media', [
            'label'               => __( 'List Wikipedia Article Media', 'wordopedia' ),
            'description'         => 'List media files used by a Wikipedia article, including SVG files.',
            'category'            => 'wordopedia',
            'input_schema'        => self::article_media_input_schema(),
            'output_schema'       => self::article_media_output_schema(),
            'execute_callback'    => [ $this, 'ability_list_article_media' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Use mime=image/svg+xml to find SVGs. Present original_url for the SVG file, thumbnail_url for preview, and description_url plus license/attribution fields for reuse.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/get-media-file', [
            'label'               => __( 'Get Wikipedia Media File', 'wordopedia' ),
            'description'         => 'Get metadata and URLs for a Wikipedia or Wikimedia Commons media file.',
            'category'            => 'wordopedia',
            'input_schema'        => self::media_file_lookup_input_schema(),
            'output_schema'       => self::media_file_output_schema(),
            'execute_callback'    => [ $this, 'ability_get_media_file' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Present original_url, thumbnail_url, description_url, license, attribution, artist, and source. Prefer description_url when the user needs reuse details.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/save-article', [
            'label'               => __( 'Save Wikipedia Article', 'wordopedia' ),
            'description'         => 'Save or update a Wikipedia article.',
            'category'            => 'wordopedia',
            'input_schema'        => self::article_lookup_input_schema(),
            'output_schema'       => self::saved_article_output_schema(),
            'execute_callback'    => [ $this, 'ability_save_article' ],
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'After saving, present whether the article was created or updated and link view_url.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/list-saved-articles', [
            'label'               => __( 'List Saved Wikipedia Articles', 'wordopedia' ),
            'description'         => 'List saved Wikipedia articles.',
            'category'            => 'wordopedia',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'search'   => [
                        'type'        => 'string',
                        'description' => 'Optional search term for saved article titles and content.',
                    ],
                    'language' => [
                        'type'        => 'string',
                        'description' => 'Optional Wikipedia language subdomain to filter saved articles.',
                    ],
                    'list'     => [
                        'type'        => 'string',
                        'description' => 'Optional saved article list slug to filter saved articles.',
                    ],
                    'limit'    => [
                        'type'        => 'integer',
                        'description' => 'Maximum number of saved articles, from 1 to 50.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'articles' => [
                        'type'  => 'array',
                        'items' => self::saved_article_schema(),
                    ],
                ],
            ],
            'execute_callback'    => [ $this, 'ability_list_saved_articles' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Use returned post_id values with wordopedia/get-saved-article or wordopedia/refetch-saved-article.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/get-saved-article', [
            'label'               => __( 'Get Saved Wikipedia Article', 'wordopedia' ),
            'description'         => 'Get a saved Wikipedia article.',
            'category'            => 'wordopedia',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'WordPress post ID from wordopedia/list-saved-articles or wordopedia/save-article.',
                    ],
                ],
                'required'             => [ 'post_id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => self::saved_article_output_schema( true ),
            'execute_callback'    => [ $this, 'ability_get_saved_article' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Present the saved article title linked to view_url and include source_url when citing Wikipedia.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/save-snippet', [
            'label'               => __( 'Save Wikipedia Snippet', 'wordopedia' ),
            'description'         => 'Save or update a Wikipedia snippet.',
            'category'            => 'wordopedia',
            'input_schema'        => self::snippet_save_input_schema(),
            'output_schema'       => self::snippet_output_schema( true ),
            'execute_callback'    => [ $this, 'ability_save_snippet' ],
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Use parent_post_id when the saved article already exists. Otherwise provide page_id or title with language so the article can be saved before the snippet is attached.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => false,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/get-snippet', [
            'label'               => __( 'Get Wikipedia Snippet', 'wordopedia' ),
            'description'         => 'Get a saved Wikipedia snippet.',
            'category'            => 'wordopedia',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'WordPress snippet post ID from wordopedia/search-snippets or wordopedia/save-snippet.',
                    ],
                ],
                'required'             => [ 'post_id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => self::snippet_output_schema( true ),
            'execute_callback'    => [ $this, 'ability_get_snippet' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Present the snippet text and link view_url; use parent_post_id with wordopedia/get-saved-article for full article context.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/search-snippets', [
            'label'               => __( 'Search Wikipedia Snippets', 'wordopedia' ),
            'description'         => 'Search saved Wikipedia snippets.',
            'category'            => 'wordopedia',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'search'         => [
                        'type'        => 'string',
                        'description' => 'Optional search term for snippet text and titles. Omit to list recent snippets.',
                    ],
                    'parent_post_id' => [
                        'type'        => 'integer',
                        'description' => 'Optional saved article parent post ID.',
                    ],
                    'language'       => [
                        'type'        => 'string',
                        'description' => 'Optional Wikipedia language subdomain to filter snippets.',
                    ],
                    'limit'          => [
                        'type'        => 'integer',
                        'description' => 'Maximum number of snippets, from 1 to 50.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'output_schema'       => self::snippet_search_output_schema(),
            'execute_callback'    => [ $this, 'ability_search_snippets' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Use post_id with wordopedia/get-snippet. Use parent_post_id with wordopedia/get-saved-article for the full saved article and all snippets.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'wordopedia/refetch-saved-article', [
            'label'               => __( 'Refetch Saved Wikipedia Article', 'wordopedia' ),
            'description'         => 'Refetch a saved Wikipedia article.',
            'category'            => 'wordopedia',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'WordPress post ID from wordopedia/list-saved-articles.',
                    ],
                ],
                'required'             => [ 'post_id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => self::saved_article_output_schema(),
            'execute_callback'    => [ $this, 'ability_refetch_saved_article' ],
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
            'meta'                => [
                'show_in_rest' => true,
                'annotations' => [
                    'instructions' => 'Report whether the saved article was updated and link view_url.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );
    }

    public function ability_search_articles( $input ) {
        $input    = is_array( $input ) ? $input : [];
        $query    = isset( $input['query'] ) ? sanitize_text_field( $input['query'] ) : '';
        $language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : self::get_default_language();
        $limit    = isset( $input['limit'] ) ? absint( $input['limit'] ) : 10;

        $articles = self::search_wordopedia_articles( $query, $language, $limit );
        if ( is_wp_error( $articles ) ) {
            return $articles;
        }

        $language = self::normalize_language( $language );

        return [
            'query'          => $query,
            'language'       => $language,
            'language_label' => is_wp_error( $language ) ? '' : self::get_language_label( $language ),
            'articles'       => $articles,
        ];
    }

    public function ability_get_article( $input ) {
        $input = is_array( $input ) ? $input : [];
        $article = self::fetch_wordopedia_article( $input );

        if ( is_wp_error( $article ) ) {
            return $article;
        }

        return [
            'article' => $article,
        ];
    }

    public function ability_save_article( $input ) {
        $input = is_array( $input ) ? $input : [];
        $saved = self::save_wordopedia_article( $input );

        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return [
            'article' => $saved,
        ];
    }

    public function ability_list_article_media( $input ) {
        $input = is_array( $input ) ? $input : [];
        $media = self::fetch_wordopedia_article_media( $input );

        if ( is_wp_error( $media ) ) {
            return $media;
        }

        return $media;
    }

    public function ability_get_media_file( $input ) {
        $input = is_array( $input ) ? $input : [];
        $file = self::fetch_wordopedia_media_file( $input );

        if ( is_wp_error( $file ) ) {
            return $file;
        }

        return [
            'file' => $file,
        ];
    }

    public function ability_list_saved_articles( $input ): array {
        $input    = is_array( $input ) ? $input : [];
        $search   = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';
        $language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : '';
        $list     = isset( $input['list'] ) ? sanitize_title( $input['list'] ) : '';
        $limit    = isset( $input['limit'] ) ? absint( $input['limit'] ) : 20;

        return [
            'articles' => self::list_saved_articles( $search, $limit, $language, $list ),
        ];
    }

    public function ability_get_saved_article( $input ) {
        $input = is_array( $input ) ? $input : [];
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $post = $post_id ? get_post( $post_id ) : null;

        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
        }

        return [
            'article' => self::format_ability_article( self::format_saved_article( $post, true ) ),
        ];
    }

    public function ability_refetch_saved_article( $input ) {
        $input = is_array( $input ) ? $input : [];
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $saved = self::refetch_saved_article( $post_id );

        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return [
            'article' => $saved,
        ];
    }

    private static function format_ability_article( array $article ): array {
        if ( ! array_key_exists( 'html', $article ) && array_key_exists( 'content', $article ) ) {
            $article['html'] = $article['content'];
        }

        unset( $article['content'] );

        if ( isset( $article['snippets'] ) && is_array( $article['snippets'] ) ) {
            foreach ( $article['snippets'] as $index => $snippet ) {
                if ( is_array( $snippet ) ) {
                    $article['snippets'][ $index ] = self::format_ability_snippet( $snippet );
                }
            }
        }

        return $article;
    }

    public function register_ai_assistant_ability_domains( array $domains ): array {
        $domains['wordopedia'] = 'Wikipedia, wiki search, encyclopedia browsing, article language versions, article media files, SVG files from articles, Wikimedia Commons files, saved Wikipedia sources, saved article lists, local article source, refetch Wikipedia article, saved snippets, article annotations, selected text snippets';
        return $domains;
    }

    public function register_ai_assistant_welcome_tips( array $tips, array $context ): array {
        $tips['wordopedia'] = [
            __( 'Ask me to search Wikipedia, compare article language versions, or save the best result to Wordopedia.', 'wordopedia' ),
            __( 'Ask me to extract specific facts from an article table into a clean saved snippet, such as a simple list.', 'wordopedia' ),
            __( 'Ask me to find SVG diagrams or logos used by a Wikipedia article, including preview and attribution links.', 'wordopedia' ),
        ];

        return $tips;
    }

    public function get_ai_assistant_ability_instructions( string $instructions, string $ability_id, $args, $result ): string {
        if ( strpos( $ability_id, 'wordopedia/' ) !== 0 || empty( $result ) ) {
            return $instructions;
        }

        if ( 'wordopedia/search-wikipedia' === $ability_id ) {
            return __( 'Present Wikipedia search results as a concise list with title, language, snippet, and app_url. Ask which result to open or save when ambiguous.', 'wordopedia' );
        }

        if ( in_array( $ability_id, [ 'wordopedia/save-article', 'wordopedia/refetch-saved-article' ], true ) ) {
            return __( 'Confirm whether the Wikipedia article was saved or updated, and link it using view_url.', 'wordopedia' );
        }

        if ( in_array( $ability_id, [ 'wordopedia/save-snippet' ], true ) ) {
            return __( 'Confirm the snippet was saved or updated, quote only the relevant snippet text briefly, and link view_url.', 'wordopedia' );
        }

        if ( in_array( $ability_id, [ 'wordopedia/get-snippet', 'wordopedia/search-snippets' ], true ) ) {
            return __( 'Present snippets as concise notes with parent article titles and view_url links. Use parent_post_id for article context when needed.', 'wordopedia' );
        }

        if ( in_array( $ability_id, [ 'wordopedia/get-article', 'wordopedia/get-saved-article' ], true ) ) {
            return __( 'Summarize the article briefly, link app_url or view_url when present, and include source_url when citing Wikipedia.', 'wordopedia' );
        }

        if ( 'wordopedia/list-article-media' === $ability_id ) {
            return __( 'Present media files as concise choices with title, original_url, thumbnail_url, description_url, license, and attribution. For SVG requests, prefer original_url for the actual SVG and thumbnail_url for preview.', 'wordopedia' );
        }

        if ( 'wordopedia/get-media-file' === $ability_id ) {
            return __( 'Present the media file title, original_url, thumbnail_url, description_url, license, attribution, artist, and source. Mention description_url for reuse or licensing details.', 'wordopedia' );
        }

        return $instructions;
    }

    public static function get_app_url( string $path = '' ): string {
        $path = ltrim( $path, '/' );
        return home_url( '/wordopedia/' . $path );
    }

    public static function get_saved_articles_url(): string {
        return self::get_app_url( 'saved' );
    }

    public static function get_saved_snippets_url(): string {
        return self::get_app_url( 'snippets' );
    }

    public static function get_settings_url(): string {
        return self::get_app_url( 'settings' );
    }

    public static function get_list_url( $list ): string {
        $slug = is_object( $list ) && isset( $list->slug ) ? (string) $list->slug : (string) $list;
        return self::get_app_url( 'list/' . sanitize_title( $slug ) );
    }

    public static function get_article_url( string $language, string $title = '', int $page_id = 0 ): string {
        $language = self::normalize_language( $language );
        if ( is_wp_error( $language ) ) {
            $language = self::get_default_language();
        }

        $args = [];
        if ( '' !== $title ) {
            $args['title'] = $title;
        } elseif ( $page_id ) {
            $args['page_id'] = absint( $page_id );
        }

        return add_query_arg( $args, self::get_app_url( 'article/' . $language ) );
    }

    public static function search_wordopedia_articles( string $query, string $language = '', int $limit = 10 ) {
        $query = trim( wp_strip_all_tags( $query ) );
        if ( '' === $query ) {
            return new \WP_Error( 'wordopedia_empty_query', __( 'Enter a search phrase.', 'wordopedia' ) );
        }

        $language = self::normalize_language( $language );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $limit = max( 1, min( 20, absint( $limit ) ) );

        $data = self::request_wikipedia( $language, [
            'action'        => 'query',
            'list'          => 'search',
            'srsearch'      => $query,
            'srlimit'       => $limit,
            'srprop'        => 'snippet|wordcount|timestamp|size',
            'formatversion' => 2,
            'utf8'          => 1,
        ], self::WORDOPEDIA_CACHE_SEARCH );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $results = [];
        foreach ( $data['query']['search'] ?? [] as $item ) {
            $page_id = isset( $item['pageid'] ) ? absint( $item['pageid'] ) : 0;
            if ( ! $page_id ) {
                continue;
            }

            $title = isset( $item['title'] ) ? wp_strip_all_tags( $item['title'] ) : '';

            $results[] = [
                'page_id'        => $page_id,
                'title'          => $title,
                'snippet'        => isset( $item['snippet'] ) ? self::plain_text( $item['snippet'] ) : '',
                'word_count'     => isset( $item['wordcount'] ) ? absint( $item['wordcount'] ) : 0,
                'size'           => isset( $item['size'] ) ? absint( $item['size'] ) : 0,
                'timestamp'      => isset( $item['timestamp'] ) ? sanitize_text_field( $item['timestamp'] ) : '',
                'language'       => $language,
                'language_label' => self::get_language_label( $language ),
                'source_url'     => self::wikipedia_page_url( $language, $title, $page_id ),
                'app_url'        => self::get_article_url( $language, $title, $page_id ),
            ];
        }

        return $results;
    }

    public static function fetch_wordopedia_article( array $input ) {
        $language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : self::get_default_language();
        $language = self::normalize_language( $language );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
        $title   = isset( $input['title'] ) ? trim( wp_strip_all_tags( $input['title'] ) ) : '';

        if ( ! $page_id && '' === $title ) {
            return new \WP_Error( 'wordopedia_missing_article', __( 'Provide a Wikipedia page ID or title.', 'wordopedia' ) );
        }

        $force_refresh = ! empty( $input['force_refresh'] );

        $metadata = self::fetch_article_metadata( $language, $page_id, $title, $force_refresh );
        if ( is_wp_error( $metadata ) ) {
            return $metadata;
        }

        $html = self::fetch_article_html( $language, $metadata['page_id'], $metadata['title'], $force_refresh );
        if ( is_wp_error( $html ) ) {
            return $html;
        }

        $metadata['html']    = $html;
        $metadata['app_url'] = self::get_article_url( $metadata['language'], $metadata['title'], $metadata['page_id'] );

        $saved_article_id = self::find_saved_article_id( $metadata['page_id'], $metadata['language'] );
        if ( $saved_article_id ) {
            $saved_post = get_post( $saved_article_id );
            if ( $saved_post instanceof \WP_Post && self::POST_TYPE === $saved_post->post_type ) {
                $metadata['saved_article'] = self::format_saved_article( $saved_post );
            }
        }

        return $metadata;
    }

    private static function fetch_article_metadata( string $language, int $page_id = 0, string $title = '', bool $force_refresh = false ) {
        $args = [
            'action'          => 'query',
            'prop'            => 'extracts|info|pageimages|langlinks',
            'explaintext'     => 1,
            'exsectionformat' => 'plain',
            'inprop'          => 'url',
            'pithumbsize'     => 1000,
            'lllimit'         => 500,
            'llprop'          => 'url|langname|autonym',
            'redirects'       => 1,
            'formatversion'   => 2,
            'utf8'            => 1,
        ];

        if ( $page_id ) {
            $args['pageids'] = $page_id;
        } else {
            $args['titles'] = $title;
        }

        $data = self::request_wikipedia( $language, $args, self::WORDOPEDIA_CACHE_ARTICLE, $force_refresh );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $pages = $data['query']['pages'] ?? [];
        $page  = is_array( $pages ) ? reset( $pages ) : null;

        if ( ! is_array( $page ) || isset( $page['missing'] ) ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Wikipedia article not found.', 'wordopedia' ) );
        }

        $page_id       = isset( $page['pageid'] ) ? absint( $page['pageid'] ) : $page_id;
        $title         = isset( $page['title'] ) ? wp_strip_all_tags( $page['title'] ) : $title;
        $extract       = isset( $page['extract'] ) ? trim( self::plain_text( $page['extract'] ) ) : '';
        $thumbnail_url = isset( $page['thumbnail']['source'] ) ? esc_url_raw( $page['thumbnail']['source'] ) : '';
        $source_url    = $page['canonicalurl'] ?? ( $page['fullurl'] ?? self::wikipedia_page_url( $language, $title, $page_id ) );

        return [
            'page_id'             => $page_id,
            'title'               => $title,
            'extract'             => $extract,
            'summary'             => wp_trim_words( $extract, 55, '...' ),
            'language'            => $language,
            'language_label'      => self::get_language_label( $language ),
            'available_languages' => self::format_language_links( isset( $page['langlinks'] ) && is_array( $page['langlinks'] ) ? $page['langlinks'] : [], $language ),
            'source_url'          => esc_url_raw( $source_url ),
            'thumbnail_url'       => $thumbnail_url,
            'last_revision_id'    => isset( $page['lastrevid'] ) ? (string) absint( $page['lastrevid'] ) : '',
            'remote_touched'      => isset( $page['touched'] ) ? sanitize_text_field( $page['touched'] ) : '',
        ];
    }

    private static function fetch_article_html( string $language, int $page_id, string $title, bool $force_refresh = false ) {
        $args = [
            'action'             => 'parse',
            'prop'               => 'text',
            'disableeditsection' => 1,
            'disabletoc'         => 0,
            'redirects'          => 1,
            'formatversion'      => 2,
            'utf8'               => 1,
        ];

        if ( $page_id ) {
            $args['pageid'] = $page_id;
        } else {
            $args['page'] = $title;
        }

        $data = self::request_wikipedia( $language, $args, self::WORDOPEDIA_CACHE_ARTICLE, $force_refresh );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $html = '';
        if ( isset( $data['parse']['text'] ) && is_string( $data['parse']['text'] ) ) {
            $html = $data['parse']['text'];
        } elseif ( isset( $data['parse']['text']['*'] ) ) {
            $html = $data['parse']['text']['*'];
        }

        if ( '' === trim( $html ) ) {
            return new \WP_Error( 'wordopedia_empty_article_html', __( 'Wikipedia returned an empty article body.', 'wordopedia' ) );
        }

        return self::sanitize_article_html( $html, $language );
    }

    public static function fetch_wordopedia_article_media( array $input ) {
        $language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : self::get_default_language();
        $language = self::normalize_language( $language );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
        $title   = isset( $input['title'] ) ? trim( wp_strip_all_tags( $input['title'] ) ) : '';

        if ( ! $page_id && '' === $title ) {
            return new \WP_Error( 'wordopedia_missing_article', __( 'Provide a Wikipedia page ID or title.', 'wordopedia' ) );
        }

        $mime            = isset( $input['mime'] ) ? strtolower( trim( sanitize_text_field( $input['mime'] ) ) ) : '';
        $limit           = isset( $input['limit'] ) ? absint( $input['limit'] ) : 20;
        $limit           = max( 1, min( 50, $limit ) );
        $thumbnail_width = self::normalize_media_thumbnail_width( $input['thumbnail_width'] ?? 512 );
        $force_refresh   = ! empty( $input['force_refresh'] );
        $candidate_limit = self::article_media_candidate_limit( $mime, $limit );

        $article_media = self::fetch_article_media_titles( $language, $page_id, $title, $candidate_limit, $force_refresh );
        if ( is_wp_error( $article_media ) ) {
            return $article_media;
        }

        $file_titles = $article_media['file_titles'];
        if ( '' !== $mime ) {
            $file_titles = array_values( array_filter( $file_titles, function( $file_title ) use ( $mime ) {
                return self::media_title_matches_mime_hint( $file_title, $mime );
            } ) );
        }

        if ( '' === $mime ) {
            $file_titles = array_slice( $file_titles, 0, $limit );
        } elseif ( 'image/svg+xml' === $mime ) {
            $file_titles = array_slice( $file_titles, 0, $limit );
        }

        $media = [];
        if ( $file_titles ) {
            $media = self::fetch_media_files( $language, $file_titles, $thumbnail_width, $force_refresh );
            if ( is_wp_error( $media ) ) {
                return $media;
            }
        }

        if ( '' !== $mime ) {
            $media = array_values( array_filter( $media, function( $file ) use ( $mime ) {
                return isset( $file['mime'] ) && strtolower( (string) $file['mime'] ) === $mime;
            } ) );
        }

        $media = array_slice( $media, 0, $limit );

        return [
            'article'          => $article_media['article'],
            'mime_filter'      => $mime,
            'count'            => count( $media ),
            'total_candidates' => count( $article_media['file_titles'] ),
            'more_available'   => ! empty( $article_media['more_available'] ),
            'media'            => $media,
        ];
    }

    public static function fetch_wordopedia_media_file( array $input ) {
        $language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : self::get_default_language();
        $language = self::normalize_language( $language );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $file_title = isset( $input['file_title'] ) ? self::normalize_media_file_title( (string) $input['file_title'] ) : '';
        if ( '' === $file_title ) {
            return new \WP_Error( 'wordopedia_missing_media_file', __( 'Provide a Wikimedia file title, such as File:Example.svg.', 'wordopedia' ) );
        }

        $thumbnail_width = self::normalize_media_thumbnail_width( $input['thumbnail_width'] ?? 512 );
        $force_refresh   = ! empty( $input['force_refresh'] );
        $files           = self::fetch_media_files( $language, [ $file_title ], $thumbnail_width, $force_refresh );

        if ( is_wp_error( $files ) ) {
            return $files;
        }

        if ( ! $files ) {
            return new \WP_Error( 'wordopedia_media_file_not_found', __( 'Wikipedia media file not found.', 'wordopedia' ) );
        }

        return $files[0];
    }

    private static function fetch_article_media_titles( string $language, int $page_id, string $title, int $max_titles = 500, bool $force_refresh = false ) {
        $max_titles = max( 1, min( 500, absint( $max_titles ) ) );
        $file_titles = [];
        $seen_titles = [];
        $article = null;
        $imcontinue = '';
        $more_available = false;

        do {
            $args = [
                'action'        => 'query',
                'prop'          => 'images|info',
                'imlimit'       => min( 500, max( 1, $max_titles - count( $file_titles ) ) ),
                'inprop'        => 'url',
                'redirects'     => 1,
                'formatversion' => 2,
                'utf8'          => 1,
            ];

            if ( $page_id ) {
                $args['pageids'] = $page_id;
            } else {
                $args['titles'] = $title;
            }

            if ( '' !== $imcontinue ) {
                $args['imcontinue'] = $imcontinue;
            }

            $data = self::request_wikipedia( $language, $args, self::WORDOPEDIA_CACHE_MEDIA, $force_refresh );
            if ( is_wp_error( $data ) ) {
                return $data;
            }

            $pages = $data['query']['pages'] ?? [];
            $page  = is_array( $pages ) ? reset( $pages ) : null;

            if ( ! is_array( $page ) || isset( $page['missing'] ) ) {
                return new \WP_Error( 'wordopedia_article_not_found', __( 'Wikipedia article not found.', 'wordopedia' ) );
            }

            if ( null === $article ) {
                $article_page_id = isset( $page['pageid'] ) ? absint( $page['pageid'] ) : $page_id;
                $article_title   = isset( $page['title'] ) ? wp_strip_all_tags( $page['title'] ) : $title;
                $source_url      = $page['canonicalurl'] ?? ( $page['fullurl'] ?? self::wikipedia_page_url( $language, $article_title, $article_page_id ) );

                $article = [
                    'page_id'        => $article_page_id,
                    'title'          => $article_title,
                    'language'       => $language,
                    'language_label' => self::get_language_label( $language ),
                    'source_url'     => esc_url_raw( $source_url ),
                    'app_url'        => self::get_article_url( $language, $article_title, $article_page_id ),
                ];
            }

            foreach ( $page['images'] ?? [] as $image ) {
                if ( ! is_array( $image ) || empty( $image['title'] ) ) {
                    continue;
                }

                $file_title = self::normalize_media_file_title( (string) $image['title'] );
                $key = strtolower( $file_title );
                if ( '' === $file_title || isset( $seen_titles[ $key ] ) ) {
                    continue;
                }

                $seen_titles[ $key ] = true;
                $file_titles[] = $file_title;
                if ( count( $file_titles ) >= $max_titles ) {
                    break;
                }
            }

            $imcontinue = isset( $data['continue']['imcontinue'] ) ? sanitize_text_field( $data['continue']['imcontinue'] ) : '';
            $more_available = '' !== $imcontinue;
        } while ( $imcontinue && count( $file_titles ) < $max_titles );

        return [
            'article'        => $article ?: [
                'page_id'        => $page_id,
                'title'          => $title,
                'language'       => $language,
                'language_label' => self::get_language_label( $language ),
                'source_url'     => self::wikipedia_page_url( $language, $title, $page_id ),
                'app_url'        => self::get_article_url( $language, $title, $page_id ),
            ],
            'file_titles'    => $file_titles,
            'more_available' => $more_available,
        ];
    }

    private static function fetch_media_files( string $language, array $file_titles, int $thumbnail_width = 512, bool $force_refresh = false ) {
        $thumbnail_width = self::normalize_media_thumbnail_width( $thumbnail_width );
        $normalized_titles = [];

        foreach ( $file_titles as $file_title ) {
            if ( ! is_scalar( $file_title ) ) {
                continue;
            }

            $file_title = self::normalize_media_file_title( (string) $file_title );
            if ( '' !== $file_title && ! in_array( $file_title, $normalized_titles, true ) ) {
                $normalized_titles[] = $file_title;
            }
        }

        if ( ! $normalized_titles ) {
            return [];
        }

        $files = [];
        foreach ( array_chunk( $normalized_titles, 50 ) as $chunk ) {
            $data = self::request_wikipedia( $language, [
                'action'                 => 'query',
                'prop'                   => 'imageinfo',
                'titles'                 => implode( '|', $chunk ),
                'iiprop'                 => 'canonicaltitle|url|size|mime|mediatype|sha1|extmetadata',
                'iiurlwidth'             => $thumbnail_width,
                'iiextmetadatalanguage'  => $language,
                'iiextmetadatafilter'    => implode( '|', self::media_extmetadata_keys() ),
                'iimetadataversion'      => 'latest',
                'formatversion'          => 2,
                'utf8'                   => 1,
            ], self::WORDOPEDIA_CACHE_MEDIA, $force_refresh );

            if ( is_wp_error( $data ) ) {
                return $data;
            }

            foreach ( $data['query']['pages'] ?? [] as $page ) {
                if ( ! is_array( $page ) ) {
                    continue;
                }

                $file = self::format_media_file_page( $page );
                if ( $file ) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private static function format_media_file_page( array $page ): array {
        $image_info = $page['imageinfo'][0] ?? null;
        if ( ! is_array( $image_info ) ) {
            return [];
        }

        $title = isset( $image_info['canonicaltitle'] ) ? (string) $image_info['canonicaltitle'] : (string) ( $page['title'] ?? '' );
        $title = self::normalize_media_file_title( $title );
        if ( '' === $title ) {
            return [];
        }

        $metadata = self::format_media_extmetadata( isset( $image_info['extmetadata'] ) && is_array( $image_info['extmetadata'] ) ? $image_info['extmetadata'] : [] );

        return [
            'page_id'         => isset( $page['pageid'] ) ? absint( $page['pageid'] ) : 0,
            'title'           => $title,
            'filename'        => self::media_filename_from_title( $title ),
            'canonical_title' => isset( $image_info['canonicaltitle'] ) ? sanitize_text_field( $image_info['canonicaltitle'] ) : $title,
            'mime'            => isset( $image_info['mime'] ) ? sanitize_text_field( $image_info['mime'] ) : '',
            'media_type'      => isset( $image_info['mediatype'] ) ? sanitize_text_field( $image_info['mediatype'] ) : '',
            'repository'      => isset( $page['imagerepository'] ) ? sanitize_text_field( $page['imagerepository'] ) : '',
            'original_url'    => isset( $image_info['url'] ) ? esc_url_raw( $image_info['url'] ) : '',
            'thumbnail_url'   => isset( $image_info['thumburl'] ) ? esc_url_raw( $image_info['thumburl'] ) : '',
            'description_url' => isset( $image_info['descriptionurl'] ) ? esc_url_raw( $image_info['descriptionurl'] ) : '',
            'width'           => isset( $image_info['width'] ) ? absint( $image_info['width'] ) : 0,
            'height'          => isset( $image_info['height'] ) ? absint( $image_info['height'] ) : 0,
            'size'            => isset( $image_info['size'] ) ? absint( $image_info['size'] ) : 0,
            'sha1'            => isset( $image_info['sha1'] ) ? sanitize_text_field( $image_info['sha1'] ) : '',
            'description'     => $metadata['ImageDescription'] ?? ( $metadata['ObjectName'] ?? '' ),
            'license'         => $metadata['LicenseShortName'] ?? ( $metadata['UsageTerms'] ?? ( $metadata['License'] ?? '' ) ),
            'license_url'     => isset( $metadata['LicenseUrl'] ) ? esc_url_raw( $metadata['LicenseUrl'] ) : '',
            'usage_terms'     => $metadata['UsageTerms'] ?? '',
            'artist'          => $metadata['Artist'] ?? '',
            'credit'          => $metadata['Credit'] ?? '',
            'attribution'     => $metadata['Attribution'] ?? '',
            'source'          => $metadata['Source'] ?? '',
            'metadata'        => $metadata,
        ];
    }

    private static function format_media_extmetadata( array $extmetadata ): array {
        $metadata = [];

        foreach ( $extmetadata as $key => $item ) {
            if ( ! is_string( $key ) || ! is_array( $item ) || ! array_key_exists( 'value', $item ) || ! is_scalar( $item['value'] ) ) {
                continue;
            }

            $value = self::plain_text( (string) $item['value'] );
            if ( '' === $value ) {
                continue;
            }

            $metadata[ $key ] = $value;
        }

        return $metadata;
    }

    private static function normalize_media_file_title( string $file_title ): string {
        $file_title = trim( html_entity_decode( wp_strip_all_tags( $file_title ), ENT_QUOTES, 'UTF-8' ) );
        if ( '' === $file_title ) {
            return '';
        }

        if ( preg_match( '~^https?://~i', $file_title ) ) {
            $parts = wp_parse_url( $file_title );
            if ( ! is_array( $parts ) || empty( $parts['path'] ) || strpos( $parts['path'], '/wiki/' ) !== 0 ) {
                return '';
            }

            $file_title = substr( $parts['path'], 6 );
        }

        if ( strpos( $file_title, '/wiki/' ) === 0 ) {
            $file_title = substr( $file_title, 6 );
        }

        $file_title = strtok( $file_title, '#' );
        $file_title = is_string( $file_title ) ? $file_title : '';
        $file_title = rawurldecode( $file_title );
        $file_title = str_replace( '_', ' ', $file_title );
        $file_title = preg_replace( '/\s+/', ' ', $file_title );
        $file_title = is_string( $file_title ) ? trim( $file_title ) : '';
        if ( '' === $file_title ) {
            return '';
        }

        $matches = [];
        if ( preg_match( '/^(file|image)\s*:\s*(.+)$/i', $file_title, $matches ) ) {
            $file_title = trim( $matches[2] );
        } elseif (
            preg_match( '/^[^:]{2,40}\s*:\s*(.+)$/u', $file_title, $matches ) &&
            self::looks_like_media_filename( $matches[1] )
        ) {
            $file_title = trim( $matches[1] );
        }

        return '' === $file_title ? '' : 'File:' . $file_title;
    }

    private static function looks_like_media_filename( string $file_title ): bool {
        return (bool) preg_match( '/\.(?:apng|avif|bmp|gif|heic|heif|ico|jpe?g|jxl|mid|midi|oga|ogg|ogv|opus|pdf|png|stl|svgz?|tiff?|webm|webp|wav)$/i', trim( $file_title ) );
    }

    private static function media_filename_from_title( string $file_title ): string {
        $file_title = self::normalize_media_file_title( $file_title );
        return preg_replace( '/^File:/i', '', $file_title );
    }

    private static function normalize_media_thumbnail_width( $thumbnail_width ): int {
        $thumbnail_width = is_scalar( $thumbnail_width ) ? absint( $thumbnail_width ) : 512;
        if ( ! $thumbnail_width ) {
            $thumbnail_width = 512;
        }

        return max( 64, min( 2000, $thumbnail_width ) );
    }

    private static function article_media_candidate_limit( string $mime, int $limit ): int {
        if ( '' === $mime ) {
            return $limit;
        }

        if ( 'image/svg+xml' === $mime ) {
            return 500;
        }

        return min( 500, max( 100, $limit * 5 ) );
    }

    private static function media_title_matches_mime_hint( string $file_title, string $mime ): bool {
        $mime = strtolower( trim( $mime ) );
        if ( 'image/svg+xml' === $mime ) {
            return (bool) preg_match( '/\.svgz?$/i', self::media_filename_from_title( $file_title ) );
        }

        return true;
    }

    private static function media_extmetadata_keys(): array {
        return [
            'Attribution',
            'Artist',
            'Credit',
            'DateTime',
            'ImageDescription',
            'License',
            'LicenseShortName',
            'LicenseUrl',
            'ObjectName',
            'Source',
            'UsageTerms',
        ];
    }

    public static function save_wordopedia_article( array $input ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'wordopedia_cannot_save', __( 'You are not allowed to save Wikipedia articles.', 'wordopedia' ) );
        }

        $article = self::fetch_wordopedia_article( $input );
        if ( is_wp_error( $article ) ) {
            return $article;
        }

        $existing_id = self::find_saved_article_id( $article['page_id'], $article['language'] );
        if ( $existing_id && ! current_user_can( 'edit_post', $existing_id ) ) {
            return new \WP_Error( 'wordopedia_cannot_update', __( 'You are not allowed to update this Wikipedia article.', 'wordopedia' ) );
        }

        $post_data = [
            'post_type'    => self::POST_TYPE,
            'post_title'   => $article['title'],
            'post_content' => $article['html'],
            'post_excerpt' => $article['summary'],
            'post_status'  => 'publish',
            'post_name'    => self::build_article_slug( $article['language'], $article['title'], $article['page_id'] ),
        ];

        if ( $existing_id ) {
            $post_data['ID'] = $existing_id;
            $post_id = wp_update_post( $post_data, true );
            $created = false;
        } else {
            $post_data['post_author'] = get_current_user_id();
            $post_id = wp_insert_post( $post_data, true );
            $created = true;
        }

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        self::update_article_origin_meta( $post_id, $article, $created );

        return self::format_saved_article( get_post( $post_id ), false, [
            'created' => $created,
            'updated' => ! $created,
        ] );
    }

    public static function refetch_saved_article( int $post_id ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'wordopedia_cannot_refetch', __( 'You are not allowed to refetch Wikipedia articles.', 'wordopedia' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new \WP_Error( 'wordopedia_cannot_refetch', __( 'You are not allowed to refetch this Wikipedia article.', 'wordopedia' ) );
        }

        $page_id  = absint( get_post_meta( $post_id, self::META_PAGE_ID, true ) );
        $language = (string) get_post_meta( $post_id, self::META_LANGUAGE, true );

        if ( ! $page_id || '' === $language ) {
            return new \WP_Error( 'wordopedia_missing_origin', __( 'This saved article is missing its Wikipedia origin metadata.', 'wordopedia' ) );
        }

        return self::save_wordopedia_article( [
            'page_id'       => $page_id,
            'language'      => $language,
            'force_refresh' => true,
        ] );
    }

    public static function sideload_saved_article_images( int $post_id, array $image_urls ) {
        if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
            return new \WP_Error( 'wordopedia_cannot_import_images', __( 'You are not allowed to download Wikipedia article images.', 'wordopedia' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new \WP_Error( 'wordopedia_cannot_import_images', __( 'You are not allowed to update this Wikipedia article.', 'wordopedia' ) );
        }

        $available = [];
        foreach ( self::article_images_from_html( $post->post_content ) as $image ) {
            if ( empty( $image['url'] ) || ! empty( $image['is_local'] ) ) {
                continue;
            }

            $available[ $image['url'] ] = true;
        }

        $selected = [];
        foreach ( $image_urls as $image_url ) {
            if ( ! is_scalar( $image_url ) ) {
                continue;
            }

            $image_url = self::normalize_article_image_url( (string) $image_url );
            if ( $image_url && isset( $available[ $image_url ] ) ) {
                $selected[ $image_url ] = true;
            }
        }

        if ( ! $selected ) {
            return new \WP_Error( 'wordopedia_no_images_selected', __( 'Choose at least one remote article image to download.', 'wordopedia' ) );
        }

        $url_map = [];
        $failed = 0;
        foreach ( array_keys( $selected ) as $source_url ) {
            $imported = self::sideload_article_image( $source_url, $post_id );
            if ( is_wp_error( $imported ) ) {
                $failed++;
                continue;
            }

            if ( ! empty( $imported['url'] ) ) {
                $url_map[ $source_url ] = $imported['url'];
            }
        }

        if ( ! $url_map ) {
            return new \WP_Error( 'wordopedia_image_import_failed', __( 'The selected images could not be downloaded.', 'wordopedia' ) );
        }

        $updated = wp_update_post( [
            'ID'           => $post_id,
            'post_content' => wp_kses( self::rewrite_article_image_urls( $post->post_content, $url_map ), self::article_allowed_html() ),
        ], true );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $thumbnail_url = self::normalize_article_image_url( (string) get_post_meta( $post_id, self::META_THUMBNAIL_URL, true ) );
        if ( $thumbnail_url && isset( $url_map[ $thumbnail_url ] ) ) {
            update_post_meta( $post_id, self::META_THUMBNAIL_URL, esc_url_raw( $url_map[ $thumbnail_url ] ) );
        }

        return [
            'post_id'  => $post_id,
            'imported' => count( $url_map ),
            'failed'   => $failed,
            'view_url' => self::get_saved_article_view_url( $post ),
            'images'   => $url_map,
        ];
    }

    public static function article_images_from_html( string $html ): array {
        if ( '' === trim( $html ) ) {
            return [];
        }

        if ( ! class_exists( '\DOMDocument' ) ) {
            return self::article_images_from_html_fallback( $html );
        }

        $previous = libxml_use_internal_errors( true );
        $document = new \DOMDocument();
        $flags = 0;
        if ( defined( 'LIBXML_HTML_NOIMPLIED' ) ) {
            $flags |= LIBXML_HTML_NOIMPLIED;
        }
        if ( defined( 'LIBXML_HTML_NODEFDTD' ) ) {
            $flags |= LIBXML_HTML_NODEFDTD;
        }

        $loaded = $document->loadHTML( '<?xml encoding="utf-8" ?><div id="wordopedia-app-article-root">' . $html . '</div>', $flags );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return self::article_images_from_html_fallback( $html );
        }

        $images = [];
        $seen = [];
        foreach ( $document->getElementsByTagName( 'img' ) as $image ) {
            if ( ! self::is_visible_article_image( $image ) ) {
                continue;
            }

            $url = self::normalize_article_image_url( $image->getAttribute( 'src' ) );
            if ( ! $url ) {
                $srcset_urls = self::article_srcset_urls( $image->getAttribute( 'srcset' ) );
                $url = $srcset_urls ? $srcset_urls[0] : '';
            }

            if ( ! $url || isset( $seen[ $url ] ) ) {
                continue;
            }

            $seen[ $url ] = true;
            $images[] = self::format_article_image( $url, $image->getAttribute( 'alt' ), $image->getAttribute( 'width' ), $image->getAttribute( 'height' ) );
        }

        return $images;
    }

    private static function is_visible_article_image( \DOMElement $image ): bool {
        $hidden_classes = [ 'mw-editsection', 'noprint', 'metadata', 'ambox' ];

        for ( $node = $image; $node instanceof \DOMElement; $node = $node->parentNode ) {
            if ( $node->hasAttribute( 'hidden' ) || 'true' === strtolower( $node->getAttribute( 'aria-hidden' ) ) ) {
                return false;
            }

            $style = strtolower( $node->getAttribute( 'style' ) );
            if ( preg_match( '/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden)\b/', $style ) ) {
                return false;
            }

            $classes = preg_split( '/\s+/', strtolower( $node->getAttribute( 'class' ) ) );
            foreach ( is_array( $classes ) ? $classes : [] as $class ) {
                if ( in_array( $class, $hidden_classes, true ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function article_images_from_html_fallback( string $html ): array {
        preg_match_all( '~<img\b[^>]*>~i', $html, $matches );

        $images = [];
        $seen = [];
        foreach ( $matches[0] ?? [] as $tag ) {
            $url = self::normalize_article_image_url( self::html_attribute_from_tag( $tag, 'src' ) );
            if ( ! $url ) {
                $srcset_urls = self::article_srcset_urls( self::html_attribute_from_tag( $tag, 'srcset' ) );
                $url = $srcset_urls ? $srcset_urls[0] : '';
            }

            if ( ! $url || isset( $seen[ $url ] ) ) {
                continue;
            }

            $seen[ $url ] = true;
            $images[] = self::format_article_image(
                $url,
                self::html_attribute_from_tag( $tag, 'alt' ),
                self::html_attribute_from_tag( $tag, 'width' ),
                self::html_attribute_from_tag( $tag, 'height' )
            );
        }

        return $images;
    }

    private static function html_attribute_from_tag( string $tag, string $attribute ): string {
        $attribute = preg_quote( $attribute, '~' );

        if ( preg_match( '~\s' . $attribute . '\s*=\s*(["\'])(.*?)\1~is', $tag, $matches ) ) {
            return html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' );
        }

        if ( preg_match( '~\s' . $attribute . '\s*=\s*([^\s>]+)~is', $tag, $matches ) ) {
            return html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
        }

        return '';
    }

    private static function format_article_image( string $url, string $alt = '', string $width = '', string $height = '' ): array {
        $alt = trim( sanitize_text_field( html_entity_decode( $alt, ENT_QUOTES, 'UTF-8' ) ) );

        return [
            'url'       => $url,
            'label'     => $alt ?: self::article_image_filename( $url ),
            'alt'       => $alt,
            'width'     => absint( $width ),
            'height'    => absint( $height ),
            'host'      => self::article_image_host( $url ),
            'is_local'  => self::is_local_article_image_url( $url ),
        ];
    }

    private static function article_image_filename( string $url ): string {
        $parts = wp_parse_url( $url );
        $path = is_array( $parts ) && ! empty( $parts['path'] ) ? (string) $parts['path'] : '';
        $filename = $path ? rawurldecode( basename( $path ) ) : '';

        return $filename ? sanitize_text_field( $filename ) : __( 'Article image', 'wordopedia' );
    }

    private static function article_image_host( string $url ): string {
        $parts = wp_parse_url( $url );
        return is_array( $parts ) && ! empty( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
    }

    private static function is_local_article_image_url( string $url ): bool {
        $host = self::article_image_host( $url );
        $home_parts = wp_parse_url( home_url( '/' ) );
        $home_host = is_array( $home_parts ) && ! empty( $home_parts['host'] ) ? strtolower( (string) $home_parts['host'] ) : '';

        return $host && $home_host && $host === $home_host;
    }

    private static function article_srcset_urls( string $srcset ): array {
        $urls = [];
        $candidates = preg_split( '/\s*,\s*/', trim( $srcset ) );
        foreach ( is_array( $candidates ) ? $candidates : [] as $candidate ) {
            $parts = preg_split( '/\s+/', trim( $candidate ) );
            $url = $parts ? self::normalize_article_image_url( $parts[0] ) : '';
            if ( $url && ! in_array( $url, $urls, true ) ) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private static function normalize_article_image_url( string $url ): string {
        $url = trim( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
        if ( '' === $url ) {
            return '';
        }

        if ( 0 === strpos( $url, '//' ) ) {
            $url = 'https:' . $url;
        }

        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return '';
        }

        $scheme = strtolower( (string) $parts['scheme'] );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
            return '';
        }

        return esc_url_raw( $url );
    }

    private static function sideload_article_image( string $url, int $post_id ) {
        self::include_media_sideload_dependencies();

        $attachment_id = self::find_sideloaded_article_image_id( $url );
        if ( ! $attachment_id ) {
            $attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
        }

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        $attachment_id = absint( $attachment_id );
        $local_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
        if ( ! $local_url ) {
            return new \WP_Error( 'wordopedia_image_import_failed', __( 'The image was downloaded but its media URL could not be found.', 'wordopedia' ) );
        }

        update_post_meta( $attachment_id, '_wordopedia_source_image_url', esc_url_raw( $url ) );

        return [
            'attachment_id' => $attachment_id,
            'url'           => esc_url_raw( $local_url ),
        ];
    }

    private static function include_media_sideload_dependencies(): void {
        if ( ! function_exists( 'media_sideload_image' ) && defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }

    private static function find_sideloaded_article_image_id( string $url ): int {
        if ( ! function_exists( 'get_posts' ) ) {
            return 0;
        }

        $attachments = get_posts( [
            'post_type'              => 'attachment',
            'post_status'            => 'inherit',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [
                'relation' => 'OR',
                [
                    'key'   => '_source_url',
                    'value' => $url,
                ],
                [
                    'key'   => '_wordopedia_source_image_url',
                    'value' => $url,
                ],
            ],
        ] );

        return $attachments ? absint( $attachments[0] ) : 0;
    }

    private static function rewrite_article_image_urls( string $html, array $url_map ): string {
        $normalized_map = [];
        foreach ( $url_map as $source_url => $replacement_url ) {
            if ( ! is_scalar( $source_url ) || ! is_scalar( $replacement_url ) ) {
                continue;
            }

            $source_url = self::normalize_article_image_url( (string) $source_url );
            $replacement_url = esc_url_raw( (string) $replacement_url );
            if ( $source_url && $replacement_url ) {
                $normalized_map[ $source_url ] = $replacement_url;
            }
        }

        if ( ! $normalized_map || '' === trim( $html ) ) {
            return $html;
        }

        if ( ! class_exists( '\DOMDocument' ) ) {
            return self::rewrite_article_image_urls_fallback( $html, $normalized_map );
        }

        $previous = libxml_use_internal_errors( true );
        $document = new \DOMDocument();
        $flags = 0;
        if ( defined( 'LIBXML_HTML_NOIMPLIED' ) ) {
            $flags |= LIBXML_HTML_NOIMPLIED;
        }
        if ( defined( 'LIBXML_HTML_NODEFDTD' ) ) {
            $flags |= LIBXML_HTML_NODEFDTD;
        }

        $loaded = $document->loadHTML( '<?xml encoding="utf-8" ?><div id="wordopedia-app-article-root">' . $html . '</div>', $flags );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return self::rewrite_article_image_urls_fallback( $html, $normalized_map );
        }

        foreach ( $document->getElementsByTagName( 'img' ) as $image ) {
            $source_url = self::matching_article_image_url( $image, $normalized_map );
            if ( ! $source_url ) {
                continue;
            }

            $image->setAttribute( 'src', $normalized_map[ $source_url ] );
            $image->removeAttribute( 'srcset' );
            $image->removeAttribute( 'sizes' );
        }

        $root = $document->getElementById( 'wordopedia-app-article-root' );
        if ( ! $root ) {
            return $html;
        }

        $rewritten = '';
        foreach ( $root->childNodes as $child ) {
            $rewritten .= $document->saveHTML( $child );
        }

        return $rewritten;
    }

    private static function matching_article_image_url( \DOMElement $image, array $url_map ): string {
        $src = self::normalize_article_image_url( $image->getAttribute( 'src' ) );
        if ( $src && isset( $url_map[ $src ] ) ) {
            return $src;
        }

        foreach ( self::article_srcset_urls( $image->getAttribute( 'srcset' ) ) as $srcset_url ) {
            if ( isset( $url_map[ $srcset_url ] ) ) {
                return $srcset_url;
            }
        }

        return '';
    }

    private static function rewrite_article_image_urls_fallback( string $html, array $url_map ): string {
        foreach ( $url_map as $source_url => $replacement_url ) {
            $html = str_replace( [ $source_url, preg_replace( '~^https?:~', '', $source_url ) ], $replacement_url, $html );
        }

        return $html;
    }

    private static function update_article_origin_meta( int $post_id, array $article, bool $created ): void {
        update_post_meta( $post_id, self::META_PAGE_ID, absint( $article['page_id'] ) );
        update_post_meta( $post_id, self::META_LANGUAGE, sanitize_text_field( $article['language'] ) );
        update_post_meta( $post_id, self::META_SOURCE_URL, esc_url_raw( $article['source_url'] ) );
        update_post_meta( $post_id, self::META_THUMBNAIL_URL, esc_url_raw( $article['thumbnail_url'] ) );
        update_post_meta( $post_id, self::META_LAST_REVISION, sanitize_text_field( $article['last_revision_id'] ) );
        update_post_meta( $post_id, self::META_REMOTE_TOUCHED, sanitize_text_field( $article['remote_touched'] ) );

        if ( $created || ! get_post_meta( $post_id, self::META_SAVED_AT, true ) ) {
            update_post_meta( $post_id, self::META_SAVED_AT, current_time( 'mysql' ) );
        }

        if ( ! $created ) {
            update_post_meta( $post_id, self::META_REFETCHED_AT, current_time( 'mysql' ) );
        }
    }

    public static function list_saved_articles( string $search = '', int $limit = 20, string $language = '', string $list = '' ): array {
        $limit = max( 1, min( 50, absint( $limit ) ) );
        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $search = trim( $search );
        if ( '' !== $search ) {
            $args['s'] = $search;
        }

        $language = trim( $language );
        if ( '' !== $language ) {
            $language = self::normalize_language( $language );
            if ( ! is_wp_error( $language ) ) {
                $args['meta_query'] = [
                    [
                        'key'   => self::META_LANGUAGE,
                        'value' => $language,
                    ],
                ];
            }
        }

        $list = sanitize_title( $list );
        if ( '' !== $list ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => self::TAX_LIST,
                    'field'    => 'slug',
                    'terms'    => $list,
                ],
            ];
        }

        $posts = get_posts( $args );
        $articles = [];

        foreach ( $posts as $post ) {
            $extra = [];
            if ( '' !== $search ) {
                $extra['search_snippet'] = self::build_saved_article_search_snippet( $post, $search );
            }

            $articles[] = self::format_saved_article( $post, false, $extra );
        }

        return $articles;
    }

    public static function group_articles_by_initial( array $articles ): array {
        usort( $articles, function( $a, $b ) {
            $a_title = is_array( $a ) && isset( $a['title'] ) ? (string) $a['title'] : '';
            $b_title = is_array( $b ) && isset( $b['title'] ) ? (string) $b['title'] : '';
            return strcasecmp( $a_title, $b_title );
        } );

        $groups = [];
        foreach ( $articles as $article ) {
            if ( ! is_array( $article ) ) {
                continue;
            }

            $title  = trim( (string) ( $article['title'] ?? '' ) );
            $letter = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
            $letter = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letter ) : strtoupper( $letter );
            if ( '' === $letter || ! preg_match( '/[[:alpha:]]/u', $letter ) ) {
                $letter = '#';
            }

            if ( ! isset( $groups[ $letter ] ) ) {
                $groups[ $letter ] = [];
            }

            $groups[ $letter ][] = $article;
        }

        uksort( $groups, function( $a, $b ) {
            if ( '#' === $a ) {
                return 1;
            }
            if ( '#' === $b ) {
                return -1;
            }
            return strcasecmp( $a, $b );
        } );

        return $groups;
    }

    public static function format_saved_article( $post, bool $include_content = false, array $extra = [] ): array {
        if ( ! $post instanceof \WP_Post ) {
            return [];
        }

        $post_id  = (int) $post->ID;
        $language = (string) get_post_meta( $post_id, self::META_LANGUAGE, true );
        $page_id  = absint( get_post_meta( $post_id, self::META_PAGE_ID, true ) );

        $saved_at = (string) get_post_meta( $post_id, self::META_SAVED_AT, true );
        $refetched_at = (string) get_post_meta( $post_id, self::META_REFETCHED_AT, true );
        $last_saved_at = self::latest_datetime( [ $saved_at, $refetched_at ] );

        $article = [
            'post_id'             => $post_id,
            'id'                  => $post_id,
            'title'               => get_the_title( $post ),
            'status'              => get_post_status( $post ),
            'summary'             => $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '...' ),
            'page_id'             => $page_id,
            'language'            => $language,
            'language_label'      => self::get_language_label( $language ),
            'source_url'          => (string) get_post_meta( $post_id, self::META_SOURCE_URL, true ),
            'thumbnail_url'       => (string) get_post_meta( $post_id, self::META_THUMBNAIL_URL, true ),
            'last_revision_id'    => (string) get_post_meta( $post_id, self::META_LAST_REVISION, true ),
            'remote_touched'      => (string) get_post_meta( $post_id, self::META_REMOTE_TOUCHED, true ),
            'saved_at'            => $saved_at,
            'saved_at_display'    => self::format_datetime( $saved_at ),
            'refetched_at'        => $refetched_at,
            'refetched_at_display' => self::format_datetime( $refetched_at ),
            'last_saved_at'       => $last_saved_at,
            'last_saved_at_display' => self::format_datetime( $last_saved_at ),
            'view_url'            => self::get_app_url( 'saved/' . ( $post->post_name ?: $post_id ) ),
            'live_app_url'        => self::get_article_url( $language, get_the_title( $post ), $page_id ),
            'app_url'             => self::get_article_url( $language, get_the_title( $post ), $page_id ),
            'edit_url'            => get_edit_post_link( $post_id, '' ) ?: '',
            'lists'               => self::format_article_lists( $post_id ),
            'available_languages' => [],
        ];

        if ( $include_content ) {
            $article['content'] = $post->post_content;
            $article['html']    = $post->post_content;
            $article['snippets'] = self::get_saved_article_snippets( $post_id, true );
        }

        return array_merge( $article, $extra );
    }

    public static function format_datetime( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        $format = 'M j, Y';
        if ( function_exists( 'get_option' ) ) {
            $date_format = (string) get_option( 'date_format' );
            $format = trim( $date_format ) ?: $format;
        }

        if ( function_exists( 'mysql2date' ) ) {
            return mysql2date( $format, $value );
        }

        $timestamp = strtotime( $value );
        return $timestamp ? date( $format, $timestamp ) : $value;
    }

    private static function latest_datetime( array $values ): string {
        $latest = '';
        $latest_timestamp = 0;

        foreach ( $values as $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }

            $value = trim( (string) $value );
            if ( '' === $value ) {
                continue;
            }

            $timestamp = strtotime( $value );
            if ( ! $timestamp ) {
                if ( '' === $latest ) {
                    $latest = $value;
                }
                continue;
            }

            if ( $timestamp >= $latest_timestamp ) {
                $latest = $value;
                $latest_timestamp = $timestamp;
            }
        }

        return $latest;
    }

    private static function build_saved_article_search_snippet( $post, string $search ): string {
        if ( ! $post instanceof \WP_Post ) {
            return '';
        }

        $search = trim( wp_strip_all_tags( $search ) );
        if ( '' === $search ) {
            return '';
        }

        $content = wp_strip_all_tags( $post->post_content );
        $content = preg_replace( '/\s+/', ' ', $content );
        $content = is_string( $content ) ? trim( $content ) : '';
        if ( '' === $content ) {
            return '';
        }

        $terms = preg_split( '/\s+/', $search );
        $terms = array_values( array_filter( array_map( 'trim', is_array( $terms ) ? $terms : [] ) ) );
        if ( ! $terms ) {
            return '';
        }

        $position = false;
        foreach ( $terms as $term ) {
            if ( function_exists( 'mb_stripos' ) ) {
                $position = mb_stripos( $content, $term );
            } else {
                $position = stripos( $content, $term );
            }

            if ( false !== $position ) {
                break;
            }
        }

        if ( false === $position ) {
            return wp_trim_words( $content, 34, '...' );
        }

        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $content ) : strlen( $content );
        $start = max( 0, (int) $position - 120 );
        $snippet_length = 260;
        $snippet = function_exists( 'mb_substr' )
            ? mb_substr( $content, $start, $snippet_length )
            : substr( $content, $start, $snippet_length );

        $prefix = $start > 0 ? '...' : '';
        $suffix = ( $start + $snippet_length ) < $length ? '...' : '';
        $snippet = esc_html( $prefix . trim( $snippet ) . $suffix );

        foreach ( $terms as $term ) {
            $term = preg_quote( esc_html( $term ), '/' );
            if ( '' === $term ) {
                continue;
            }

            $snippet = preg_replace( '/(' . $term . ')/iu', '<mark>$1</mark>', $snippet );
        }

        return is_string( $snippet ) ? $snippet : '';
    }

    private static function format_article_lists( int $post_id ): array {
        $terms = get_the_terms( $post_id, self::TAX_LIST );
        if ( ! is_array( $terms ) || is_wp_error( $terms ) ) {
            return [];
        }

        return array_map( function( $term ) {
            return [
                'id'       => (int) $term->term_id,
                'name'     => (string) $term->name,
                'slug'     => (string) $term->slug,
                'view_url' => self::get_list_url( $term ),
            ];
        }, $terms );
    }

    public static function find_saved_article_id( int $page_id, string $language = '' ): int {
        if ( ! $page_id ) {
            return 0;
        }

        $language = self::normalize_language( $language );
        if ( is_wp_error( $language ) ) {
            return 0;
        }

        $query = new \WP_Query( [
            'post_type'              => self::POST_TYPE,
            'post_status'            => [ 'publish', 'draft', 'private' ],
            'fields'                 => 'ids',
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [
                'relation' => 'AND',
                [
                    'key'   => self::META_PAGE_ID,
                    'value' => $page_id,
                ],
                [
                    'key'   => self::META_LANGUAGE,
                    'value' => $language,
                ],
            ],
        ] );

        return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
    }

    public static function get_saved_article_from_route( $id = 0, string $slug = '' ) {
        $id = absint( $id );
        if ( $id ) {
            $post = get_post( $id );
            return $post instanceof \WP_Post && $post->post_type === self::POST_TYPE ? $post : null;
        }

        $slug = sanitize_title( $slug );
        if ( '' === $slug ) {
            return null;
        }

        $posts = get_posts( [
            'name'           => $slug,
            'post_type'      => self::POST_TYPE,
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'posts_per_page' => 1,
        ] );

        return $posts ? $posts[0] : null;
    }

    public static function get_article_language_links( int $page_id, string $language = '' ) {
        if ( ! $page_id ) {
            return [];
        }

        $language = self::normalize_language( $language );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $metadata = self::fetch_article_metadata( $language, $page_id );
        if ( is_wp_error( $metadata ) ) {
            return $metadata;
        }

        return $metadata['available_languages'];
    }

    private static function request_wikipedia( string $language, array $args, int $cache_ttl = 0, bool $force_refresh = false ) {
        $language = self::normalize_language( $language );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $args['format'] = 'json';
        $args['origin'] = '*';
        ksort( $args );

        $cache_key = $cache_ttl > 0 ? self::wordopedia_cache_key( $language, $args ) : '';
        if ( $cache_key && ! $force_refresh && function_exists( 'get_transient' ) ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $url = add_query_arg( $args, 'https://' . $language . '.wikipedia.org/w/api.php' );

        $response = wp_remote_get( $url, [
            'timeout'     => 20,
            'redirection' => 3,
            'user-agent'  => self::is_wordpress_playground() ? '' : self::wordopedia_user_agent(),
            'headers'     => self::wordopedia_request_headers(),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return self::wordopedia_http_error( $status_code, is_array( $data ) ? $data : [], $response );
        }

        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'wordopedia_bad_response', __( 'Wikipedia returned an unreadable response.', 'wordopedia' ) );
        }

        if ( isset( $data['error']['info'] ) ) {
            return new \WP_Error( 'wordopedia_api_error', sanitize_text_field( $data['error']['info'] ) );
        }

        if ( $cache_key && function_exists( 'set_transient' ) ) {
            set_transient( $cache_key, $data, $cache_ttl );
        }

        return $data;
    }

    private static function wordopedia_request_headers(): array {
        $headers = [
            'Accept' => 'application/json',
        ];

        return apply_filters( 'wordopedia_app_wikipedia_request_headers', $headers );
    }

    private static function wordopedia_user_agent(): string {
        return 'Wordopedia/1.0 (' . home_url( '/' ) . '; uses Wikipedia API)';
    }

    private static function is_wordpress_playground(): bool {
        return defined( 'PLAYGROUND_AUTO_LOGIN_AS_USER' );
    }

    private static function wordopedia_cache_key( string $language, array $args ): string {
        return 'wordopedia_app_api_' . md5( $language . ':' . wp_json_encode( $args ) );
    }

    private static function wordopedia_http_error( int $status_code, array $data, $response ) {
        if ( isset( $data['error']['info'] ) ) {
            return new \WP_Error( 'wordopedia_api_error', sanitize_text_field( $data['error']['info'] ) );
        }

        $retry_after = self::wordopedia_retry_after( $response );
        if ( $retry_after && in_array( $status_code, [ 429, 503 ], true ) ) {
            return new \WP_Error(
                'wordopedia_rate_limited',
                sprintf(
                    /* translators: %s: Retry-After header value. */
                    __( 'Wikipedia asked this app to slow down. Try again after %s.', 'wordopedia' ),
                    $retry_after
                )
            );
        }

        return new \WP_Error(
            'wordopedia_http_error',
            sprintf(
                /* translators: %d: HTTP response status code. */
                __( 'Wikipedia returned HTTP %d.', 'wordopedia' ),
                $status_code
            )
        );
    }

    private static function wordopedia_retry_after( $response ): string {
        if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
            return '';
        }

        $retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
        return is_scalar( $retry_after ) ? sanitize_text_field( (string) $retry_after ) : '';
    }

    public static function normalize_language( string $language = '' ) {
        $language = strtolower( trim( $language ) );
        if ( '' === $language ) {
            $language = self::get_default_language();
        }

        if ( ! preg_match( '/^[a-z][a-z0-9-]{1,15}$/', $language ) ) {
            return new \WP_Error( 'wordopedia_invalid_language', __( 'Use a valid Wikipedia language subdomain, such as en, de, fr, or simple.', 'wordopedia' ) );
        }

        return $language;
    }

    public static function get_default_language(): string {
        if ( function_exists( 'get_current_user_id' ) && function_exists( 'get_user_meta' ) ) {
            $stored = get_user_meta( get_current_user_id(), self::USER_META_LANGUAGES, true );
            $languages = is_array( $stored ) ? self::normalize_language_list( $stored ) : [];
            if ( $languages ) {
                return $languages[0];
            }
        }

        return 'en';
    }

    public static function get_locale_default_language(): string {
        $locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
        return self::language_from_locale( $locale ) ?: 'en';
    }

    public static function language_from_locale( string $locale ): string {
        $locale = strtolower( str_replace( '-', '_', trim( $locale ) ) );
        $map = [
            'nb_no'   => 'no',
            'nn_no'   => 'nn',
            'pt_br'   => 'pt',
            'zh_cn'   => 'zh',
            'zh_hans' => 'zh',
            'zh_hant' => 'zh',
            'zh_hk'   => 'zh',
            'zh_sg'   => 'zh',
            'zh_tw'   => 'zh',
        ];

        if ( isset( $map[ $locale ] ) ) {
            return $map[ $locale ];
        }

        $language = strtok( $locale, '_' );
        return is_string( $language ) && preg_match( '/^[a-z][a-z0-9-]{1,15}$/', $language ) ? $language : 'en';
    }

    public static function get_supported_languages(): array {
        $fallback = [ 'en' => __( 'English', 'wordopedia' ) ];
        if ( ! function_exists( 'wp_remote_get' ) ) {
            return apply_filters( 'wordopedia_app_languages', $fallback );
        }

        $cache_key = 'wordopedia_app_language_versions';
        $languages = function_exists( 'get_transient' ) ? get_transient( $cache_key ) : false;
        if ( is_array( $languages ) && $languages ) {
            return apply_filters( 'wordopedia_app_languages', $languages );
        }

        $data = self::request_wikipedia( 'en', [
            'action'        => 'sitematrix',
            'smtype'        => 'language|special',
            'smlangprop'    => 'code|name|localname|site',
            'smsiteprop'    => 'url|dbname|code|sitename',
            'formatversion' => 2,
            'utf8'          => 1,
        ] );

        if ( is_wp_error( $data ) ) {
            return apply_filters( 'wordopedia_app_languages', $fallback );
        }

        $languages = [];
        foreach ( $data['sitematrix'] ?? [] as $key => $language ) {
            if ( ! is_array( $language ) ) {
                continue;
            }

            if ( 'specials' === $key ) {
                foreach ( $language as $special_site ) {
                    if ( ! is_array( $special_site ) || 'simple' !== ( $special_site['code'] ?? '' ) || ! self::is_open_wikipedia_site( [ $special_site ], 'simple' ) ) {
                        continue;
                    }

                    $languages['simple'] = __( 'Simple English', 'wordopedia' );
                }
                continue;
            }

            if ( isset( $language['site'] ) && is_array( $language['site'] ) ) {
                $code = sanitize_text_field( $language['code'] ?? '' );
                if ( ! self::is_open_wikipedia_site( $language['site'], $code ) ) {
                    continue;
                }

                $local_name = isset( $language['localname'] ) ? sanitize_text_field( $language['localname'] ) : '';
                $name = isset( $language['name'] ) ? sanitize_text_field( $language['name'] ) : '';
                $label = $local_name ?: ( $name ?: strtoupper( $code ) );
                if ( $name && $local_name && $name !== $local_name ) {
                    $label .= ' - ' . $name;
                }

                if ( preg_match( '/^[a-z][a-z0-9-]{1,15}$/', $code ) ) {
                    $languages[ $code ] = $label;
                }
                continue;
            }

        }

        ksort( $languages, SORT_NATURAL | SORT_FLAG_CASE );
        $languages = $languages ?: $fallback;
        if ( function_exists( 'set_transient' ) ) {
            set_transient( $cache_key, $languages, self::WORDOPEDIA_CACHE_LANGUAGE );
        }

        return apply_filters( 'wordopedia_app_languages', $languages );
    }

    public static function normalize_language_list( array $languages ): array {
        $normalized = [];

        foreach ( $languages as $language ) {
            if ( ! is_scalar( $language ) ) {
                continue;
            }

            $language = strtolower( trim( (string) $language ) );
            if ( '' === $language ) {
                continue;
            }

            $language = self::normalize_language( $language );
            if ( is_wp_error( $language ) || in_array( $language, $normalized, true ) ) {
                continue;
            }

            $normalized[] = $language;
            if ( count( $normalized ) >= 8 ) {
                break;
            }
        }

        return $normalized;
    }

    public static function get_user_languages( int $user_id = 0 ): array {
        $user_id = $user_id ?: get_current_user_id();
        $stored = get_user_meta( $user_id, self::USER_META_LANGUAGES, true );
        $languages = is_array( $stored ) ? self::normalize_language_list( $stored ) : [];

        if ( ! $languages ) {
            $languages = [ 'en' ];
        }

        return $languages;
    }

    public static function get_language_label( string $language ): string {
        $languages = self::get_supported_languages();
        return $languages[ $language ] ?? strtoupper( $language );
    }

    private static function is_open_wikipedia_site( array $sites, string $language ): bool {
        $language = strtolower( trim( $language ) );
        if ( ! preg_match( '/^[a-z][a-z0-9-]{1,15}$/', $language ) ) {
            return false;
        }

        foreach ( $sites as $site ) {
            if ( ! is_array( $site ) ) {
                continue;
            }

            if ( isset( $site['closed'] ) || isset( $site['private'] ) || isset( $site['fishbowl'] ) ) {
                continue;
            }

            $url = isset( $site['url'] ) ? (string) $site['url'] : '';
            $dbname = isset( $site['dbname'] ) ? (string) $site['dbname'] : '';
            $is_wikipedia_project = 'wiki' === ( $site['code'] ?? '' ) || $dbname === $language . 'wiki';
            if ( $is_wikipedia_project && preg_match( '#^https://' . preg_quote( $language, '#' ) . '\.wikipedia\.org/?$#', $url ) ) {
                return true;
            }
        }

        return false;
    }

    public static function build_article_slug( string $language, string $title, int $page_id ): string {
        $slug = trim( $language . '-' . $title );
        if ( '' === trim( $title ) && $page_id ) {
            $slug .= '-' . absint( $page_id );
        }

        return sanitize_title( $slug );
    }

    private static function wikipedia_page_url( string $language, string $title = '', int $page_id = 0 ): string {
        if ( $title ) {
            return esc_url_raw( 'https://' . $language . '.wikipedia.org/wiki/' . rawurlencode( str_replace( ' ', '_', $title ) ) );
        }

        return esc_url_raw( 'https://' . $language . '.wikipedia.org/?curid=' . absint( $page_id ) );
    }

    private static function sanitize_article_html( string $html, string $language ): string {
        $html = self::remove_article_resource_nodes( $html );
        $html = self::rewrite_article_links( $html, $language );
        return wp_kses( $html, self::article_allowed_html() );
    }

    private static function remove_article_resource_nodes( string $html ): string {
        if ( ! class_exists( '\DOMDocument' ) ) {
            $html = preg_replace( '~<style\b[^>]*>.*?</style>~is', '', $html );
            $html = preg_replace( '~<link\b[^>]*>~is', '', is_string( $html ) ? $html : '' );
            return is_string( $html ) ? $html : '';
        }

        $previous = libxml_use_internal_errors( true );
        $document = new \DOMDocument();
        $flags = 0;
        if ( defined( 'LIBXML_HTML_NOIMPLIED' ) ) {
            $flags |= LIBXML_HTML_NOIMPLIED;
        }
        if ( defined( 'LIBXML_HTML_NODEFDTD' ) ) {
            $flags |= LIBXML_HTML_NODEFDTD;
        }

        $loaded = $document->loadHTML( '<?xml encoding="utf-8" ?><div id="wordopedia-app-article-root">' . $html . '</div>', $flags );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return $html;
        }

        foreach ( [ 'style', 'link' ] as $tag_name ) {
            $nodes = [];
            foreach ( $document->getElementsByTagName( $tag_name ) as $node ) {
                $nodes[] = $node;
            }

            foreach ( $nodes as $node ) {
                if ( $node->parentNode ) {
                    $node->parentNode->removeChild( $node );
                }
            }
        }

        $root = $document->getElementById( 'wordopedia-app-article-root' );
        if ( ! $root ) {
            return $html;
        }

        $cleaned = '';
        foreach ( $root->childNodes as $child ) {
            $cleaned .= $document->saveHTML( $child );
        }

        return $cleaned;
    }

    private static function rewrite_article_links( string $html, string $current_language ): string {
        if ( ! class_exists( '\DOMDocument' ) ) {
            return $html;
        }

        $previous = libxml_use_internal_errors( true );
        $document = new \DOMDocument();
        $flags = 0;
        if ( defined( 'LIBXML_HTML_NOIMPLIED' ) ) {
            $flags |= LIBXML_HTML_NOIMPLIED;
        }
        if ( defined( 'LIBXML_HTML_NODEFDTD' ) ) {
            $flags |= LIBXML_HTML_NODEFDTD;
        }

        $loaded = $document->loadHTML( '<?xml encoding="utf-8" ?><div id="wordopedia-app-article-root">' . $html . '</div>', $flags );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return $html;
        }

        $links = $document->getElementsByTagName( 'a' );
        foreach ( $links as $link ) {
            $href = $link->getAttribute( 'href' );
            $app_url = self::app_url_from_wikipedia_href( $href, $current_language );
            if ( $app_url ) {
                $link->setAttribute( 'href', $app_url );
                $link->removeAttribute( 'target' );
                $link->removeAttribute( 'rel' );
            } elseif ( preg_match( '~^https?://~i', $href ) ) {
                $link->setAttribute( 'target', '_blank' );
                $link->setAttribute( 'rel', 'noreferrer' );
            }
        }

        $root = $document->getElementById( 'wordopedia-app-article-root' );
        if ( ! $root ) {
            return $html;
        }

        $rewritten = '';
        foreach ( $root->childNodes as $child ) {
            $rewritten .= $document->saveHTML( $child );
        }

        return $rewritten;
    }

    private static function app_url_from_wikipedia_href( string $href, string $current_language ): string {
        $href = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
        if ( '' === $href || '#' === $href[0] ) {
            return '';
        }

        $parts = wp_parse_url( $href );
        if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
            return '';
        }

        $language = $current_language;
        if ( ! empty( $parts['host'] ) ) {
            if ( ! preg_match( '/^([a-z0-9-]+)\.wikipedia\.org$/i', $parts['host'], $matches ) ) {
                return '';
            }
            $language = strtolower( $matches[1] );
        }

        if ( strpos( $parts['path'], '/wiki/' ) !== 0 ) {
            return '';
        }

        $title = rawurldecode( substr( $parts['path'], 6 ) );
        $title = str_replace( '_', ' ', $title );
        if ( '' === $title || self::is_non_article_title( $title ) ) {
            return '';
        }

        $url = self::get_article_url( $language, $title );
        if ( ! empty( $parts['fragment'] ) ) {
            $url .= '#' . rawurlencode( rawurldecode( $parts['fragment'] ) );
        }

        return $url;
    }

    private static function is_non_article_title( string $title ): bool {
        $namespace = strtolower( strtok( $title, ':' ) );
        return in_array( $namespace, [ 'file', 'image', 'category', 'special', 'help', 'template', 'talk', 'user', 'wikipedia', 'portal', 'module', 'mediawiki' ], true );
    }

    private static function format_language_links( array $links, string $current_language ): array {
        $languages = [];

        foreach ( $links as $link ) {
            if ( ! is_array( $link ) || empty( $link['lang'] ) || empty( $link['title'] ) ) {
                continue;
            }

            $language = sanitize_text_field( $link['lang'] );
            if ( $language === $current_language ) {
                continue;
            }

            $title = wp_strip_all_tags( $link['title'] );
            $languages[] = [
                'language'       => $language,
                'language_label' => isset( $link['langname'] ) ? sanitize_text_field( $link['langname'] ) : self::get_language_label( $language ),
                'autonym'        => isset( $link['autonym'] ) ? sanitize_text_field( $link['autonym'] ) : '',
                'title'          => $title,
                'url'            => ! empty( $link['url'] ) ? esc_url_raw( $link['url'] ) : self::wikipedia_page_url( $language, $title ),
                'app_url'        => self::get_article_url( $language, $title ),
            ];
        }

        usort( $languages, function( $a, $b ) {
            return strcasecmp( $a['language_label'], $b['language_label'] );
        } );

        return $languages;
    }

    private static function plain_text( string $value ): string {
        $charset = get_option( 'blog_charset' ) ?: 'UTF-8';
        return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, $charset ) );
    }

    public static function article_allowed_html(): array {
        $global = [
            'class'       => true,
            'id'          => true,
            'title'       => true,
            'lang'        => true,
            'dir'         => true,
            'role'        => true,
            'aria-label'  => true,
            'aria-hidden' => true,
        ];

        return [
            'a'          => array_merge( $global, [ 'href' => true, 'target' => true, 'rel' => true ] ),
            'abbr'       => $global,
            'b'          => $global,
            'blockquote' => $global,
            'br'         => [],
            'caption'    => $global,
            'cite'       => $global,
            'code'       => $global,
            'dd'         => $global,
            'del'        => $global,
            'details'    => $global,
            'dfn'        => $global,
            'div'        => $global,
            'dl'         => $global,
            'dt'         => $global,
            'em'         => $global,
            'figcaption' => $global,
            'figure'     => $global,
            'h1'         => $global,
            'h2'         => $global,
            'h3'         => $global,
            'h4'         => $global,
            'h5'         => $global,
            'h6'         => $global,
            'hr'         => $global,
            'i'          => $global,
            'img'        => array_merge( $global, [ 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'srcset' => true, 'sizes' => true, 'loading' => true ] ),
            'li'         => $global,
            'mark'       => $global,
            'math'       => $global,
            'mi'         => $global,
            'mn'         => $global,
            'mo'         => $global,
            'mrow'       => $global,
            'msub'       => $global,
            'msup'       => $global,
            'ol'         => $global,
            'p'          => $global,
            'pre'        => $global,
            'q'          => $global,
            's'          => $global,
            'small'      => $global,
            'span'       => $global,
            'strong'     => $global,
            'sub'        => $global,
            'summary'    => $global,
            'sup'        => $global,
            'table'      => $global,
            'tbody'      => $global,
            'td'         => array_merge( $global, [ 'colspan' => true, 'rowspan' => true ] ),
            'tfoot'      => $global,
            'th'         => array_merge( $global, [ 'colspan' => true, 'rowspan' => true, 'scope' => true ] ),
            'thead'      => $global,
            'time'       => array_merge( $global, [ 'datetime' => true ] ),
            'tr'         => $global,
            'u'          => $global,
            'ul'         => $global,
        ];
    }

    private static function article_lookup_input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'page_id'  => [
                    'type'        => 'integer',
                    'description' => 'Wikipedia page ID from wordopedia/search-wikipedia.',
                ],
                'title'    => [
                    'type'        => 'string',
                    'description' => 'Exact Wikipedia article title. Used when page_id is missing.',
                ],
                'language' => [
                    'type'        => 'string',
                    'description' => 'Wikipedia language subdomain. Defaults to the current user locale when omitted.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function article_media_input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'page_id'         => [
                    'type'        => 'integer',
                    'description' => 'Wikipedia page ID from wordopedia/search-wikipedia.',
                ],
                'title'           => [
                    'type'        => 'string',
                    'description' => 'Exact Wikipedia article title. Used when page_id is missing.',
                ],
                'language'        => [
                    'type'        => 'string',
                    'description' => 'Wikipedia language subdomain. Defaults to the current user locale when omitted.',
                ],
                'mime'            => [
                    'type'        => 'string',
                    'description' => 'Optional MIME type filter. Use image/svg+xml to list SVG files from the article.',
                ],
                'limit'           => [
                    'type'        => 'integer',
                    'description' => 'Maximum number of media files, from 1 to 50.',
                ],
                'thumbnail_width' => [
                    'type'        => 'integer',
                    'description' => 'Requested preview thumbnail width in pixels, from 64 to 2000. Defaults to 512.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function media_file_lookup_input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'file_title'      => [
                    'type'        => 'string',
                    'description' => 'Wikimedia file title or file page URL, such as File:Example.svg, from wordopedia/list-article-media.',
                ],
                'language'        => [
                    'type'        => 'string',
                    'description' => 'Wikipedia language subdomain to resolve local and Commons files. Defaults to the current user locale when omitted.',
                ],
                'thumbnail_width' => [
                    'type'        => 'integer',
                    'description' => 'Requested preview thumbnail width in pixels, from 64 to 2000. Defaults to 512.',
                ],
            ],
            'required'             => [ 'file_title' ],
            'additionalProperties' => false,
        ];
    }

    private static function article_search_output_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'query'          => [ 'type' => 'string' ],
                'language'       => [ 'type' => 'string' ],
                'language_label' => [ 'type' => 'string' ],
                'articles'       => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'page_id'        => [ 'type' => 'integer', 'description' => 'Use with wordopedia/get-article or wordopedia/save-article.' ],
                            'title'          => [ 'type' => 'string' ],
                            'snippet'        => [ 'type' => 'string' ],
                            'word_count'     => [ 'type' => 'integer' ],
                            'size'           => [ 'type' => 'integer' ],
                            'timestamp'      => [ 'type' => 'string' ],
                            'language'       => [ 'type' => 'string' ],
                            'language_label' => [ 'type' => 'string' ],
                            'source_url'     => [ 'type' => 'string' ],
                            'app_url'        => [ 'type' => 'string' ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function article_media_output_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'article'          => [
                    'type'       => 'object',
                    'properties' => [
                        'page_id'        => [ 'type' => 'integer' ],
                        'title'          => [ 'type' => 'string' ],
                        'language'       => [ 'type' => 'string' ],
                        'language_label' => [ 'type' => 'string' ],
                        'source_url'     => [ 'type' => 'string' ],
                        'app_url'        => [ 'type' => 'string' ],
                    ],
                ],
                'mime_filter'      => [ 'type' => 'string' ],
                'count'            => [ 'type' => 'integer' ],
                'total_candidates' => [ 'type' => 'integer' ],
                'more_available'   => [ 'type' => 'boolean' ],
                'media'            => [
                    'type'  => 'array',
                    'items' => self::media_file_schema(),
                ],
            ],
        ];
    }

    private static function media_file_output_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'file' => self::media_file_schema(),
            ],
        ];
    }

    private static function media_file_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id'         => [ 'type' => 'integer' ],
                'title'           => [ 'type' => 'string', 'description' => 'Canonical MediaWiki file title. Use with wordopedia/get-media-file.' ],
                'filename'        => [ 'type' => 'string' ],
                'canonical_title' => [ 'type' => 'string' ],
                'mime'            => [ 'type' => 'string' ],
                'media_type'      => [ 'type' => 'string' ],
                'repository'      => [ 'type' => 'string' ],
                'original_url'    => [ 'type' => 'string', 'description' => 'Direct original media URL, such as the SVG file URL for image/svg+xml.' ],
                'thumbnail_url'   => [ 'type' => 'string', 'description' => 'Rendered preview thumbnail URL when Wikimedia provides one.' ],
                'description_url' => [ 'type' => 'string', 'description' => 'Wikimedia file description page with licensing and reuse details.' ],
                'width'           => [ 'type' => 'integer' ],
                'height'          => [ 'type' => 'integer' ],
                'size'            => [ 'type' => 'integer' ],
                'sha1'            => [ 'type' => 'string' ],
                'description'     => [ 'type' => 'string' ],
                'license'         => [ 'type' => 'string' ],
                'license_url'     => [ 'type' => 'string' ],
                'usage_terms'     => [ 'type' => 'string' ],
                'artist'          => [ 'type' => 'string' ],
                'credit'          => [ 'type' => 'string' ],
                'attribution'     => [ 'type' => 'string' ],
                'source'          => [ 'type' => 'string' ],
                'metadata'        => [
                    'type'                 => 'object',
                    'description'          => 'Plain-text Wikimedia extmetadata keyed by original metadata names.',
                    'additionalProperties' => [ 'type' => 'string' ],
                ],
            ],
        ];
    }

    private static function article_detail_output_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'article' => [
                    'type'       => 'object',
                    'properties' => [
                        'page_id'             => [ 'type' => 'integer', 'description' => 'Use with wordopedia/save-article.' ],
                        'title'               => [ 'type' => 'string' ],
                        'extract'             => [ 'type' => 'string' ],
                        'summary'             => [ 'type' => 'string' ],
                        'html'                => [ 'type' => 'string' ],
                        'language'            => [ 'type' => 'string' ],
                        'language_label'      => [ 'type' => 'string' ],
                        'available_languages' => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'language'       => [ 'type' => 'string' ],
                                    'language_label' => [ 'type' => 'string' ],
                                    'autonym'        => [ 'type' => 'string' ],
                                    'title'          => [ 'type' => 'string' ],
                                    'url'            => [ 'type' => 'string' ],
                                    'app_url'        => [ 'type' => 'string' ],
                                ],
                            ],
                        ],
                        'source_url'       => [ 'type' => 'string' ],
                        'thumbnail_url'    => [ 'type' => 'string' ],
                        'last_revision_id' => [ 'type' => 'string' ],
                        'remote_touched'   => [ 'type' => 'string' ],
                        'app_url'          => [ 'type' => 'string' ],
                        'saved_article'    => self::saved_article_schema(),
                    ],
                ],
            ],
        ];
    }

    private static function saved_article_output_schema( bool $include_content = false ): array {
        return [
            'type'       => 'object',
            'properties' => [
                'article' => self::saved_article_schema( $include_content ),
            ],
        ];
    }

    private static function saved_article_schema( bool $include_content = false ): array {
        $properties = [
            'post_id'              => [ 'type' => 'integer', 'description' => 'Use with wordopedia/get-saved-article or wordopedia/refetch-saved-article.' ],
            'id'                   => [ 'type' => 'integer' ],
            'title'                => [ 'type' => 'string' ],
            'status'               => [ 'type' => 'string' ],
            'summary'              => [ 'type' => 'string' ],
            'page_id'              => [ 'type' => 'integer' ],
            'language'             => [ 'type' => 'string' ],
            'language_label'       => [ 'type' => 'string' ],
            'source_url'           => [ 'type' => 'string' ],
            'thumbnail_url'        => [ 'type' => 'string' ],
            'last_revision_id'     => [ 'type' => 'string' ],
            'remote_touched'       => [ 'type' => 'string' ],
            'saved_at'             => [ 'type' => 'string' ],
            'saved_at_display'     => [ 'type' => 'string' ],
            'refetched_at'         => [ 'type' => 'string' ],
            'refetched_at_display' => [ 'type' => 'string' ],
            'last_saved_at'        => [ 'type' => 'string' ],
            'last_saved_at_display' => [ 'type' => 'string' ],
            'view_url'             => [ 'type' => 'string' ],
            'live_app_url'         => [ 'type' => 'string' ],
            'app_url'              => [ 'type' => 'string' ],
            'edit_url'             => [ 'type' => 'string' ],
            'lists'                => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'       => [ 'type' => 'integer' ],
                        'name'     => [ 'type' => 'string' ],
                        'slug'     => [ 'type' => 'string' ],
                        'view_url' => [ 'type' => 'string' ],
                    ],
                ],
            ],
            'created'              => [ 'type' => 'boolean' ],
            'updated'              => [ 'type' => 'boolean' ],
        ];

        if ( $include_content ) {
            $properties['html'] = [ 'type' => 'string' ];
            $properties['snippets'] = [
                'type'  => 'array',
                'items' => self::snippet_schema( true ),
            ];
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }

    public function activate(): void {
        $this->register_post_types();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }
}
