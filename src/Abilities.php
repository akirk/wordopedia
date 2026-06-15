<?php

namespace Akirk\Wordopedia;

class Abilities {
    private App $app;

    public function __construct( App $app ) {
        $this->app = $app;
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
                    'language' => [
                        'type'        => 'string',
                        'description' => 'Wikipedia language subdomain for slug lookup, such as de in de-wien.',
                    ],
                    'slug'    => [
                        'type'        => 'string',
                        'description' => 'Saved article slug. Can be the full saved slug, such as de-wien, or the article slug with language supplied separately, such as wien.',
                    ],
                ],
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
            'input_schema'        => App::snippet_save_input_schema(),
            'output_schema'       => App::snippet_output_schema( true ),
            'execute_callback'    => [ $this->app, 'ability_save_snippet' ],
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
            'output_schema'       => App::snippet_output_schema( true ),
            'execute_callback'    => [ $this->app, 'ability_get_snippet' ],
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
            'output_schema'       => App::snippet_search_output_schema(),
            'execute_callback'    => [ $this->app, 'ability_search_snippets' ],
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
        $language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : App::get_default_language();
        $limit    = isset( $input['limit'] ) ? absint( $input['limit'] ) : 10;

        $articles = App::search_wordopedia_articles( $query, $language, $limit );
        if ( is_wp_error( $articles ) ) {
            return $articles;
        }

        $language = App::normalize_language( $language );

        return [
            'query'          => $query,
            'language'       => $language,
            'language_label' => is_wp_error( $language ) ? '' : App::get_language_label( $language ),
            'articles'       => $articles,
        ];
    }

    public function ability_get_article( $input ) {
        $input = is_array( $input ) ? $input : [];
        $article = App::fetch_wordopedia_article( $input );

        if ( is_wp_error( $article ) ) {
            return $article;
        }

        return self::format_ability_article( $article );
    }

    public function ability_save_article( $input ) {
        $input = is_array( $input ) ? $input : [];
        $saved = App::save_wordopedia_article( $input );

        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return $saved;
    }

    public function ability_list_article_media( $input ) {
        $input = is_array( $input ) ? $input : [];
        $media = App::fetch_wordopedia_article_media( $input );

        if ( is_wp_error( $media ) ) {
            return $media;
        }

        return $media;
    }

    public function ability_get_media_file( $input ) {
        $input = is_array( $input ) ? $input : [];
        $file = App::fetch_wordopedia_media_file( $input );

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
            'articles' => App::list_saved_articles( $search, $limit, $language, $list ),
        ];
    }

    public function ability_get_saved_article( $input ) {
        $input = is_array( $input ) ? $input : [];
        $post = self::resolve_saved_article_lookup( $input );

        if ( ! $post || $post->post_type !== App::POST_TYPE ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
        }

        return self::format_ability_article( App::format_saved_article( $post, true ) );
    }

    public static function resolve_saved_article_lookup( array $input ) {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        if ( $post_id ) {
            return get_post( $post_id );
        }

        $slug = isset( $input['slug'] ) && is_scalar( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '';
        if ( '' === $slug ) {
            return null;
        }

        $language = isset( $input['language'] ) && is_scalar( $input['language'] ) ? sanitize_text_field( (string) $input['language'] ) : '';
        if ( '' !== $language ) {
            $language = App::normalize_language( $language );
            if ( is_wp_error( $language ) ) {
                return null;
            }
        }

        $candidate_slugs = [ $slug ];
        if ( '' !== $language && 0 !== strpos( $slug, $language . '-' ) ) {
            array_unshift( $candidate_slugs, sanitize_title( $language . '-' . $slug ) );
        }

        foreach ( array_unique( $candidate_slugs ) as $candidate_slug ) {
            $posts = get_posts( [
                'name'           => $candidate_slug,
                'post_type'      => App::POST_TYPE,
                'post_status'    => [ 'publish', 'draft', 'private' ],
                'posts_per_page' => 1,
            ] );

            if ( $posts ) {
                return $posts[0];
            }
        }

        return null;
    }

    public function ability_refetch_saved_article( $input ) {
        $input = is_array( $input ) ? $input : [];
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $saved = App::refetch_saved_article( $post_id );

        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return $saved;
    }

    public static function format_ability_article( array $article ): array {
        $html = '';
        if ( array_key_exists( 'html', $article ) && is_scalar( $article['html'] ) ) {
            $html = (string) $article['html'];
        } elseif ( array_key_exists( 'content', $article ) && is_scalar( $article['content'] ) ) {
            $html = (string) $article['content'];
        }

        $tocdata = isset( $article['tocdata'] ) && is_array( $article['tocdata'] ) ? $article['tocdata'] : [];
        $sectioned = self::section_article_html( $html, $tocdata );
        $article['outline']  = $sectioned['outline'];
        $article['sections'] = $sectioned['sections'];

        unset( $article['html'] );
        unset( $article['content'] );
        unset( $article['tocdata'] );

        if ( isset( $article['snippets'] ) && is_array( $article['snippets'] ) ) {
            foreach ( $article['snippets'] as $index => $snippet ) {
                if ( is_array( $snippet ) ) {
                    $article['snippets'][ $index ] = App::format_ability_snippet( $snippet );
                }
            }
        }

        return $article;
    }

    public static function section_article_html( string $html, array $tocdata = [] ): array {
        $fallback = [
            'outline'  => [
                'lead' => [
                    'title' => __( 'Lead', 'wordopedia' ),
                ],
            ],
            'sections' => [
                'lead' => [
                    'title'      => __( 'Lead', 'wordopedia' ),
                    'path'       => [ __( 'Lead', 'wordopedia' ) ],
                    'html'       => $html,
                    'word_count' => self::html_word_count( $html ),
                ],
            ],
        ];

        if ( '' === trim( $html ) || ! class_exists( '\DOMDocument' ) ) {
            return $fallback;
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

        $loaded = $document->loadHTML( '<?xml encoding="utf-8" ?><div id="wordopedia-app-section-root">' . $html . '</div>', $flags );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return $fallback;
        }

        $root = $document->getElementById( 'wordopedia-app-section-root' );
        if ( ! $root ) {
            return $fallback;
        }

        $container = self::article_section_container( $root );
        $lead_title = __( 'Lead', 'wordopedia' );
        $records = self::article_section_records_from_tocdata( $tocdata, $lead_title );
        if ( ! $records ) {
            return $fallback;
        }

        $anchor_map = self::article_section_anchor_map( $records );
        $current_key = 'lead';

        foreach ( $container->childNodes as $child ) {
            if ( self::is_article_toc_node( $child ) ) {
                continue;
            }

            $heading_level = self::article_heading_level( $child );
            if ( $heading_level ) {
                $base_key = self::article_heading_key( $child, trim( self::plain_text( $child->textContent ) ) );
                if ( isset( $anchor_map[ $base_key ] ) ) {
                    $current_key = $anchor_map[ $base_key ];
                }
                continue;
            }

            if ( isset( $records[ $current_key ] ) ) {
                $records[ $current_key ]['html'] .= $document->saveHTML( $child );
            }
        }

        foreach ( array_keys( $records ) as $key ) {
            $records[ $key ]['html'] = trim( $records[ $key ]['html'] );
        }

        if ( '' === $records['lead']['html'] && count( $records ) > 1 ) {
            unset( $records['lead'] );
        }

        $sections = [];
        foreach ( $records as $key => $record ) {
            $sections[ $key ] = [
                'title'      => $record['title'],
                'path'       => $record['path'],
                'html'       => $record['html'],
                'word_count' => self::html_word_count( $record['html'] ),
            ];
        }

        return [
            'outline'  => self::article_outline_from_records( $records ),
            'sections' => $sections,
        ];
    }

    private static function article_section_records_from_tocdata( array $tocdata, string $lead_title ): array {
        $toc_sections = isset( $tocdata['sections'] ) && is_array( $tocdata['sections'] ) ? $tocdata['sections'] : [];
        if ( ! $toc_sections ) {
            return [];
        }

        $records = [
            'lead' => [
                'key'    => 'lead',
                'title'  => $lead_title,
                'path'   => [ $lead_title ],
                'level'  => 1,
                'parent' => '',
                'html'   => '',
            ],
        ];
        $used_keys = [ 'lead' => true ];
        $stack = [];

        foreach ( $toc_sections as $toc_section ) {
            if ( ! is_array( $toc_section ) ) {
                continue;
            }

            $title = isset( $toc_section['line'] ) && is_scalar( $toc_section['line'] ) ? trim( self::plain_text( (string) $toc_section['line'] ) ) : '';
            if ( '' === $title ) {
                $title = isset( $toc_section['anchor'] ) && is_scalar( $toc_section['anchor'] ) ? str_replace( '_', ' ', (string) $toc_section['anchor'] ) : __( 'Untitled section', 'wordopedia' );
            }

            $base_key = self::article_tocdata_section_key( $toc_section, $title );
            $key = self::unique_article_section_key( $base_key, $used_keys );
            $level = isset( $toc_section['tocLevel'] ) ? absint( $toc_section['tocLevel'] ) : 0;
            if ( ! $level && isset( $toc_section['hLevel'] ) ) {
                $level = max( 1, absint( $toc_section['hLevel'] ) - 1 );
            }
            $level = max( 1, $level );

            foreach ( array_keys( $stack ) as $stack_level ) {
                if ( $stack_level >= $level ) {
                    unset( $stack[ $stack_level ] );
                }
            }

            $parent = null;
            if ( $stack ) {
                $parent_level = max( array_keys( $stack ) );
                $parent = $stack[ $parent_level ];
            }

            $path = $parent ? array_merge( $parent['path'], [ $title ] ) : [ $title ];
            $records[ $key ] = [
                'key'    => $key,
                'title'  => $title,
                'path'   => $path,
                'level'  => $level,
                'parent' => $parent['key'] ?? '',
                'html'   => '',
            ];

            $stack[ $level ] = [
                'key'  => $key,
                'path' => $path,
            ];
        }

        return count( $records ) > 1 ? $records : [];
    }

    private static function article_tocdata_section_key( array $toc_section, string $title ): string {
        foreach ( [ 'anchor', 'linkAnchor' ] as $field ) {
            if ( isset( $toc_section[ $field ] ) && is_scalar( $toc_section[ $field ] ) && '' !== trim( (string) $toc_section[ $field ] ) ) {
                $key = sanitize_title( rawurldecode( (string) $toc_section[ $field ] ) );
                if ( '' !== $key ) {
                    return $key;
                }
            }
        }

        $key = sanitize_title( $title );
        return '' !== $key ? $key : 'section';
    }

    private static function article_section_anchor_map( array $records ): array {
        $map = [];
        foreach ( $records as $key => $record ) {
            if ( empty( $record['key'] ) || 'lead' === $record['key'] ) {
                continue;
            }

            $map[ sanitize_title( (string) $record['key'] ) ] = (string) $record['key'];
        }

        return $map;
    }

    private static function article_section_container( \DOMElement $root ): \DOMElement {
        foreach ( $root->childNodes as $child ) {
            if ( $child instanceof \DOMElement && false !== strpos( ' ' . $child->getAttribute( 'class' ) . ' ', ' mw-parser-output ' ) ) {
                return $child;
            }
        }

        return $root;
    }

    private static function is_article_toc_node( \DOMNode $node ): bool {
        if ( ! $node instanceof \DOMElement ) {
            return false;
        }

        $id = strtolower( $node->getAttribute( 'id' ) );
        $class = strtolower( ' ' . $node->getAttribute( 'class' ) . ' ' );

        return 'toc' === $id || false !== strpos( $class, ' toc ' ) || false !== strpos( $class, ' vector-toc ' );
    }

    private static function article_heading_level( \DOMNode $node ): int {
        if ( ! $node instanceof \DOMElement ) {
            return 0;
        }

        if ( preg_match( '/^h([2-6])$/i', $node->tagName, $matches ) ) {
            return (int) $matches[1];
        }

        $class = ' ' . $node->getAttribute( 'class' ) . ' ';
        if ( false !== strpos( $class, ' mw-heading ' ) && preg_match( '/\bmw-heading([2-6])\b/', $class, $matches ) ) {
            return (int) $matches[1];
        }

        foreach ( [ 'h2', 'h3', 'h4', 'h5', 'h6' ] as $tag_name ) {
            if ( $node->getElementsByTagName( $tag_name )->length ) {
                return (int) substr( $tag_name, 1 );
            }
        }

        return 0;
    }

    private static function article_heading_key( \DOMElement $heading, string $title ): string {
        $id = trim( $heading->getAttribute( 'id' ) );
        if ( '' === $id ) {
            foreach ( [ 'h2', 'h3', 'h4', 'h5', 'h6' ] as $tag_name ) {
                foreach ( $heading->getElementsByTagName( $tag_name ) as $heading_child ) {
                    $id = trim( $heading_child->getAttribute( 'id' ) );
                    if ( '' !== $id ) {
                        break 2;
                    }
                }
            }
        }
        if ( '' === $id ) {
            foreach ( $heading->getElementsByTagName( 'span' ) as $span ) {
                $id = trim( $span->getAttribute( 'id' ) );
                if ( '' !== $id ) {
                    break;
                }
            }
        }

        $key = sanitize_title( rawurldecode( '' !== $id ? $id : $title ) );
        return '' !== $key ? $key : 'section';
    }

    private static function unique_article_section_key( string $base_key, array &$used_keys ): string {
        $key = $base_key;
        $suffix = 2;

        while ( isset( $used_keys[ $key ] ) ) {
            $key = $base_key . '-' . $suffix;
            ++$suffix;
        }

        $used_keys[ $key ] = true;
        return $key;
    }

    private static function article_outline_from_records( array $records ): array {
        $children = [];
        foreach ( $records as $key => $record ) {
            $parent = isset( $record['parent'] ) ? (string) $record['parent'] : '';
            $children[ $parent ][] = $key;
        }

        return self::article_outline_children( $records, $children, '' );
    }

    private static function article_outline_children( array $records, array $children, string $parent_key ): array {
        $outline = [];
        foreach ( $children[ $parent_key ] ?? [] as $key ) {
            if ( ! isset( $records[ $key ] ) ) {
                continue;
            }

            $entry = [
                'title' => $records[ $key ]['title'],
            ];
            $section_children = self::article_outline_children( $records, $children, $key );
            if ( $section_children ) {
                $entry['sections'] = $section_children;
            }

            $outline[ $key ] = $entry;
        }

        return $outline;
    }

    private static function html_word_count( string $html ): int {
        $text = self::plain_text( $html );
        if ( '' === $text ) {
            return 0;
        }

        if ( preg_match_all( '/[\p{L}\p{N}]+/u', $text, $matches ) ) {
            return count( $matches[0] );
        }

        return str_word_count( $text );
    }

    private static function plain_text( string $value ): string {
        $charset = get_option( 'blog_charset' ) ?: 'UTF-8';
        return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, $charset ) );
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


    public static function article_lookup_input_schema(): array {
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

    public static function article_media_input_schema(): array {
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

    public static function media_file_lookup_input_schema(): array {
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

    public static function article_search_output_schema(): array {
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

    public static function article_media_output_schema(): array {
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

    public static function media_file_output_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'file' => self::media_file_schema(),
            ],
        ];
    }

    public static function media_file_schema(): array {
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

    public static function article_detail_output_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id'             => [ 'type' => 'integer', 'description' => 'Use with wordopedia/save-article.' ],
                'title'               => [ 'type' => 'string' ],
                'extract'             => [ 'type' => 'string' ],
                'summary'             => [ 'type' => 'string' ],
                'outline'             => self::article_outline_schema(),
                'sections'            => self::article_sections_schema(),
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
        ];
    }

    public static function saved_article_output_schema( bool $include_content = false ): array {
        return self::saved_article_schema( $include_content );
    }

    public static function saved_article_schema( bool $include_content = false ): array {
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
            $properties['outline'] = self::article_outline_schema();
            $properties['sections'] = self::article_sections_schema();
            $properties['snippets'] = [
                'type'  => 'array',
                'items' => App::snippet_schema( true ),
            ];
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }

    public static function article_outline_schema(): array {
        return [
            'type'                 => 'object',
            'description'          => 'Nested article outline keyed by section ID. Child sections are nested under sections.',
            'additionalProperties' => [
                'type'       => 'object',
                'properties' => [
                    'title'    => [ 'type' => 'string' ],
                    'sections' => [
                        'type'                 => 'object',
                        'additionalProperties' => [ 'type' => 'object' ],
                    ],
                ],
            ],
        ];
    }

    public static function article_toc_items_from_tocdata( array $tocdata ): array {
        $toc_sections = isset( $tocdata['sections'] ) && is_array( $tocdata['sections'] ) ? $tocdata['sections'] : [];
        if ( ! $toc_sections ) {
            return [];
        }

        $items = [];
        $stack = [];

        foreach ( $toc_sections as $toc_section ) {
            if ( ! is_array( $toc_section ) ) {
                continue;
            }

            $title = isset( $toc_section['line'] ) && is_scalar( $toc_section['line'] ) ? trim( self::plain_text( (string) $toc_section['line'] ) ) : '';
            $anchor = isset( $toc_section['anchor'] ) && is_scalar( $toc_section['anchor'] ) ? trim( (string) $toc_section['anchor'] ) : '';
            if ( '' === $title || '' === $anchor ) {
                continue;
            }

            $level = isset( $toc_section['tocLevel'] ) ? absint( $toc_section['tocLevel'] ) : 0;
            if ( ! $level && isset( $toc_section['hLevel'] ) ) {
                $level = max( 1, absint( $toc_section['hLevel'] ) - 1 );
            }
            $level = max( 1, $level );

            $item = [
                'title'    => $title,
                'anchor'   => $anchor,
                'children' => [],
            ];

            foreach ( array_keys( $stack ) as $stack_level ) {
                if ( $stack_level >= $level ) {
                    unset( $stack[ $stack_level ] );
                }
            }

            if ( $stack ) {
                $parent_level = max( array_keys( $stack ) );
                $parent =& $stack[ $parent_level ];
                $parent['children'][] = $item;
                $last_index = count( $parent['children'] ) - 1;
                $stack[ $level ] =& $parent['children'][ $last_index ];
                unset( $parent );
            } else {
                $items[] = $item;
                $last_index = count( $items ) - 1;
                $stack[ $level ] =& $items[ $last_index ];
            }
        }

        return $items;
    }

    public static function article_sections_schema(): array {
        return [
            'type'                 => 'object',
            'description'          => 'Article section content keyed by the same section IDs used in outline.',
            'additionalProperties' => [
                'type'       => 'object',
                'properties' => [
                    'title'      => [ 'type' => 'string' ],
                    'path'       => [
                        'type'  => 'array',
                        'items' => [ 'type' => 'string' ],
                    ],
                    'html'       => [ 'type' => 'string' ],
                    'word_count' => [ 'type' => 'integer' ],
                ],
            ],
        ];
    }

}
