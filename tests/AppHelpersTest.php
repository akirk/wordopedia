<?php

use Akirk\Wordopedia\App;
use PHPUnit\Framework\TestCase;

class AppHelpersTest extends TestCase {
    protected function tearDown(): void {
        unset( $GLOBALS['wordopedia_app_test_user_locale'] );
        unset( $GLOBALS['wordopedia_app_test_home_url'] );
    }

    /** @dataProvider localeLanguages */
    public function test_language_from_locale( string $locale, string $expected ): void {
        $this->assertSame( $expected, App::language_from_locale( $locale ) );
    }

    public static function localeLanguages(): array {
        return [
            'english us'     => [ 'en_US', 'en' ],
            'german formal'  => [ 'de_DE_formal', 'de' ],
            'portuguese br'  => [ 'pt_BR', 'pt' ],
            'chinese taiwan' => [ 'zh_TW', 'zh' ],
            'nynorsk'        => [ 'nn_NO', 'nn' ],
            'invalid blank'  => [ '', 'en' ],
        ];
    }

    public function test_locale_default_language_uses_user_locale(): void {
        $GLOBALS['wordopedia_app_test_user_locale'] = 'de_DE';

        $this->assertSame( 'de', App::get_locale_default_language() );
    }

    public function test_default_language_uses_english_without_preferences(): void {
        $GLOBALS['wordopedia_app_test_user_locale'] = 'de_DE';

        $this->assertSame( 'en', App::get_default_language() );
        $this->assertSame( 'en', App::normalize_language() );
    }

    public function test_normalize_language_rejects_invalid_subdomain(): void {
        $result = App::normalize_language( '../en' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'wordopedia_invalid_language', $result->get_error_code() );
    }

    public function test_language_labels_include_supported_and_unknown_codes(): void {
        $this->assertSame( 'English', App::get_language_label( 'en' ) );
        $this->assertSame( 'SCO', App::get_language_label( 'sco' ) );
    }

    public function test_normalize_language_list_dedupes_and_rejects_invalid_codes(): void {
        $this->assertSame(
            [ 'de', 'en', 'simple' ],
            App::normalize_language_list( [ 'DE', '../bad', 'en', 'de', 'simple' ] )
        );
    }

    public function test_saved_article_slug_includes_language_and_title(): void {
        $this->assertSame(
            'de-albert-einstein',
            App::build_article_slug( 'de', 'Albert Einstein', 736 )
        );
    }

    public function test_article_url_prefers_title_for_readable_urls(): void {
        $this->assertSame(
            'https://example.test/wordopedia/article/de?title=Albert%20Einstein',
            App::get_article_url( 'de', 'Albert Einstein', 736 )
        );
    }

    public function test_article_url_can_use_title(): void {
        $this->assertSame(
            'https://example.test/wordopedia/article/en?title=Albert%20Einstein',
            App::get_article_url( 'en', 'Albert Einstein' )
        );
    }

    public function test_article_url_can_use_page_id_when_title_is_missing(): void {
        $this->assertSame(
            'https://example.test/wordopedia/article/de?page_id=736',
            App::get_article_url( 'de', '', 736 )
        );
    }

    public function test_saved_articles_url(): void {
        $this->assertSame( 'https://example.test/wordopedia/saved', App::get_saved_articles_url() );
    }

    public function test_group_articles_by_initial_sorts_titles_and_symbols_last(): void {
        $groups = App::group_articles_by_initial( [
            [ 'title' => 'Zebra' ],
            [ 'title' => 'algebra' ],
            [ 'title' => 'Apple' ],
            [ 'title' => '123 History' ],
        ] );

        $this->assertSame( [ 'A', 'Z', '#' ], array_keys( $groups ) );
        $this->assertSame( [ 'algebra', 'Apple' ], array_column( $groups['A'], 'title' ) );
        $this->assertSame( [ '123 History' ], array_column( $groups['#'], 'title' ) );
    }

    public function test_saved_snippets_url(): void {
        $this->assertSame( 'https://example.test/wordopedia/snippets', App::get_saved_snippets_url() );
    }

    public function test_settings_and_list_urls(): void {
        $this->assertSame( 'https://example.test/wordopedia/settings', App::get_settings_url() );
        $this->assertSame( 'https://example.test/wordopedia/list/science', App::get_list_url( 'Science' ) );
    }

    public function test_ai_assistant_welcome_tips_add_wordopedia_contextual_tips(): void {
        $app = $this->newAppWithoutConstructor();
        $tips = $app->register_ai_assistant_welcome_tips( [
            'other' => [
                'Existing tip.',
            ],
        ], [
            'path' => '/wordopedia/?query=relativity',
        ] );

        $this->assertSame( [ 'Existing tip.' ], $tips['other'] );
        $this->assertArrayHasKey( 'wordopedia', $tips );
        $this->assertCount( 3, $tips['wordopedia'] );
        $this->assertStringContainsString( 'search Wikipedia', $tips['wordopedia'][0] );
        $this->assertStringContainsString( 'extract specific facts', $tips['wordopedia'][1] );
        $this->assertStringContainsString( 'saved snippet', $tips['wordopedia'][1] );
        $this->assertStringContainsString( 'SVG diagrams', $tips['wordopedia'][2] );
    }

    public function test_ai_assistant_welcome_tips_use_route_component_key_for_subroutes(): void {
        $app = $this->newAppWithoutConstructor();
        $tips = $app->register_ai_assistant_welcome_tips( [], [
            'path' => '/wordopedia/article/de?title=Albert%20Einstein',
        ] );

        $this->assertArrayHasKey( 'wordopedia', $tips );
        $this->assertArrayNotHasKey( 'wordopedia-article', $tips );
        $this->assertArrayNotHasKey( 'article', $tips );
        $this->assertStringContainsString( 'save the best result to Wordopedia', $tips['wordopedia'][0] );
    }

    public function test_ability_article_payload_uses_html_without_raw_content(): void {
        $article = [
            'title'    => 'Example',
            'content'  => '<p>Article body</p>',
            'snippets' => [
                [
                    'post_id' => 123,
                    'content' => '<!-- wp:paragraph --><p>Snippet body</p><!-- /wp:paragraph -->',
                    'html'    => '<p>Snippet body</p>',
                    'text'    => 'Snippet body',
                ],
            ],
        ];

        $formatted = $this->invokePrivateStatic( 'format_ability_article', [ $article ] );

        $this->assertArrayNotHasKey( 'content', $formatted );
        $this->assertSame( '<p>Article body</p>', $formatted['html'] );
        $this->assertArrayNotHasKey( 'content', $formatted['snippets'][0] );
        $this->assertSame( '<p>Snippet body</p>', $formatted['snippets'][0]['html'] );
        $this->assertSame( 'Snippet body', $formatted['snippets'][0]['text'] );
    }

    public function test_ability_output_schemas_do_not_include_raw_content_duplicates(): void {
        $article_schema = $this->invokePrivateStatic( 'saved_article_schema', [ true ] );
        $article_properties = $article_schema['properties'];

        $this->assertArrayHasKey( 'html', $article_properties );
        $this->assertArrayHasKey( 'snippets', $article_properties );
        $this->assertArrayNotHasKey( 'content', $article_properties );

        $media_schema = $this->invokePrivateStatic( 'media_file_schema', [] );
        $media_properties = $media_schema['properties'];

        $this->assertArrayHasKey( 'original_url', $media_properties );
        $this->assertArrayHasKey( 'thumbnail_url', $media_properties );
        $this->assertArrayHasKey( 'description_url', $media_properties );
        $this->assertArrayHasKey( 'license', $media_properties );
        $this->assertArrayHasKey( 'attribution', $media_properties );

        $snippet_schema = $this->invokePrivateStatic( 'snippet_schema', [ true ] );
        $snippet_properties = $snippet_schema['properties'];

        $this->assertArrayHasKey( 'html', $snippet_properties );
        $this->assertArrayHasKey( 'text', $snippet_properties );
        $this->assertArrayNotHasKey( 'content', $snippet_properties );
    }

    public function test_article_media_input_schema_supports_svg_filtering(): void {
        $schema = $this->invokePrivateStatic( 'article_media_input_schema', [] );
        $properties = $schema['properties'];

        $this->assertArrayHasKey( 'page_id', $properties );
        $this->assertArrayHasKey( 'title', $properties );
        $this->assertArrayHasKey( 'mime', $properties );
        $this->assertArrayHasKey( 'thumbnail_width', $properties );
        $this->assertStringContainsString( 'image/svg+xml', $properties['mime']['description'] );
        $this->assertFalse( $schema['additionalProperties'] );
    }

    public function test_media_file_title_normalization_accepts_urls_and_plain_names(): void {
        $this->assertSame(
            'File:Example logo.svg',
            $this->invokePrivateStatic( 'normalize_media_file_title', [ 'https://commons.wikimedia.org/wiki/File:Example_logo.svg' ] )
        );

        $this->assertSame(
            'File:Example diagram.svg',
            $this->invokePrivateStatic( 'normalize_media_file_title', [ 'Example_diagram.svg' ] )
        );

        $this->assertSame(
            'File:Old name.svg',
            $this->invokePrivateStatic( 'normalize_media_file_title', [ 'Image:Old_name.svg#Preview' ] )
        );

        $this->assertSame(
            'File:Vienna, administrative divisions - Nmbrs.svg',
            $this->invokePrivateStatic( 'normalize_media_file_title', [ 'Datei:Vienna,_administrative_divisions_-_Nmbrs.svg' ] )
        );

        $this->assertSame(
            'File:Example logo.svg',
            $this->invokePrivateStatic( 'normalize_media_file_title', [ 'Fichier:Example_logo.svg' ] )
        );

        $this->assertSame(
            'File:Example diagram.png',
            $this->invokePrivateStatic( 'normalize_media_file_title', [ 'Archivo:Example_diagram.png' ] )
        );
    }

    public function test_media_file_format_includes_svg_urls_and_plain_metadata(): void {
        $file = $this->invokePrivateStatic( 'format_media_file_page', [
            [
                'pageid'          => 42,
                'title'           => 'File:Example logo.svg',
                'imagerepository' => 'shared',
                'imageinfo'       => [
                    [
                        'canonicaltitle' => 'File:Example logo.svg',
                        'url'            => 'https://upload.wikimedia.org/wikipedia/commons/e/e0/Example_logo.svg',
                        'thumburl'       => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e0/Example_logo.svg/512px-Example_logo.svg.png',
                        'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Example_logo.svg',
                        'width'          => 320,
                        'height'         => 240,
                        'size'           => 1234,
                        'sha1'           => 'abc123',
                        'mime'           => 'image/svg+xml',
                        'mediatype'      => 'DRAWING',
                        'extmetadata'    => [
                            'ImageDescription' => [ 'value' => '<p>Example <strong>logo</strong></p>' ],
                            'LicenseShortName' => [ 'value' => 'CC BY-SA 4.0' ],
                            'LicenseUrl'       => [ 'value' => 'https://creativecommons.org/licenses/by-sa/4.0/' ],
                            'Artist'           => [ 'value' => '<a href="/wiki/User:Example">Example Artist</a>' ],
                            'Credit'           => [ 'value' => '<span>Own work</span>' ],
                            'Attribution'      => [ 'value' => 'Example Artist' ],
                            'Source'           => [ 'value' => 'Own work' ],
                        ],
                    ],
                ],
            ],
        ] );

        $this->assertSame( 42, $file['page_id'] );
        $this->assertSame( 'File:Example logo.svg', $file['title'] );
        $this->assertSame( 'Example logo.svg', $file['filename'] );
        $this->assertSame( 'image/svg+xml', $file['mime'] );
        $this->assertSame( 'DRAWING', $file['media_type'] );
        $this->assertSame( 'shared', $file['repository'] );
        $this->assertSame( 'https://upload.wikimedia.org/wikipedia/commons/e/e0/Example_logo.svg', $file['original_url'] );
        $this->assertSame( 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e0/Example_logo.svg/512px-Example_logo.svg.png', $file['thumbnail_url'] );
        $this->assertSame( 'https://commons.wikimedia.org/wiki/File:Example_logo.svg', $file['description_url'] );
        $this->assertSame( 'Example logo', $file['description'] );
        $this->assertSame( 'CC BY-SA 4.0', $file['license'] );
        $this->assertSame( 'https://creativecommons.org/licenses/by-sa/4.0/', $file['license_url'] );
        $this->assertSame( 'Example Artist', $file['artist'] );
        $this->assertSame( 'Own work', $file['credit'] );
        $this->assertSame( 'Example Artist', $file['attribution'] );
        $this->assertSame( 'Own work', $file['source'] );
    }

    public function test_media_file_format_accepts_shared_known_missing_pages(): void {
        $file = $this->invokePrivateStatic( 'format_media_file_page', [
            [
                'ns'              => 6,
                'title'           => 'Datei:Vienna, administrative divisions - Nmbrs.svg',
                'missing'         => true,
                'known'           => true,
                'imagerepository' => 'shared',
                'imageinfo'       => [
                    [
                        'canonicaltitle' => 'Datei:Vienna, administrative divisions - Nmbrs.svg',
                        'url'            => 'https://upload.wikimedia.org/wikipedia/commons/1/1f/Vienna%2C_administrative_divisions_-_Nmbrs.svg',
                        'thumburl'       => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1f/Vienna%2C_administrative_divisions_-_Nmbrs.svg/960px-Vienna%2C_administrative_divisions_-_Nmbrs.svg.png',
                        'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Vienna,_administrative_divisions_-_Nmbrs.svg',
                        'mime'           => 'image/svg+xml',
                        'mediatype'      => 'DRAWING',
                    ],
                ],
            ],
        ] );

        $this->assertSame( 'File:Vienna, administrative divisions - Nmbrs.svg', $file['title'] );
        $this->assertSame( 'Vienna, administrative divisions - Nmbrs.svg', $file['filename'] );
        $this->assertSame( 'shared', $file['repository'] );
        $this->assertSame( 'image/svg+xml', $file['mime'] );
        $this->assertSame( 'https://upload.wikimedia.org/wikipedia/commons/1/1f/Vienna%2C_administrative_divisions_-_Nmbrs.svg', $file['original_url'] );
    }

    public function test_media_thumbnail_width_is_clamped(): void {
        $this->assertSame( 64, $this->invokePrivateStatic( 'normalize_media_thumbnail_width', [ 1 ] ) );
        $this->assertSame( 512, $this->invokePrivateStatic( 'normalize_media_thumbnail_width', [ 0 ] ) );
        $this->assertSame( 2000, $this->invokePrivateStatic( 'normalize_media_thumbnail_width', [ 5000 ] ) );
    }

    public function test_article_allowed_html_contains_expected_article_tags(): void {
        $allowed = App::article_allowed_html();

        $this->assertArrayHasKey( 'a', $allowed );
        $this->assertArrayHasKey( 'href', $allowed['a'] );
        $this->assertArrayHasKey( 'table', $allowed );
        $this->assertArrayHasKey( 'img', $allowed );
        $this->assertArrayHasKey( 'src', $allowed['img'] );
    }

    public function test_snippet_text_is_plain_and_normalized(): void {
        $text = "  Albert&nbsp;<strong>Einstein</strong>\r\n\r\n\r\n developed\t relativity.  ";

        $this->assertSame(
            "Albert Einstein\n\ndeveloped relativity.",
            $this->invokePrivateStatic( 'normalize_snippet_text', [ $text ] )
        );
    }

    public function test_snippet_excerpt_trims_to_word_limit(): void {
        $this->assertSame(
            'one two three...',
            $this->invokePrivateStatic( 'snippet_excerpt', [ 'one two three four five', 3 ] )
        );
    }

    public function test_snippet_content_is_stored_as_blocks_and_read_as_text(): void {
        $content = $this->invokePrivateStatic( 'snippet_content_for_storage', [ "one\ntwo\n\nthree & four" ] );

        $this->assertStringContainsString( '<!-- wp:paragraph -->', $content );
        $this->assertStringContainsString( '<p>one<br>two</p>', $content );
        $this->assertStringContainsString( '<p>three &amp; four</p>', $content );
        $this->assertSame(
            "one\ntwo\n\nthree & four",
            $this->invokePrivateStatic( 'snippet_plain_text_from_content', [ $content ] )
        );
    }

    public function test_snippet_content_preserves_links_from_html(): void {
        $content = $this->invokePrivateStatic( 'snippet_content_for_storage', [
            'Albert relativity & science',
            '<p>Albert <a class="mw-redirect" onclick="alert(1)" href="https://example.test/wordopedia/article/en?title=Relativity">relativity</a> &amp; science</p>',
        ] );

        $this->assertStringContainsString( '<a href="https://example.test/wordopedia/article/en?title=Relativity">relativity</a>', $content );
        $this->assertStringNotContainsString( 'onclick', $content );
        $this->assertStringNotContainsString( 'mw-redirect', $content );
        $this->assertSame(
            'Albert relativity & science',
            $this->invokePrivateStatic( 'snippet_plain_text_from_content', [ $content ] )
        );
    }

    public function test_snippet_content_drops_unsafe_html_from_links(): void {
        $content = $this->invokePrivateStatic( 'snippet_content_for_storage', [
            'Safe bad link and encoded link and bold',
            '<p>Safe <a href="javascript:alert(1)">bad link</a> and <a href="java&#x0A;script:alert(1)">encoded link</a><script>alert(1)</script> and <strong>bold</strong></p>',
        ] );

        $this->assertStringContainsString( 'Safe bad link and encoded link and <strong>bold</strong>', $content );
        $this->assertStringNotContainsString( 'javascript:', $content );
        $this->assertStringNotContainsString( '<script', $content );
        $this->assertSame(
            'Safe bad link and encoded link and bold',
            $this->invokePrivateStatic( 'snippet_plain_text_from_content', [ $content ] )
        );
    }

    public function test_wordopedia_article_href_is_rewritten_to_app_url(): void {
        $this->assertSame(
            'https://example.test/wordopedia/article/en?title=Albert%20Einstein#Life',
            $this->invokePrivateStatic( 'app_url_from_wikipedia_href', [ '/wiki/Albert_Einstein#Life', 'en' ] )
        );
    }

    public function test_cross_language_wordopedia_href_is_rewritten_to_that_language(): void {
        $this->assertSame(
            'https://example.test/wordopedia/article/de?title=Albert%20Einstein',
            $this->invokePrivateStatic( 'app_url_from_wikipedia_href', [ 'https://de.wikipedia.org/wiki/Albert_Einstein', 'en' ] )
        );
    }

    public function test_non_article_or_external_hrefs_are_not_rewritten(): void {
        $this->assertSame( '', $this->invokePrivateStatic( 'app_url_from_wikipedia_href', [ '/wiki/File:Example.jpg', 'en' ] ) );
        $this->assertSame( '', $this->invokePrivateStatic( 'app_url_from_wikipedia_href', [ 'https://example.com/wiki/Albert_Einstein', 'en' ] ) );
    }

    public function test_article_resource_nodes_are_removed(): void {
        $html = '<style>.mw-parser-output .hatnote{font-style:italic}</style><link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles"><p>Article body</p>';
        $cleaned = $this->invokePrivateStatic( 'remove_article_resource_nodes', [ $html ] );

        $this->assertStringNotContainsString( 'mw-parser-output', $cleaned );
        $this->assertStringNotContainsString( '<style', $cleaned );
        $this->assertStringNotContainsString( '<link', $cleaned );
        $this->assertStringContainsString( 'Article body', $cleaned );
    }

    public function test_article_images_from_html_extracts_unique_sources(): void {
        $html = '<p><img src="//upload.wikimedia.org/example-a.jpg" alt="Example A" width="120" height="80" srcset="//upload.wikimedia.org/example-a-2x.jpg 2x"><img src="//upload.wikimedia.org/example-a.jpg" alt="Duplicate"><img src="https://example.test/wp-content/uploads/local.jpg" alt=""></p>';

        $images = App::article_images_from_html( $html );

        $this->assertCount( 2, $images );
        $this->assertSame( 'https://upload.wikimedia.org/example-a.jpg', $images[0]['url'] );
        $this->assertSame( 'Example A', $images[0]['label'] );
        $this->assertSame( 120, $images[0]['width'] );
        $this->assertFalse( $images[0]['is_local'] );
        $this->assertSame( 'https://example.test/wp-content/uploads/local.jpg', $images[1]['url'] );
        $this->assertTrue( $images[1]['is_local'] );
    }

    public function test_article_images_from_html_skips_hidden_utility_images(): void {
        $html = '<div class="metadata"><img src="//upload.wikimedia.org/hidden-metadata.png" alt="Hidden"></div><div class="noprint"><img src="//upload.wikimedia.org/hidden-print.png" alt="Hidden"></div><p><img src="//upload.wikimedia.org/visible.jpg" alt="Visible"></p>';

        $images = App::article_images_from_html( $html );

        $this->assertCount( 1, $images );
        $this->assertSame( 'https://upload.wikimedia.org/visible.jpg', $images[0]['url'] );
    }

    public function test_article_image_rewrite_replaces_selected_src_and_removes_srcset(): void {
        $html = '<figure><img src="//upload.wikimedia.org/example-a.jpg" alt="A" srcset="//upload.wikimedia.org/example-a.jpg 1x, //upload.wikimedia.org/example-a-2x.jpg 2x" sizes="100vw"><img src="//upload.wikimedia.org/example-b.jpg" alt="B" srcset="//upload.wikimedia.org/example-b-2x.jpg 2x"></figure>';

        $rewritten = $this->invokePrivateStatic( 'rewrite_article_image_urls', [
            $html,
            [
                'https://upload.wikimedia.org/example-a.jpg' => 'https://example.test/wp-content/uploads/example-a.jpg',
            ],
        ] );

        $this->assertStringContainsString( 'src="https://example.test/wp-content/uploads/example-a.jpg"', $rewritten );
        $this->assertStringNotContainsString( 'example-a-2x.jpg', $rewritten );
        $this->assertStringNotContainsString( 'sizes="100vw"', $rewritten );
        $this->assertStringContainsString( 'example-b.jpg', $rewritten );
        $this->assertStringContainsString( 'example-b-2x.jpg', $rewritten );
    }

    public function test_wordopedia_request_headers_identify_normal_wordpress_sites(): void {
        $headers = $this->invokePrivateStatic( 'wordopedia_request_headers', [] );
        $user_agent = $this->invokePrivateStatic( 'wordopedia_user_agent', [] );

        $this->assertSame( 'application/json', $headers['Accept'] );
        $this->assertArrayNotHasKey( 'User-Agent', $headers );
        $this->assertStringContainsString( 'Wordopedia/1.0', $user_agent );
        $this->assertStringContainsString( 'https://example.test/', $user_agent );
    }

    /**
     * @runInSeparateProcess
     */
    public function test_wordpress_playground_detection_uses_playground_constant(): void {
        if ( ! defined( 'PLAYGROUND_AUTO_LOGIN_AS_USER' ) ) {
            define( 'PLAYGROUND_AUTO_LOGIN_AS_USER', 1 );
        }

        $this->assertTrue( $this->invokePrivateStatic( 'is_wordpress_playground', [] ) );
    }

    public function test_wordopedia_http_error_prefers_api_error_body(): void {
        $error = $this->invokePrivateStatic( 'wordopedia_http_error', [
            400,
            [ 'error' => [ 'info' => '<strong>Bad request</strong>' ] ],
            [],
        ] );

        $this->assertInstanceOf( WP_Error::class, $error );
        $this->assertSame( 'wordopedia_api_error', $error->get_error_code() );
        $this->assertSame( 'Bad request', $error->get_error_message() );
    }

    public function test_wordopedia_http_error_reports_retry_after_for_rate_limits(): void {
        $error = $this->invokePrivateStatic( 'wordopedia_http_error', [
            429,
            [],
            [ 'headers' => [ 'retry-after' => '60' ] ],
        ] );

        $this->assertInstanceOf( WP_Error::class, $error );
        $this->assertSame( 'wordopedia_rate_limited', $error->get_error_code() );
        $this->assertStringContainsString( '60', $error->get_error_message() );
    }

    private function invokePrivateStatic( string $method, array $args ) {
        $reflection = new ReflectionMethod( App::class, $method );
        $reflection->setAccessible( true );

        return $reflection->invokeArgs( null, $args );
    }

    private function newAppWithoutConstructor(): App {
        $reflection = new ReflectionClass( App::class );

        return $reflection->newInstanceWithoutConstructor();
    }
}
