<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

global $wp_app_route;

$route_language = isset( $wp_app_route['params']['language'] ) ? sanitize_text_field( $wp_app_route['params']['language'] ) : App::get_default_language();
$language = App::normalize_language( $route_language );
if ( is_wp_error( $language ) ) {
    $language = App::get_default_language();
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- article lookup is read-only.
$title = isset( $_GET['title'] ) ? sanitize_text_field( wp_unslash( $_GET['title'] ) ) : '';
$page_id = isset( $_GET['page_id'] ) ? absint( wp_unslash( $_GET['page_id'] ) ) : 0;
$article = null;
$error = null;

if ( $page_id || '' !== $title ) {
    $article = App::fetch_wordopedia_article( [
        'page_id'  => $page_id,
        'title'    => $title,
        'language' => $language,
    ] );

    if ( is_wp_error( $article ) ) {
        $error = $article;
        $article = null;
    }
}

$page_title = $article ? $article['title'] : __( 'Encyclopedia article', 'wordopedia' );
$wiki_current_nav = 'search';
if ( $article ) {
    $wiki_article_actions = [
        'article'       => $article,
        'is_saved_view' => false,
    ];
}
include __DIR__ . '/_header.php';
?>
<?php
$wiki_search_query = $title;
$wiki_search_language = $language;
include __DIR__ . '/_search-form.php';
?>
<?php
$language_tabs_query = $title;
$language_tabs_article = $article;
if ( $article ) {
    include __DIR__ . '/_article-language-tabs.php';
} else {
    include __DIR__ . '/_search-language-tabs.php';
}
?>

<section class="wiki-search-results" id="wiki-search-results" aria-labelledby="wiki-search-results-title" data-wiki-quicksearch-results aria-live="polite">
    <h2 id="wiki-search-results-title" class="screen-reader-text"><?php esc_html_e( 'Search results', 'wordopedia' ); ?></h2>
</section>

<?php if ( isset( $_GET['wordopedia_error'] ) ) : ?>
    <div class="wiki-notice error"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['wordopedia_error'] ) ) ); ?></div>
<?php endif; ?>

<?php if ( $error ) : ?>
    <div class="wiki-notice error"><?php echo esc_html( $error->get_error_message() ); ?></div>
<?php elseif ( ! $article ) : ?>
    <div class="wiki-notice"><?php esc_html_e( 'Choose an article from search results.', 'wordopedia' ); ?></div>
<?php else : ?>
    <?php
    $is_saved_view = false;
    include __DIR__ . '/_article-view.php';
    ?>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
