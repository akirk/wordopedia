<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- search and filters are read-only.
$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$language_input = isset( $_GET['language'] ) ? sanitize_text_field( wp_unslash( $_GET['language'] ) ) : App::get_default_language();
$language = App::normalize_language( $language_input );
if ( is_wp_error( $language ) ) {
    $language = App::get_default_language();
}

$results = null;
$search_error = null;
if ( '' !== $query ) {
    $results = App::search_wordopedia_articles( $query, $language, 12 );
    if ( is_wp_error( $results ) ) {
        $search_error = $results;
        $results = [];
    }
}

$saved_posts = get_posts( [
    'post_type'      => App::POST_TYPE,
    'post_status'    => [ 'publish', 'draft', 'private' ],
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

$saved_articles = [];
foreach ( $saved_posts as $saved_post ) {
    $saved_articles[] = App::format_saved_article( $saved_post );
}

$saved_articles_by_letter = App::group_articles_by_initial( $saved_articles );

$page_title = __( 'Wordopedia', 'wordopedia' );
$wiki_current_nav = 'search';
include __DIR__ . '/_header.php';
?>
<div class="wiki-page-head">
    <div>
        <h1><?php esc_html_e( 'Search Wikipedia', 'wordopedia' ); ?></h1>
    </div>
</div>

<?php if ( isset( $_GET['wordopedia_error'] ) ) : ?>
    <div class="wiki-notice error"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['wordopedia_error'] ) ) ); ?></div>
<?php endif; ?>

<?php
$wiki_search_query = $query;
$wiki_search_language = $language;
include __DIR__ . '/_search-form.php';
?>
<?php
$language_tabs_query = $query;
$language_tabs_hidden = '' === trim( $query );
include __DIR__ . '/_search-language-tabs.php';
?>

<section class="wiki-search-results" id="wiki-search-results" aria-labelledby="wiki-search-results-title" data-wiki-quicksearch-results data-ai-assistant-important aria-live="polite">
    <h2 id="wiki-search-results-title" class="screen-reader-text"><?php esc_html_e( 'Search results', 'wordopedia' ); ?></h2>
    <?php if ( $search_error ) : ?>
        <div class="wiki-notice error"><?php echo esc_html( $search_error->get_error_message() ); ?></div>
    <?php elseif ( is_array( $results ) ) : ?>
        <?php if ( $results ) : ?>
            <ul class="wiki-results">
                <?php foreach ( $results as $result ) : ?>
                    <li class="wiki-result">
                        <h2><a href="<?php echo esc_url( $result['app_url'] ); ?>"><?php echo esc_html( $result['title'] ); ?></a></h2>
                        <div class="wiki-meta">
                            <span title="<?php echo esc_attr( $result['language_label'] ); ?>" aria-label="<?php echo esc_attr( $result['language_label'] . ' (' . $result['language'] . ')' ); ?>"><?php echo esc_html( $result['language'] ); ?></span>
                            <?php if ( ! empty( $result['word_count'] ) ) : ?>
                                <span><?php echo esc_html( number_format_i18n( $result['word_count'] ) . ' ' . __( 'words', 'wordopedia' ) ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ( ! empty( $result['snippet'] ) ) : ?>
                            <p><?php echo esc_html( $result['snippet'] ); ?></p>
                        <?php endif; ?>
                        <div class="wiki-article-tools">
                            <a class="wiki-btn secondary" href="<?php echo esc_url( $result['app_url'] ); ?>"><?php esc_html_e( 'Read', 'wordopedia' ); ?></a>
                            <?php if ( current_user_can( 'edit_posts' ) ) : ?>
                                <form class="wiki-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <?php wp_nonce_field( App::NONCE_SAVE_ARTICLE ); ?>
                                    <input type="hidden" name="action" value="wordopedia_save_article">
                                    <input type="hidden" name="page_id" value="<?php echo esc_attr( $result['page_id'] ); ?>">
                                    <input type="hidden" name="title" value="<?php echo esc_attr( $result['title'] ); ?>">
                                    <input type="hidden" name="language" value="<?php echo esc_attr( $result['language'] ); ?>">
                                    <button class="wiki-btn secondary" type="submit"><?php esc_html_e( 'Save article', 'wordopedia' ); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <div class="wiki-notice"><?php esc_html_e( 'No Wikipedia results found.', 'wordopedia' ); ?></div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="wiki-home-saved" id="wiki-home-saved-articles" aria-labelledby="wiki-home-saved-articles-title">
    <div class="wiki-section-head">
        <div>
            <h2 id="wiki-home-saved-articles-title"><a href="<?php echo esc_url( App::get_saved_articles_url() ); ?>"><?php esc_html_e( 'Saved articles', 'wordopedia' ); ?></a></h2>
            <p class="wiki-subtitle">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: number of saved articles */
                        _n( '%d article saved in WordPress.', '%d articles saved in WordPress.', count( $saved_articles ), 'wordopedia' ),
                        count( $saved_articles )
                    )
                );
                ?>
            </p>
        </div>
    </div>

    <?php
    $wiki_saved_alpha_empty_message = __( 'No saved articles yet.', 'wordopedia' );
    include __DIR__ . '/_saved-alpha-list.php';
    ?>
</section>
<?php include __DIR__ . '/_footer.php'; ?>
