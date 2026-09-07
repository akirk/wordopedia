<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

$article = isset( $article ) && is_array( $article ) ? $article : [];
$is_saved_view = ! empty( $is_saved_view );
?>
<?php if ( ! $is_saved_view && ! empty( $article['available_languages'] ) ) : ?>
    <label class="wiki-language-switcher">
        <span><?php esc_html_e( 'Language', 'wordopedia' ); ?></span>
        <select onchange="if (this.value) window.location.href = this.value;">
            <option value="<?php echo esc_url( $article['app_url'] ); ?>" selected><?php echo esc_html( $article['language_label'] . ' (' . $article['language'] . ')' ); ?></option>
            <?php foreach ( $article['available_languages'] as $translation ) : ?>
                <option value="<?php echo esc_url( $translation['app_url'] ); ?>">
                    <?php echo esc_html( $translation['language_label'] . ' (' . $translation['language'] . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
<?php endif; ?>
<?php if ( ! $is_saved_view && ! empty( $article['saved_article']['view_url'] ) ) : ?>
    <a class="wiki-btn" href="<?php echo esc_url( $article['saved_article']['view_url'] ); ?>"><?php esc_html_e( 'Open saved', 'wordopedia' ); ?></a>
<?php endif; ?>
<?php if ( $is_saved_view && ! empty( $article['source_url'] ) ) : ?>
    <a class="wiki-btn secondary" href="<?php echo esc_url( $article['source_url'] ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'View on Wikipedia', 'wordopedia' ); ?></a>
<?php endif; ?>
<?php if ( current_user_can( 'edit_posts' ) ) : ?>
    <?php if ( $is_saved_view && ! empty( $article['post_id'] ) ) : ?>
        <?php if ( current_user_can( 'edit_post', $article['post_id'] ) && current_user_can( 'upload_files' ) ) : ?>
            <button
                class="wiki-btn secondary"
                type="button"
                data-wiki-image-download-open
                data-action-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                data-action-name="wordopedia_sideload_article_images"
                data-post-id="<?php echo esc_attr( $article['post_id'] ); ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( App::NONCE_SIDELOAD_IMAGES . '_' . $article['post_id'] ) ); ?>"
                data-title="<?php esc_attr_e( 'Download images', 'wordopedia' ); ?>"
                data-close-text="<?php esc_attr_e( 'Close', 'wordopedia' ); ?>"
                data-select-all-text="<?php esc_attr_e( 'Select all', 'wordopedia' ); ?>"
                data-select-none-text="<?php esc_attr_e( 'Select none', 'wordopedia' ); ?>"
                data-already-local-text="<?php esc_attr_e( 'Already local', 'wordopedia' ); ?>"
                data-cancel-text="<?php esc_attr_e( 'Cancel', 'wordopedia' ); ?>"
                data-download-selected-text="<?php esc_attr_e( 'Download selected', 'wordopedia' ); ?>"
                data-article-image-text="<?php esc_attr_e( 'Article image', 'wordopedia' ); ?>"
                data-no-images-text="<?php esc_attr_e( 'No article images found.', 'wordopedia' ); ?>"
                data-remote-images-label="<?php esc_attr_e( 'Remote images', 'wordopedia' ); ?>"
                data-info-text="<?php esc_attr_e( 'The downloaded images in the article will then be loaded from your media library.', 'wordopedia' ); ?>"
            ><?php esc_html_e( 'Download images', 'wordopedia' ); ?></button>
        <?php endif; ?>
        <form class="wiki-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( App::NONCE_REFETCH_ARTICLE . '_' . $article['post_id'] ); ?>
            <input type="hidden" name="action" value="wordopedia_refetch_article">
            <input type="hidden" name="post_id" value="<?php echo esc_attr( $article['post_id'] ); ?>">
            <button class="wiki-btn" type="submit"><?php esc_html_e( 'Refetch', 'wordopedia' ); ?></button>
        </form>
    <?php elseif ( ! empty( $article['page_id'] ) ) : ?>
        <form class="wiki-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( App::NONCE_SAVE_ARTICLE ); ?>
            <input type="hidden" name="action" value="wordopedia_save_article">
            <input type="hidden" name="page_id" value="<?php echo esc_attr( $article['page_id'] ); ?>">
            <input type="hidden" name="title" value="<?php echo esc_attr( $article['title'] ); ?>">
            <input type="hidden" name="language" value="<?php echo esc_attr( $article['language'] ); ?>">
            <button class="wiki-btn" type="submit">
                <?php echo esc_html( ! empty( $article['saved_article'] ) ? __( 'Update saved', 'wordopedia' ) : __( 'Save article', 'wordopedia' ) ); ?>
            </button>
        </form>
    <?php endif; ?>
<?php endif; ?>
