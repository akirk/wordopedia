<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$saved_articles_by_letter = isset( $saved_articles_by_letter ) && is_array( $saved_articles_by_letter ) ? $saved_articles_by_letter : [];
$wiki_saved_alpha_empty_message = isset( $wiki_saved_alpha_empty_message ) ? (string) $wiki_saved_alpha_empty_message : __( 'No saved articles found.', 'wordopedia' );
?>
<?php if ( $saved_articles_by_letter ) : ?>
    <nav class="wiki-alpha-index" aria-label="<?php esc_attr_e( 'Saved article index', 'wordopedia' ); ?>">
        <?php foreach ( array_keys( $saved_articles_by_letter ) as $letter ) : ?>
            <?php $letter_id = '#' === $letter ? 'other' : sanitize_title( $letter ); ?>
            <a class="wiki-chip" href="#saved-<?php echo esc_attr( $letter_id ); ?>"><?php echo esc_html( $letter ); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php foreach ( $saved_articles_by_letter as $letter => $group ) : ?>
        <?php $letter_id = '#' === $letter ? 'other' : sanitize_title( $letter ); ?>
        <section class="wiki-alpha-section" id="saved-<?php echo esc_attr( $letter_id ); ?>">
            <h3 class="wiki-alpha-heading"><?php echo esc_html( $letter ); ?></h3>
            <ul class="wiki-alpha-list">
                <?php foreach ( $group as $saved ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $saved['view_url'] ); ?>">
                            <span class="wiki-alpha-title"><?php echo esc_html( $saved['title'] ); ?></span>
                            <span class="wiki-meta">
                                <span title="<?php echo esc_attr( $saved['language_label'] ); ?>" aria-label="<?php echo esc_attr( $saved['language_label'] . ' (' . $saved['language'] . ')' ); ?>"><?php echo esc_html( $saved['language'] ); ?></span>
                                <?php foreach ( $saved['lists'] as $list ) : ?>
                                    <span><?php echo esc_html( $list['name'] ); ?></span>
                                <?php endforeach; ?>
                                <?php if ( ! empty( $saved['last_saved_at_display'] ) ) : ?>
                                    <span title="<?php echo esc_attr( __( 'Last saved', 'wordopedia' ) . ' ' . $saved['last_saved_at_display'] ); ?>" aria-label="<?php echo esc_attr( __( 'Last saved', 'wordopedia' ) . ' ' . $saved['last_saved_at_display'] ); ?>"><?php echo esc_html( $saved['last_saved_at_display'] ); ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
<?php else : ?>
    <div class="wiki-notice"><?php echo esc_html( $wiki_saved_alpha_empty_message ); ?></div>
<?php endif; ?>
