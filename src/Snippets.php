<?php

namespace Akirk\Wordopedia;

trait Snippets {
    public function handle_save_snippet(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to save Wikipedia snippets.', 'wordopedia' ) );
        }

        check_admin_referer( self::NONCE_SAVE_SNIPPET );

        $result = self::save_wordopedia_snippet( self::snippet_save_input_from_request() );
        $referer = wp_get_referer() ?: self::get_app_url();

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'wordopedia_error', rawurlencode( $result->get_error_message() ), $referer ) );
            exit;
        }

        wp_safe_redirect( self::snippet_redirect_url( $result, 'snippet_saved', $referer ) );
        exit;
    }

    public function handle_update_snippet(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to update Wikipedia snippets.', 'wordopedia' ) );
        }

        $snippet_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        check_admin_referer( self::NONCE_UPDATE_SNIPPET . '_' . $snippet_id );

        $result = self::update_wordopedia_snippet( $snippet_id, self::snippet_update_input_from_request() );
        $referer = wp_get_referer() ?: self::get_app_url();

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'wordopedia_error', rawurlencode( $result->get_error_message() ), $referer ) );
            exit;
        }

        wp_safe_redirect( self::snippet_redirect_url( $result, 'snippet_updated', $referer ) );
        exit;
    }

    public function handle_delete_snippet(): void {
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to delete Wikipedia snippets.', 'wordopedia' ) );
        }

        $snippet_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        check_admin_referer( self::NONCE_DELETE_SNIPPET . '_' . $snippet_id );

        $result = self::delete_wordopedia_snippet( $snippet_id );
        $referer = wp_get_referer() ?: self::get_app_url();

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'wordopedia_error', rawurlencode( $result->get_error_message() ), $referer ) );
            exit;
        }

        $url = ! empty( $result['parent_view_url'] ) ? $result['parent_view_url'] : $referer;
        wp_safe_redirect( add_query_arg( 'snippet_deleted', 1, $url ) );
        exit;
    }

    public function ajax_save_snippet(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [
                'message' => __( 'You are not allowed to save Wikipedia snippets.', 'wordopedia' ),
            ], 403 );
        }

        check_ajax_referer( self::NONCE_SAVE_SNIPPET );

        $result = self::save_wordopedia_snippet( self::snippet_save_input_from_request() );
        self::send_snippet_json_response( $result );
    }

    public function ajax_update_snippet(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [
                'message' => __( 'You are not allowed to update Wikipedia snippets.', 'wordopedia' ),
            ], 403 );
        }

        $snippet_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        check_ajax_referer( self::NONCE_UPDATE_SNIPPET . '_' . $snippet_id );

        $result = self::update_wordopedia_snippet( $snippet_id, self::snippet_update_input_from_request() );
        self::send_snippet_json_response( $result );
    }

    public function ajax_delete_snippet(): void {
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_send_json_error( [
                'message' => __( 'You are not allowed to delete Wikipedia snippets.', 'wordopedia' ),
            ], 403 );
        }

        $snippet_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        check_ajax_referer( self::NONCE_DELETE_SNIPPET . '_' . $snippet_id );

        $result = self::delete_wordopedia_snippet( $snippet_id );
        self::send_snippet_json_response( $result );
    }

    public function ability_save_snippet( $input ) {
        $input = is_array( $input ) ? $input : [];
        $snippet = self::save_wordopedia_snippet( $input );

        if ( is_wp_error( $snippet ) ) {
            return $snippet;
        }

        return [
            'snippet' => self::format_ability_snippet( $snippet ),
        ];
    }

    public function ability_get_snippet( $input ) {
        $input = is_array( $input ) ? $input : [];
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $snippet = self::get_wordopedia_snippet( $post_id );

        if ( is_wp_error( $snippet ) ) {
            return $snippet;
        }

        return [
            'snippet' => self::format_ability_snippet( $snippet ),
        ];
    }

    public function ability_search_snippets( $input ) {
        $input = is_array( $input ) ? $input : [];
        $search = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';
        $parent_post_id = isset( $input['parent_post_id'] ) ? absint( $input['parent_post_id'] ) : 0;
        $language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : '';
        $limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : 20;

        $snippets = self::search_wordopedia_snippets( $search, $limit, $parent_post_id, $language );
        if ( is_wp_error( $snippets ) ) {
            return $snippets;
        }

        return [
            'snippets' => array_map( function( $snippet ) {
                return is_array( $snippet ) ? self::format_ability_snippet( $snippet ) : $snippet;
            }, $snippets ),
        ];
    }

    public static function format_ability_snippet( array $snippet ): array {
        if ( ! array_key_exists( 'html', $snippet ) && array_key_exists( 'content', $snippet ) ) {
            $snippet['html'] = self::snippet_display_html( (string) $snippet['content'] );
        }

        unset( $snippet['content'] );

        return $snippet;
    }

    public static function save_wordopedia_snippet( array $input ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'wordopedia_cannot_save_snippet', __( 'You are not allowed to save Wikipedia snippets.', 'wordopedia' ) );
        }

        $snippet_id = isset( $input['snippet_id'] ) ? absint( $input['snippet_id'] ) : 0;
        if ( ! $snippet_id && isset( $input['post_id'] ) && ! self::is_saved_article_post_id( absint( $input['post_id'] ) ) ) {
            $snippet_id = absint( $input['post_id'] );
        }

        if ( $snippet_id ) {
            return self::update_wordopedia_snippet( $snippet_id, $input );
        }

        $snippet_content = self::snippet_content_from_input( $input );
        $text = $snippet_content['text'];
        if ( '' === $text ) {
            return new \WP_Error( 'wordopedia_empty_snippet', __( 'Select or provide snippet text.', 'wordopedia' ) );
        }

        $parent_post_id = self::resolve_snippet_parent_post_id( $input );
        if ( is_wp_error( $parent_post_id ) ) {
            return $parent_post_id;
        }

        $parent = get_post( $parent_post_id );
        if ( ! $parent instanceof \WP_Post ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
        }

        $snippet_title = self::input_text_value( $input, [ 'snippet_title' ] );
        $snippet_title = '' !== trim( $snippet_title ) ? sanitize_text_field( $snippet_title ) : self::build_snippet_title( $parent, $text );

        $post_id = wp_insert_post( [
            'post_type'    => self::POST_TYPE_SNIPPET,
            'post_title'   => $snippet_title,
            'post_content' => $snippet_content['content'],
            'post_excerpt' => self::snippet_excerpt( $text, 32 ),
            'post_status'  => 'publish',
            'post_parent'  => $parent_post_id,
            'post_author'  => get_current_user_id(),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        self::update_snippet_meta( (int) $post_id, $parent_post_id, $text, true );

        return self::format_snippet( get_post( $post_id ), true, [
            'created' => true,
            'updated' => false,
        ] );
    }

    public static function update_wordopedia_snippet( int $snippet_id, array $input ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'wordopedia_cannot_update_snippet', __( 'You are not allowed to update Wikipedia snippets.', 'wordopedia' ) );
        }

        $post = get_post( $snippet_id );
        if ( ! $post instanceof \WP_Post || self::POST_TYPE_SNIPPET !== $post->post_type ) {
            return new \WP_Error( 'wordopedia_snippet_not_found', __( 'Wikipedia snippet not found.', 'wordopedia' ) );
        }

        if ( ! current_user_can( 'edit_post', $snippet_id ) ) {
            return new \WP_Error( 'wordopedia_cannot_update_snippet', __( 'You are not allowed to update this Wikipedia snippet.', 'wordopedia' ) );
        }

        $snippet_content = self::snippet_content_from_input( $input );
        $text = $snippet_content['text'];
        if ( '' === $text ) {
            return new \WP_Error( 'wordopedia_empty_snippet', __( 'Snippet text cannot be empty.', 'wordopedia' ) );
        }

        $parent = get_post( (int) $post->post_parent );
        if ( ! $parent instanceof \WP_Post || self::POST_TYPE !== $parent->post_type ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
        }

        $snippet_title = self::input_text_value( $input, [ 'snippet_title' ] );
        $snippet_title = '' !== trim( $snippet_title ) ? sanitize_text_field( $snippet_title ) : self::build_snippet_title( $parent, $text );

        $updated = wp_update_post( [
            'ID'           => $snippet_id,
            'post_title'   => $snippet_title,
            'post_content' => $snippet_content['content'],
            'post_excerpt' => self::snippet_excerpt( $text, 32 ),
            'post_status'  => 'publish',
        ], true );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        self::update_snippet_meta( $snippet_id, (int) $parent->ID, $text, false );

        return self::format_snippet( get_post( $snippet_id ), true, [
            'created' => false,
            'updated' => true,
        ] );
    }

    public static function delete_wordopedia_snippet( int $snippet_id ) {
        if ( ! current_user_can( 'delete_posts' ) ) {
            return new \WP_Error( 'wordopedia_cannot_delete_snippet', __( 'You are not allowed to delete Wikipedia snippets.', 'wordopedia' ) );
        }

        $post = get_post( $snippet_id );
        if ( ! $post instanceof \WP_Post || self::POST_TYPE_SNIPPET !== $post->post_type ) {
            return new \WP_Error( 'wordopedia_snippet_not_found', __( 'Wikipedia snippet not found.', 'wordopedia' ) );
        }

        if ( ! current_user_can( 'delete_post', $snippet_id ) ) {
            return new \WP_Error( 'wordopedia_cannot_delete_snippet', __( 'You are not allowed to delete this Wikipedia snippet.', 'wordopedia' ) );
        }

        $snippet = self::format_snippet( $post, true, [
            'deleted' => true,
        ] );
        $deleted = wp_trash_post( $snippet_id );

        if ( ! $deleted ) {
            return new \WP_Error( 'wordopedia_snippet_delete_failed', __( 'Snippet could not be deleted.', 'wordopedia' ) );
        }

        return $snippet;
    }

    public static function get_wordopedia_snippet( int $snippet_id ) {
        $post = get_post( $snippet_id );
        if ( ! $post instanceof \WP_Post || self::POST_TYPE_SNIPPET !== $post->post_type ) {
            return new \WP_Error( 'wordopedia_snippet_not_found', __( 'Wikipedia snippet not found.', 'wordopedia' ) );
        }

        return self::format_snippet( $post, true );
    }

    public static function get_saved_article_snippets( int $parent_post_id, bool $include_content = true, int $limit = 50 ): array {
        if ( ! $parent_post_id || ! self::is_saved_article_post_id( $parent_post_id ) ) {
            return [];
        }

        $posts = get_posts( [
            'post_type'      => self::POST_TYPE_SNIPPET,
            'post_parent'    => $parent_post_id,
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'posts_per_page' => max( 1, min( 100, absint( $limit ) ) ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        return array_map( function( $post ) use ( $include_content ) {
            return self::format_snippet( $post, $include_content );
        }, $posts );
    }

    public static function search_wordopedia_snippets( string $search = '', int $limit = 20, int $parent_post_id = 0, string $language = '' ) {
        $limit = max( 1, min( 50, absint( $limit ) ) );
        $args = [
            'post_type'      => self::POST_TYPE_SNIPPET,
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $search = trim( $search );
        if ( '' !== $search ) {
            $args['s'] = $search;
        }

        if ( $parent_post_id ) {
            if ( ! self::is_saved_article_post_id( $parent_post_id ) ) {
                return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
            }
            $args['post_parent'] = $parent_post_id;
        }

        $language = trim( $language );
        if ( '' !== $language ) {
            $language = self::normalize_language( $language );
            if ( is_wp_error( $language ) ) {
                return $language;
            }

            $args['meta_query'] = [
                [
                    'key'   => self::META_LANGUAGE,
                    'value' => $language,
                ],
            ];
        }

        $posts = get_posts( $args );

        return array_map( function( $post ) {
            return self::format_snippet( $post, true );
        }, $posts );
    }

    public static function get_saved_snippet_languages(): array {
        $posts = get_posts( [
            'post_type'              => self::POST_TYPE_SNIPPET,
            'post_status'            => [ 'publish', 'draft', 'private' ],
            'posts_per_page'         => 500,
            'fields'                 => 'ids',
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ] );

        $languages = [];
        foreach ( $posts as $post_id ) {
            $language = strtolower( trim( (string) get_post_meta( (int) $post_id, self::META_LANGUAGE, true ) ) );
            if ( '' === $language ) {
                continue;
            }

            $language = self::normalize_language( $language );
            if ( is_wp_error( $language ) ) {
                continue;
            }

            $languages[ $language ] = self::get_language_label( $language );
        }

        asort( $languages, SORT_NATURAL | SORT_FLAG_CASE );

        return $languages;
    }

    public static function format_snippet( $post, bool $include_content = true, array $extra = [] ): array {
        if ( ! $post instanceof \WP_Post || self::POST_TYPE_SNIPPET !== $post->post_type ) {
            return [];
        }

        $post_id = (int) $post->ID;
        $parent_post_id = (int) $post->post_parent;
        $parent = $parent_post_id ? get_post( $parent_post_id ) : null;
        $parent_title = $parent instanceof \WP_Post ? get_the_title( $parent ) : '';
        $parent_view_url = $parent instanceof \WP_Post ? self::get_saved_article_view_url( $parent ) : '';
        $created_at = (string) get_post_meta( $post_id, self::META_SNIPPET_CREATED_AT, true );
        $updated_at = (string) get_post_meta( $post_id, self::META_SNIPPET_UPDATED_AT, true );
        $text = self::snippet_plain_text_from_content( $post->post_content );

        $snippet = [
            'post_id'              => $post_id,
            'id'                   => $post_id,
            'parent_post_id'       => $parent_post_id,
            'saved_article_post_id' => $parent_post_id,
            'saved_article_title'  => $parent_title,
            'title'                => get_the_title( $post ),
            'status'               => get_post_status( $post ),
            'summary'              => self::snippet_excerpt( $text, 32 ),
            'page_id'              => absint( get_post_meta( $post_id, self::META_PAGE_ID, true ) ),
            'language'             => (string) get_post_meta( $post_id, self::META_LANGUAGE, true ),
            'language_label'       => self::get_language_label( (string) get_post_meta( $post_id, self::META_LANGUAGE, true ) ),
            'source_url'           => (string) get_post_meta( $post_id, self::META_SOURCE_URL, true ),
            'thumbnail_url'        => (string) get_post_meta( $post_id, self::META_THUMBNAIL_URL, true ),
            'created_at'           => $created_at ?: $post->post_date,
            'created_at_display'   => self::format_datetime( $created_at ?: $post->post_date ),
            'updated_at'           => $updated_at ?: $post->post_modified,
            'updated_at_display'   => self::format_datetime( $updated_at ?: $post->post_modified ),
            'view_url'             => $parent_view_url ? $parent_view_url . '#wiki-snippet-' . $post_id : '',
            'parent_view_url'      => $parent_view_url,
            'edit_url'             => get_edit_post_link( $post_id, '' ) ?: '',
            'created'              => false,
            'updated'              => false,
            'deleted'              => false,
        ];

        if ( $include_content ) {
            $snippet['content'] = $post->post_content;
            $snippet['html'] = self::snippet_display_html( $post->post_content );
            $snippet['text'] = $text;
            $snippet['original_text'] = (string) get_post_meta( $post_id, self::META_SNIPPET_ORIGINAL_TEXT, true );
        }

        return array_merge( $snippet, $extra );
    }

    private static function resolve_snippet_parent_post_id( array $input ) {
        foreach ( [ 'parent_post_id', 'saved_article_post_id', 'article_post_id', 'post_parent', 'post_id' ] as $key ) {
            $parent_post_id = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
            if ( $parent_post_id ) {
                return self::validate_snippet_parent_post_id( $parent_post_id );
            }
        }

        $page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
        $language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : self::get_default_language();

        if ( $page_id ) {
            $normalized_language = self::normalize_language( $language );
            if ( ! is_wp_error( $normalized_language ) ) {
                $existing_id = self::find_saved_article_id( $page_id, $normalized_language );
                if ( $existing_id ) {
                    return self::validate_snippet_parent_post_id( $existing_id );
                }
            }
        }

        $saved = self::save_wordopedia_article( $input );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        $parent_post_id = isset( $saved['post_id'] ) ? absint( $saved['post_id'] ) : 0;
        if ( ! $parent_post_id ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
        }

        return self::validate_snippet_parent_post_id( $parent_post_id );
    }

    private static function validate_snippet_parent_post_id( int $parent_post_id ) {
        if ( ! self::is_saved_article_post_id( $parent_post_id ) ) {
            return new \WP_Error( 'wordopedia_article_not_found', __( 'Saved Wikipedia article not found.', 'wordopedia' ) );
        }

        if ( ! current_user_can( 'edit_post', $parent_post_id ) ) {
            return new \WP_Error( 'wordopedia_cannot_save_snippet', __( 'You are not allowed to save snippets for this article.', 'wordopedia' ) );
        }

        return $parent_post_id;
    }

    private static function is_saved_article_post_id( int $post_id ): bool {
        $post = $post_id ? get_post( $post_id ) : null;
        return $post instanceof \WP_Post && self::POST_TYPE === $post->post_type;
    }

    private static function update_snippet_meta( int $post_id, int $parent_post_id, string $text, bool $created ): void {
        $parent = get_post( $parent_post_id );
        if ( ! $parent instanceof \WP_Post ) {
            return;
        }

        update_post_meta( $post_id, self::META_PAGE_ID, absint( get_post_meta( $parent_post_id, self::META_PAGE_ID, true ) ) );
        update_post_meta( $post_id, self::META_LANGUAGE, sanitize_text_field( (string) get_post_meta( $parent_post_id, self::META_LANGUAGE, true ) ) );
        update_post_meta( $post_id, self::META_SOURCE_URL, esc_url_raw( (string) get_post_meta( $parent_post_id, self::META_SOURCE_URL, true ) ) );
        update_post_meta( $post_id, self::META_THUMBNAIL_URL, esc_url_raw( (string) get_post_meta( $parent_post_id, self::META_THUMBNAIL_URL, true ) ) );
        update_post_meta( $post_id, self::META_LAST_REVISION, sanitize_text_field( (string) get_post_meta( $parent_post_id, self::META_LAST_REVISION, true ) ) );
        update_post_meta( $post_id, self::META_REMOTE_TOUCHED, sanitize_text_field( (string) get_post_meta( $parent_post_id, self::META_REMOTE_TOUCHED, true ) ) );

        if ( $created ) {
            update_post_meta( $post_id, self::META_SNIPPET_ORIGINAL_TEXT, $text );
            update_post_meta( $post_id, self::META_SNIPPET_CREATED_AT, current_time( 'mysql' ) );
        }

        update_post_meta( $post_id, self::META_SNIPPET_UPDATED_AT, current_time( 'mysql' ) );
    }

    private static function get_saved_article_view_url( \WP_Post $post ): string {
        return self::get_app_url( 'saved/' . ( $post->post_name ?: (int) $post->ID ) );
    }

    private static function snippet_redirect_url( array $snippet, string $flag, string $fallback ): string {
        $url = ! empty( $snippet['parent_view_url'] ) ? $snippet['parent_view_url'] : ( $snippet['view_url'] ?? $fallback );
        $url = add_query_arg( $flag, 1, $url ?: $fallback );

        if ( ! empty( $snippet['post_id'] ) ) {
            $url .= '#wiki-snippet-' . absint( $snippet['post_id'] );
        }

        return $url;
    }

    private static function snippet_save_input_from_request(): array {
        // The nonce is verified by the callers (handle_save_snippet() and
        // ajax_save_snippet()) before this reads the request. The raw 'text' and
        // 'html' values are deliberately unsanitized here: snippet_content_from_input()
        // rebuilds them from a DOM allow list of inline tags, escaping every text node
        // and attribute, and display runs through wp_kses_post().
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return [
            'parent_post_id' => isset( $_POST['parent_post_id'] ) ? absint( wp_unslash( $_POST['parent_post_id'] ) ) : 0,
            'page_id'        => isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0,
            'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
            'language'       => isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : self::get_default_language(),
            'snippet_title'  => isset( $_POST['snippet_title'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_title'] ) ) : '',
            'text'           => isset( $_POST['text'] ) && is_scalar( $_POST['text'] ) ? (string) wp_unslash( $_POST['text'] ) : '',
            'html'           => isset( $_POST['html'] ) && is_scalar( $_POST['html'] ) ? (string) wp_unslash( $_POST['html'] ) : '',
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    }

    private static function snippet_update_input_from_request(): array {
        // Nonce verified by the callers; see snippet_save_input_from_request() for why
        // 'text' and 'html' are read raw.
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return [
            'snippet_title' => isset( $_POST['snippet_title'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_title'] ) ) : '',
            'text'          => isset( $_POST['text'] ) && is_scalar( $_POST['text'] ) ? (string) wp_unslash( $_POST['text'] ) : '',
            'html'          => isset( $_POST['html'] ) && is_scalar( $_POST['html'] ) ? (string) wp_unslash( $_POST['html'] ) : '',
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    }

    private static function send_snippet_json_response( $result ): void {
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ], 400 );
        }

        if ( ! is_array( $result ) ) {
            wp_send_json_error( [
                'message' => __( 'Snippet could not be saved.', 'wordopedia' ),
            ], 400 );
        }

        if ( ! empty( $result['post_id'] ) ) {
            $post_id = absint( $result['post_id'] );
            $result['update_nonce'] = wp_create_nonce( self::NONCE_UPDATE_SNIPPET . '_' . $post_id );
            $result['delete_nonce'] = wp_create_nonce( self::NONCE_DELETE_SNIPPET . '_' . $post_id );
        }

        wp_send_json_success( [
            'snippet' => $result,
        ] );
    }

    private static function input_text_value( array $input, array $keys ): string {
        foreach ( $keys as $key ) {
            if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) {
                return (string) $input[ $key ];
            }
        }

        return '';
    }

    private static function snippet_content_from_input( array $input ): array {
        $text = self::normalize_snippet_text( self::input_text_value( $input, [ 'text', 'content' ] ) );
        $html = self::input_text_value( $input, [ 'html' ] );
        $content = self::snippet_content_for_storage( $text, $html );
        $stored_text = self::snippet_plain_text_from_content( $content );

        if ( '' === $stored_text && '' !== $text ) {
            $content = self::snippet_content_for_storage( $text );
            $stored_text = self::snippet_plain_text_from_content( $content );
        }

        return [
            'content' => $content,
            'text'    => $stored_text,
        ];
    }

    private static function normalize_snippet_text( string $text ): string {
        $charset = function_exists( 'get_option' ) ? ( get_option( 'blog_charset' ) ?: 'UTF-8' ) : 'UTF-8';
        $text = html_entity_decode( $text, ENT_QUOTES, $charset );
        $text = preg_replace( '~<\s*br\s*/?>~i', "\n", $text );
        $text = preg_replace( '~</p\s*>~i', "\n\n", is_string( $text ) ? $text : '' );
        $text = preg_replace( '~<!--.*?-->~s', '', is_string( $text ) ? $text : '' );
        $text = wp_strip_all_tags( is_string( $text ) ? $text : '' );
        $text = str_replace( "\xc2\xa0", ' ', $text );
        $text = str_replace( [ "\r\n", "\r" ], "\n", $text );
        $text = preg_replace( "/[ \t]+/", ' ', $text );
        $lines = explode( "\n", is_string( $text ) ? $text : '' );
        $lines = array_map( 'trim', $lines );
        $text = implode( "\n", $lines );
        $text = preg_replace( "/\n{3,}/", "\n\n", $text );

        return trim( is_string( $text ) ? $text : '' );
    }

    private static function build_snippet_title( \WP_Post $parent, string $text ): string {
        $article_title = get_the_title( $parent );
        $title = $article_title
            ? sprintf(
                /* translators: %s: saved article title */
                __( 'Snippet from %s', 'wordopedia' ),
                $article_title
            )
            : __( 'Wikipedia snippet', 'wordopedia' );

        return sanitize_text_field( $title );
    }

    private static function snippet_content_for_storage( string $text, string $html = '' ): string {
        $html = trim( $html );
        if ( '' !== $html ) {
            $content = self::snippet_html_content_for_storage( $html );
            if ( '' !== $content ) {
                return $content;
            }
        }

        $text = self::normalize_snippet_text( $text );
        if ( '' === $text ) {
            return '';
        }

        $paragraphs = preg_split( "/\n{2,}/", $text );
        $paragraphs = is_array( $paragraphs ) ? $paragraphs : [ $text ];
        $blocks = [];

        foreach ( $paragraphs as $paragraph ) {
            $lines = explode( "\n", trim( $paragraph ) );
            $lines = array_map( function( $line ) {
                return self::snippet_escape_html( $line );
            }, $lines );
            $html = '<p>' . implode( '<br>', $lines ) . '</p>';
            $blocks[] = "<!-- wp:paragraph -->\n" . $html . "\n<!-- /wp:paragraph -->";
        }

        return implode( "\n\n", $blocks );
    }

    private static function snippet_html_content_for_storage( string $html ): string {
        if ( '' === trim( $html ) || ! class_exists( '\DOMDocument' ) ) {
            return '';
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

        $loaded = $document->loadHTML( '<?xml encoding="utf-8" ?><div id="wordopedia-app-snippet-root">' . $html . '</div>', $flags );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return '';
        }

        $root = $document->getElementById( 'wordopedia-app-snippet-root' );
        if ( ! $root ) {
            return '';
        }

        $paragraphs = [];
        self::collect_snippet_html_paragraphs( $root, $paragraphs );

        $blocks = [];
        foreach ( $paragraphs as $paragraph ) {
            $paragraph = self::trim_snippet_inline_html( $paragraph );
            if ( '' === self::normalize_snippet_text( $paragraph ) ) {
                continue;
            }

            $blocks[] = "<!-- wp:paragraph -->\n<p>" . $paragraph . "</p>\n<!-- /wp:paragraph -->";
        }

        return implode( "\n\n", $blocks );
    }

    private static function collect_snippet_html_paragraphs( \DOMNode $node, array &$paragraphs ): void {
        $buffer = '';

        foreach ( $node->childNodes as $child ) {
            if ( XML_TEXT_NODE === $child->nodeType ) {
                $buffer .= self::snippet_text_node_html( (string) $child->nodeValue );
                continue;
            }

            if ( XML_ELEMENT_NODE !== $child->nodeType ) {
                continue;
            }

            $tag_name = strtolower( $child->nodeName );
            if ( isset( self::snippet_block_tags()[ $tag_name ] ) ) {
                self::append_snippet_html_paragraph( $paragraphs, $buffer );
                $buffer = '';

                if ( self::snippet_contains_block_child( $child ) ) {
                    self::collect_snippet_html_paragraphs( $child, $paragraphs );
                } else {
                    self::append_snippet_html_paragraph( $paragraphs, self::snippet_inline_children_html( $child ) );
                }
                continue;
            }

            $buffer .= self::snippet_inline_node_html( $child );
        }

        self::append_snippet_html_paragraph( $paragraphs, $buffer );
    }

    private static function append_snippet_html_paragraph( array &$paragraphs, string $html ): void {
        $html = self::trim_snippet_inline_html( $html );
        if ( '' === self::normalize_snippet_text( $html ) ) {
            return;
        }

        $paragraphs[] = $html;
    }

    private static function snippet_contains_block_child( \DOMNode $node ): bool {
        foreach ( $node->childNodes as $child ) {
            if ( XML_ELEMENT_NODE !== $child->nodeType ) {
                continue;
            }

            if ( isset( self::snippet_block_tags()[ strtolower( $child->nodeName ) ] ) ) {
                return true;
            }
        }

        return false;
    }

    private static function snippet_inline_node_html( \DOMNode $node ): string {
        if ( XML_TEXT_NODE === $node->nodeType ) {
            return self::snippet_text_node_html( (string) $node->nodeValue );
        }

        if ( XML_ELEMENT_NODE !== $node->nodeType ) {
            return '';
        }

        $tag_name = strtolower( $node->nodeName );
        if ( isset( self::snippet_dropped_tags()[ $tag_name ] ) ) {
            return '';
        }

        if ( 'br' === $tag_name ) {
            return '<br>';
        }

        $inner = self::snippet_inline_children_html( $node );
        if ( '' === self::normalize_snippet_text( $inner ) ) {
            return '';
        }

        if ( 'a' === $tag_name ) {
            $href = $node instanceof \DOMElement ? self::snippet_sanitize_href( $node->getAttribute( 'href' ) ) : '';
            if ( '' === $href ) {
                return $inner;
            }

            $attributes = ' href="' . self::snippet_escape_attr( $href ) . '"';
            if ( $node instanceof \DOMElement && '_blank' === $node->getAttribute( 'target' ) ) {
                $attributes .= ' target="_blank" rel="noreferrer"';
            }

            return '<a' . $attributes . '>' . $inner . '</a>';
        }

        $allowed_tags = self::snippet_inline_tags();
        if ( ! isset( $allowed_tags[ $tag_name ] ) ) {
            return $inner;
        }

        return '<' . $allowed_tags[ $tag_name ] . '>' . $inner . '</' . $allowed_tags[ $tag_name ] . '>';
    }

    private static function snippet_inline_children_html( \DOMNode $node ): string {
        $html = '';
        foreach ( $node->childNodes as $child ) {
            $html .= self::snippet_inline_node_html( $child );
        }

        return $html;
    }

    private static function snippet_text_node_html( string $text ): string {
        $text = str_replace( "\xc2\xa0", ' ', $text );
        $text = preg_replace( '/\s+/u', ' ', $text );

        return self::snippet_escape_html( is_string( $text ) ? $text : '' );
    }

    private static function trim_snippet_inline_html( string $html ): string {
        $html = preg_replace( '~^(?:\s|&nbsp;|<br\s*/?>)+~i', '', $html );
        $html = preg_replace( '~(?:\s|&nbsp;|<br\s*/?>)+$~i', '', is_string( $html ) ? $html : '' );

        return trim( is_string( $html ) ? $html : '' );
    }

    private static function snippet_sanitize_href( string $href ): string {
        $href = trim( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ) );
        $protocol_check = preg_replace( '/[\x00-\x20]+/', '', $href );
        $protocol_check = is_string( $protocol_check ) ? $protocol_check : $href;

        if ( '' === $href || preg_match( '~^(?:javascript|data|vbscript):~i', $protocol_check ) ) {
            return '';
        }

        if ( preg_match( '~^[a-z][a-z0-9+.-]*:~i', $protocol_check ) && ! preg_match( '~^(?:https?|mailto):~i', $protocol_check ) ) {
            return '';
        }

        return function_exists( 'esc_url_raw' ) ? esc_url_raw( $href ) : $href;
    }

    private static function snippet_block_tags(): array {
        return [
            'address'    => true,
            'article'    => true,
            'aside'      => true,
            'blockquote' => true,
            'dd'         => true,
            'div'        => true,
            'dl'         => true,
            'dt'         => true,
            'figcaption' => true,
            'figure'     => true,
            'footer'     => true,
            'h1'         => true,
            'h2'         => true,
            'h3'         => true,
            'h4'         => true,
            'h5'         => true,
            'h6'         => true,
            'header'     => true,
            'li'         => true,
            'main'       => true,
            'ol'         => true,
            'p'          => true,
            'pre'        => true,
            'section'    => true,
            'table'      => true,
            'tbody'      => true,
            'td'         => true,
            'tfoot'      => true,
            'th'         => true,
            'thead'      => true,
            'tr'         => true,
            'ul'         => true,
        ];
    }

    private static function snippet_inline_tags(): array {
        return [
            'b'      => 'strong',
            'code'   => 'code',
            'del'    => 'del',
            'em'     => 'em',
            'i'      => 'em',
            'ins'    => 'ins',
            'mark'   => 'mark',
            's'      => 's',
            'small'  => 'small',
            'strong' => 'strong',
            'sub'    => 'sub',
            'sup'    => 'sup',
        ];
    }

    private static function snippet_dropped_tags(): array {
        return [
            'button'   => true,
            'embed'    => true,
            'form'     => true,
            'iframe'   => true,
            'input'    => true,
            'math'     => true,
            'noscript' => true,
            'object'   => true,
            'option'   => true,
            'script'   => true,
            'select'   => true,
            'style'    => true,
            'svg'      => true,
            'template' => true,
            'textarea' => true,
        ];
    }

    private static function snippet_plain_text_from_content( string $content ): string {
        if ( function_exists( 'strip_blocks' ) ) {
            $content = strip_blocks( $content );
        }

        return self::normalize_snippet_text( $content );
    }

    private static function snippet_display_html( string $content ): string {
        if ( function_exists( 'do_blocks' ) ) {
            $content = do_blocks( $content );
        }

        if ( function_exists( 'wp_kses_post' ) ) {
            return wp_kses_post( $content );
        }

        return strip_tags( $content, '<p><br><strong><em><b><i><a><code><pre><blockquote><ul><ol><li>' );
    }

    private static function snippet_escape_html( string $text ): string {
        if ( function_exists( 'esc_html' ) ) {
            return esc_html( $text );
        }

        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }

    private static function snippet_escape_attr( string $text ): string {
        if ( function_exists( 'esc_attr' ) ) {
            return esc_attr( $text );
        }

        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }

    private static function snippet_excerpt( string $text, int $words = 24 ): string {
        $text = self::normalize_snippet_text( $text );
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( is_string( $text ) ? $text : '' );
        if ( '' === $text ) {
            return '';
        }

        if ( function_exists( 'wp_trim_words' ) ) {
            return wp_trim_words( $text, $words, '...' );
        }

        $parts = preg_split( '/\s+/', $text );
        $parts = is_array( $parts ) ? $parts : [];
        if ( count( $parts ) <= $words ) {
            return $text;
        }

        return implode( ' ', array_slice( $parts, 0, $words ) ) . '...';
    }

    public static function snippet_save_input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'snippet_id'      => [
                    'type'        => 'integer',
                    'description' => 'Existing Wikipedia snippet post ID to update. Omit to create a new snippet.',
                ],
                'parent_post_id'  => [
                    'type'        => 'integer',
                    'description' => 'Saved Wikipedia article post ID to attach the snippet to.',
                ],
                'text'            => [
                    'type'        => 'string',
                    'description' => 'Snippet text to save. This can be selected article text or an edited condensation.',
                ],
                'html'            => [
                    'type'        => 'string',
                    'description' => 'Optional selected snippet HTML. Safe inline markup such as links is preserved when the snippet is stored.',
                ],
                'snippet_title'   => [
                    'type'        => 'string',
                    'description' => 'Optional WordPress title for the snippet post.',
                ],
                'page_id'         => [
                    'type'        => 'integer',
                    'description' => 'Wikipedia page ID. Used to save the parent article first when parent_post_id is omitted.',
                ],
                'title'           => [
                    'type'        => 'string',
                    'description' => 'Exact Wikipedia article title. Used when parent_post_id is omitted.',
                ],
                'language'        => [
                    'type'        => 'string',
                    'description' => 'Wikipedia language subdomain for the parent article.',
                ],
            ],
            'required'             => [ 'text' ],
            'additionalProperties' => false,
        ];
    }

    public static function snippet_output_schema( bool $include_content = true ): array {
        return [
            'type'       => 'object',
            'properties' => [
                'snippet' => self::snippet_schema( $include_content ),
            ],
        ];
    }

    public static function snippet_search_output_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'snippets' => [
                    'type'  => 'array',
                    'items' => self::snippet_schema( true ),
                ],
            ],
        ];
    }

    public static function snippet_schema( bool $include_content = true ): array {
        $properties = [
            'post_id'               => [ 'type' => 'integer', 'description' => 'Use with wordopedia/get-snippet or wordopedia/save-snippet.' ],
            'id'                    => [ 'type' => 'integer' ],
            'parent_post_id'        => [ 'type' => 'integer', 'description' => 'Saved article parent post ID.' ],
            'saved_article_post_id' => [ 'type' => 'integer' ],
            'saved_article_title'   => [ 'type' => 'string' ],
            'title'                 => [ 'type' => 'string' ],
            'status'                => [ 'type' => 'string' ],
            'summary'               => [ 'type' => 'string' ],
            'page_id'               => [ 'type' => 'integer' ],
            'language'              => [ 'type' => 'string' ],
            'language_label'        => [ 'type' => 'string' ],
            'source_url'            => [ 'type' => 'string' ],
            'thumbnail_url'         => [ 'type' => 'string' ],
            'created_at'            => [ 'type' => 'string' ],
            'updated_at'            => [ 'type' => 'string' ],
            'view_url'              => [ 'type' => 'string' ],
            'parent_view_url'       => [ 'type' => 'string' ],
            'edit_url'              => [ 'type' => 'string' ],
            'created'               => [ 'type' => 'boolean' ],
            'updated'               => [ 'type' => 'boolean' ],
            'deleted'               => [ 'type' => 'boolean' ],
        ];

        if ( $include_content ) {
            $properties['html'] = [ 'type' => 'string' ];
            $properties['text'] = [ 'type' => 'string' ];
            $properties['original_text'] = [ 'type' => 'string' ];
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }
}
