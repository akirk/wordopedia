<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

global $wp_app_route;

$params = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : [];
$post = App::get_saved_article_from_route( $params['id'] ?? 0, $params['slug'] ?? '' );

if ( ! $post ) {
    status_header( 404 );
}

$article = $post ? App::format_saved_article( $post, true ) : null;
$page_title = $article ? $article['title'] : __( 'Saved encyclopedia article', 'wordopedia' );
$wiki_current_nav = 'saved';
if ( $article ) {
    $wiki_article_actions = [
        'article'       => $article,
        'is_saved_view' => true,
    ];
}
include __DIR__ . '/_header.php';
?>
<?php if ( isset( $_GET['wordopedia_error'] ) ) : ?>
    <div class="wiki-notice error"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['wordopedia_error'] ) ) ); ?></div>
<?php endif; ?>
<?php if ( isset( $_GET['saved'] ) ) : ?>
    <div class="wiki-notice success"><?php esc_html_e( 'Article saved.', 'wordopedia' ); ?></div>
<?php endif; ?>
<?php if ( isset( $_GET['refetched'] ) ) : ?>
    <div class="wiki-notice success"><?php esc_html_e( 'Refetched from Wikipedia.', 'wordopedia' ); ?></div>
<?php endif; ?>
<?php if ( isset( $_GET['images_imported'] ) ) : ?>
    <?php $images_imported = absint( wp_unslash( $_GET['images_imported'] ) ); ?>
    <div class="wiki-notice success">
        <?php
        echo esc_html(
            sprintf(
                /* translators: %d: number of article images downloaded */
                _n( '%d image downloaded.', '%d images downloaded.', $images_imported, 'wordopedia' ),
                $images_imported
            )
        );
        ?>
    </div>
<?php endif; ?>
<?php if ( isset( $_GET['images_failed'] ) ) : ?>
    <div class="wiki-notice error"><?php esc_html_e( 'Some images could not be downloaded.', 'wordopedia' ); ?></div>
<?php endif; ?>
<?php if ( isset( $_GET['snippet_saved'] ) ) : ?>
    <div class="wiki-notice success"><?php esc_html_e( 'Snippet saved.', 'wordopedia' ); ?></div>
<?php endif; ?>
<?php if ( isset( $_GET['snippet_updated'] ) ) : ?>
    <div class="wiki-notice success"><?php esc_html_e( 'Snippet updated.', 'wordopedia' ); ?></div>
<?php endif; ?>
<?php if ( isset( $_GET['snippet_deleted'] ) ) : ?>
    <div class="wiki-notice success"><?php esc_html_e( 'Snippet deleted.', 'wordopedia' ); ?></div>
<?php endif; ?>

<?php
$wiki_saved_search = '';
$wiki_saved_search_action = App::get_saved_articles_url();
include __DIR__ . '/_saved-search-form.php';
?>

<?php if ( ! $article ) : ?>
    <div class="wiki-notice error"><?php esc_html_e( 'Saved article not found.', 'wordopedia' ); ?></div>
<?php else : ?>
    <?php
    $is_saved_view = true;
    include __DIR__ . '/_article-view.php';
    ?>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
