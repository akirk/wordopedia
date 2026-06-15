<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- saved snippet filtering is read-only.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$requested_language = isset( $_GET['language'] ) ? sanitize_text_field( wp_unslash( $_GET['language'] ) ) : '';
$language = '';
$error_message = '';

if ( '' !== $requested_language ) {
    $normalized_language = App::normalize_language( $requested_language );
    if ( is_wp_error( $normalized_language ) ) {
        $error_message = $normalized_language->get_error_message();
    } else {
        $language = $normalized_language;
    }
}

$snippets = [];
if ( '' === $error_message ) {
    $snippets = App::search_wordopedia_snippets( $search, 50, 0, $language );
    if ( is_wp_error( $snippets ) ) {
        $error_message = $snippets->get_error_message();
        $snippets = [];
    }
}

$snippet_languages = App::get_saved_snippet_languages();
if ( '' !== $language && ! isset( $snippet_languages[ $language ] ) ) {
    $snippet_languages[ $language ] = App::get_language_label( $language );
    asort( $snippet_languages, SORT_NATURAL | SORT_FLAG_CASE );
}

$snippet_count = count( $snippets );
$page_title = __( 'Saved snippets', 'wordopedia' );
$wiki_current_nav = 'snippets';
include __DIR__ . '/_header.php';
?>
<div class="wiki-page-head">
    <div>
        <h1 id="wordopedia-saved-snippets-title"><?php esc_html_e( 'Saved snippets', 'wordopedia' ); ?></h1>
    </div>
</div>

<?php if ( isset( $_GET['wordopedia_error'] ) ) : ?>
    <div class="wiki-notice error"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['wordopedia_error'] ) ) ); ?></div>
<?php endif; ?>
<?php if ( $error_message ) : ?>
    <div class="wiki-notice error"><?php echo esc_html( $error_message ); ?></div>
<?php endif; ?>

<form class="wiki-search wiki-snippet-search" method="get" action="<?php echo esc_url( App::get_saved_snippets_url() ); ?>" aria-labelledby="wordopedia-snippet-search-label">
    <label class="wiki-search-field">
        <span id="wordopedia-snippet-search-label"><?php esc_html_e( 'Search snippets', 'wordopedia' ); ?></span>
        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" autocomplete="off" placeholder="<?php esc_attr_e( 'Search saved snippets', 'wordopedia' ); ?>" aria-controls="wiki-snippet-list">
    </label>
    <label class="wiki-search-language">
        <span><?php esc_html_e( 'Language', 'wordopedia' ); ?></span>
        <select name="language" aria-label="<?php esc_attr_e( 'Filter saved snippets by language', 'wordopedia' ); ?>">
            <option value=""><?php esc_html_e( 'All languages', 'wordopedia' ); ?></option>
            <?php foreach ( $snippet_languages as $code => $label ) : ?>
                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $language, $code ); ?>>
                    <?php echo esc_html( $label . ' (' . $code . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="wiki-btn wiki-search-submit" type="submit"><?php esc_html_e( 'Search', 'wordopedia' ); ?></button>
    <?php if ( '' !== $search || '' !== $language ) : ?>
        <a class="wiki-btn secondary" href="<?php echo esc_url( App::get_saved_snippets_url() ); ?>"><?php esc_html_e( 'Clear', 'wordopedia' ); ?></a>
    <?php endif; ?>
</form>

<?php if ( $snippets ) : ?>
    <div class="wiki-meta wiki-snippet-browser-summary">
        <span>
            <?php
            printf(
                /* translators: %d: number of saved snippets */
                esc_html( _n( '%d saved snippet', '%d saved snippets', $snippet_count, 'wordopedia' ) ),
                $snippet_count
            );
            ?>
        </span>
    </div>
    <ol class="wiki-snippet-list wiki-snippet-browser-list" id="wiki-snippet-list" aria-labelledby="wordopedia-saved-snippets-title" data-ai-assistant-important>
        <?php foreach ( $snippets as $snippet ) : ?>
            <?php
            $snippet_id = isset( $snippet['post_id'] ) ? absint( $snippet['post_id'] ) : 0;
            $snippet_title = isset( $snippet['title'] ) && '' !== (string) $snippet['title'] ? (string) $snippet['title'] : __( 'Saved snippet', 'wordopedia' );
            $snippet_text = isset( $snippet['text'] ) ? (string) $snippet['text'] : (string) ( $snippet['summary'] ?? '' );
            $snippet_html = isset( $snippet['html'] ) ? (string) $snippet['html'] : '';
            $parent_title = isset( $snippet['saved_article_title'] ) ? (string) $snippet['saved_article_title'] : '';
            $parent_view_url = isset( $snippet['parent_view_url'] ) ? (string) $snippet['parent_view_url'] : '';
            $view_url = isset( $snippet['view_url'] ) ? (string) $snippet['view_url'] : '';
            $source_url = isset( $snippet['source_url'] ) ? (string) $snippet['source_url'] : '';
            $edit_url = isset( $snippet['edit_url'] ) ? (string) $snippet['edit_url'] : '';
            $updated_at = isset( $snippet['updated_at_display'] ) ? (string) $snippet['updated_at_display'] : '';
            $snippet_language = isset( $snippet['language'] ) ? (string) $snippet['language'] : '';
            $snippet_language_label = isset( $snippet['language_label'] ) ? (string) $snippet['language_label'] : '';
            $can_edit_snippet = $snippet_id && $edit_url && current_user_can( 'edit_post', $snippet_id );
            ?>
            <li class="wiki-snippet wiki-snippet-browser-item" id="<?php echo esc_attr( 'wiki-snippet-' . $snippet_id ); ?>">
                <h2 class="wiki-snippet-browser-title">
                    <?php if ( $view_url ) : ?>
                        <a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $snippet_title ); ?></a>
                    <?php else : ?>
                        <?php echo esc_html( $snippet_title ); ?>
                    <?php endif; ?>
                </h2>
                <div class="wiki-meta">
                    <?php if ( $parent_title && $parent_view_url ) : ?>
                        <span><?php esc_html_e( 'Article', 'wordopedia' ); ?> <a href="<?php echo esc_url( $parent_view_url ); ?>"><?php echo esc_html( $parent_title ); ?></a></span>
                    <?php elseif ( $parent_title ) : ?>
                        <span><?php echo esc_html( $parent_title ); ?></span>
                    <?php endif; ?>
                    <?php if ( $snippet_language ) : ?>
                        <span title="<?php echo esc_attr( $snippet_language_label ); ?>" aria-label="<?php echo esc_attr( $snippet_language_label . ' (' . $snippet_language . ')' ); ?>"><?php echo esc_html( $snippet_language ); ?></span>
                    <?php endif; ?>
                    <?php if ( $updated_at ) : ?>
                        <span>
                            <?php
                            printf(
                                /* translators: %s: snippet updated date */
                                esc_html__( 'Updated %s', 'wordopedia' ),
                                esc_html( $updated_at )
                            );
                            ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( $source_url ) : ?>
                        <span><a href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Source', 'wordopedia' ); ?></a></span>
                    <?php endif; ?>
                    <?php if ( $can_edit_snippet ) : ?>
                        <span><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'wordopedia' ); ?></a></span>
                    <?php endif; ?>
                </div>
                <div class="wiki-snippet-content">
                    <?php if ( '' !== trim( $snippet_html ) ) : ?>
                        <?php echo wp_kses_post( $snippet_html ); ?>
                    <?php elseif ( '' !== trim( $snippet_text ) ) : ?>
                        <p><?php echo esc_html( $snippet_text ); ?></p>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
<?php else : ?>
    <div class="wiki-notice"><?php esc_html_e( 'No saved snippets found.', 'wordopedia' ); ?></div>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
