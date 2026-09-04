<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wiki_current_nav = isset( $wiki_current_nav ) ? sanitize_key( $wiki_current_nav ) : '';
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_the_title( isset( $page_title ) ? $page_title : __( 'Wordopedia', 'wordopedia' ) ); ?></title>
    <?php wp_app_head(); ?>
</head>
<body>
<?php wp_app_body_open(); ?>
<main class="wiki-shell">
    <nav class="wiki-topbar" aria-label="<?php esc_attr_e( 'Wordopedia app', 'wordopedia' ); ?>">
        <a class="wiki-brand" href="<?php echo esc_url( \Akirk\Wordopedia\App::get_app_url() ); ?>">
            <span class="wiki-brand-mark" aria-hidden="true">W</span>
            <span class="wiki-brand-text">
                <span class="wiki-wordmark"><?php esc_html_e( 'Wordopedia', 'wordopedia' ); ?></span>
                <span class="wiki-tagline"><?php esc_html_e( 'Personal encyclopedia', 'wordopedia' ); ?></span>
            </span>
        </a>
        <div class="wiki-nav" data-wiki-nav-menu-root>
            <a class="<?php echo esc_attr( 'search' === $wiki_current_nav ? 'is-active' : '' ); ?>" <?php echo 'search' === $wiki_current_nav ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( \Akirk\Wordopedia\App::get_app_url() ); ?>"><?php esc_html_e( 'Search', 'wordopedia' ); ?></a>
            <a class="<?php echo esc_attr( 'saved' === $wiki_current_nav ? 'is-active' : '' ); ?>" <?php echo 'saved' === $wiki_current_nav ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( \Akirk\Wordopedia\App::get_saved_articles_url() ); ?>"><?php esc_html_e( 'Saved articles', 'wordopedia' ); ?></a>
            <a class="<?php echo esc_attr( 'snippets' === $wiki_current_nav ? 'is-active' : '' ); ?>" <?php echo 'snippets' === $wiki_current_nav ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( \Akirk\Wordopedia\App::get_saved_snippets_url() ); ?>"><?php esc_html_e( 'Saved snippets', 'wordopedia' ); ?></a>
            <a class="<?php echo esc_attr( 'settings' === $wiki_current_nav ? 'is-active' : '' ); ?>" <?php echo 'settings' === $wiki_current_nav ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( \Akirk\Wordopedia\App::get_settings_url() ); ?>"><?php esc_html_e( 'Settings', 'wordopedia' ); ?></a>
            <?php if ( ! empty( $wiki_article_actions ) && is_array( $wiki_article_actions ) ) : ?>
                <div class="wiki-nav-menu">
                    <button class="wiki-nav-menu-toggle" type="button" data-wiki-nav-menu-toggle aria-expanded="false" aria-controls="wiki-nav-menu-panel" aria-label="<?php esc_attr_e( 'Article actions', 'wordopedia' ); ?>" title="<?php esc_attr_e( 'Article actions', 'wordopedia' ); ?>">
                        <span class="wiki-nav-menu-icon" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="wiki-nav-menu-panel" id="wiki-nav-menu-panel" data-wiki-nav-menu-panel hidden>
                    <?php
                    $article = isset( $wiki_article_actions['article'] ) && is_array( $wiki_article_actions['article'] ) ? $wiki_article_actions['article'] : [];
                    $is_saved_view = ! empty( $wiki_article_actions['is_saved_view'] );
                    include __DIR__ . '/_article-actions.php';
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="wiki-content">
