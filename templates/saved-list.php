<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

global $wp_app_route;

$params = isset( $wp_app_route['params'] ) && is_array( $wp_app_route['params'] ) ? $wp_app_route['params'] : [];
$list_slug = isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';
$current_list = $list_slug ? get_term_by( 'slug', $list_slug, App::TAX_LIST ) : null;
if ( $current_list && is_wp_error( $current_list ) ) {
    $current_list = null;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- saved article filtering is read-only.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$saved_articles = App::list_saved_articles( $search, 50, '', $list_slug );
$saved_articles_by_letter = App::group_articles_by_initial( $saved_articles );
$lists = get_terms( [
    'taxonomy'   => App::TAX_LIST,
    'hide_empty' => true,
] );
if ( is_wp_error( $lists ) ) {
    $lists = [];
}

$page_title = $current_list ? $current_list->name : __( 'Saved articles', 'wordopedia' );
$wiki_current_nav = 'saved';
include __DIR__ . '/_header.php';
?>
<div class="wiki-page-head">
    <div>
        <h1 id="wordopedia-saved-articles-title"><?php echo esc_html( $page_title ); ?></h1>
    </div>
</div>

<?php if ( isset( $_GET['wordopedia_error'] ) ) : ?>
    <div class="wiki-notice error"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['wordopedia_error'] ) ) ); ?></div>
<?php endif; ?>

<?php if ( $lists ) : ?>
    <nav class="wiki-list-tabs" aria-label="<?php esc_attr_e( 'Saved article lists', 'wordopedia' ); ?>">
        <a class="<?php echo esc_attr( '' === $list_slug ? 'is-active' : '' ); ?>" href="<?php echo esc_url( App::get_saved_articles_url() ); ?>"><?php esc_html_e( 'All', 'wordopedia' ); ?></a>
        <?php foreach ( $lists as $list ) : ?>
            <a class="<?php echo esc_attr( $list->slug === $list_slug ? 'is-active' : '' ); ?>" href="<?php echo esc_url( App::get_list_url( $list ) ); ?>"><?php echo esc_html( $list->name ); ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php
$wiki_saved_search = $search;
$wiki_saved_search_action = $current_list ? App::get_list_url( $current_list ) : App::get_saved_articles_url();
include __DIR__ . '/_saved-search-form.php';
?>

<section class="wiki-home-saved" id="wiki-saved-list" aria-labelledby="wordopedia-saved-articles-title" data-ai-assistant-important>
    <?php
    $wiki_saved_alpha_empty_message = __( 'No saved articles found.', 'wordopedia' );
    include __DIR__ . '/_saved-alpha-list.php';
    ?>
</section>
<?php include __DIR__ . '/_footer.php'; ?>
