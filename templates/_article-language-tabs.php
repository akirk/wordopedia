<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

$tabs_article = isset( $language_tabs_article ) && is_array( $language_tabs_article )
    ? $language_tabs_article
    : ( isset( $article ) && is_array( $article ) ? $article : [] );

if ( ! $tabs_article ) {
    return;
}

$current_language = isset( $tabs_article['language'] ) ? (string) $tabs_article['language'] : App::get_default_language();
$current_language = App::normalize_language( $current_language );
if ( is_wp_error( $current_language ) ) {
    $current_language = App::get_default_language();
}

$current_label = App::get_language_label( $current_language );
$current_url = ! empty( $tabs_article['app_url'] )
    ? (string) $tabs_article['app_url']
    : App::get_article_url(
        $current_language,
        isset( $tabs_article['title'] ) ? (string) $tabs_article['title'] : '',
        isset( $tabs_article['page_id'] ) ? absint( $tabs_article['page_id'] ) : 0
    );

$preferred_languages = App::get_user_languages();
$preferred_lookup = array_fill_keys( $preferred_languages, true );
$tab_codes = $preferred_languages;
if ( ! in_array( $current_language, $tab_codes, true ) ) {
    array_unshift( $tab_codes, $current_language );
}

$available_by_language = [];
foreach ( $tabs_article['available_languages'] ?? [] as $translation ) {
    if ( ! is_array( $translation ) || empty( $translation['language'] ) || empty( $translation['app_url'] ) ) {
        continue;
    }

    $translation_language = App::normalize_language( (string) $translation['language'] );
    if ( is_wp_error( $translation_language ) || $translation_language === $current_language ) {
        continue;
    }

    $translation['language'] = $translation_language;
    $translation['language_label'] = App::get_language_label( $translation_language );

    $available_by_language[ $translation_language ] = $translation;
}

$tab_items = [];
$tab_language_lookup = [];
foreach ( $tab_codes as $code ) {
    if ( $code === $current_language ) {
        $item = [
            'language'       => $current_language,
            'language_label' => $current_label,
            'app_url'        => $current_url,
            'is_current'     => true,
            'is_known'       => isset( $preferred_lookup[ $code ] ),
        ];
    } elseif ( isset( $available_by_language[ $code ] ) ) {
        $item = $available_by_language[ $code ];
        $item['is_current'] = false;
        $item['is_known'] = true;
    } else {
        continue;
    }

    $tab_items[] = $item;
    $tab_language_lookup[ $code ] = true;
}

$dropdown_languages = [];
foreach ( $available_by_language as $code => $translation ) {
    if ( isset( $tab_language_lookup[ $code ] ) ) {
        continue;
    }

    $dropdown_languages[] = $translation;
}
usort( $dropdown_languages, function( $a, $b ) {
    return strcasecmp( $a['language_label'], $b['language_label'] );
} );

$show_actions = $dropdown_languages || ( current_user_can( 'edit_posts' ) && ! empty( $tabs_article['page_id'] ) );
?>
<nav class="wiki-language-tabs wiki-article-language-tabs" id="wiki-article-language-tabs" data-wiki-article-language-tabs aria-label="<?php esc_attr_e( 'Article languages', 'wordopedia' ); ?>">
    <?php foreach ( $tab_items as $item ) : ?>
        <?php
        $classes = [];
        if ( ! empty( $item['is_current'] ) ) {
            $classes[] = 'is-active';
        }
        if ( ! empty( $item['is_known'] ) ) {
            $classes[] = 'is-known';
        }
        ?>
        <a class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" href="<?php echo esc_url( $item['app_url'] ); ?>" data-wiki-article-language="<?php echo esc_attr( $item['language'] ); ?>" <?php echo ! empty( $item['is_current'] ) ? 'aria-current="page"' : ''; ?>>
            <?php echo esc_html( $item['language_label'] ); ?>
        </a>
    <?php endforeach; ?>
    <a href="<?php echo esc_url( App::get_settings_url() ); ?>"><?php esc_html_e( 'Edit', 'wordopedia' ); ?></a>
    <?php if ( $show_actions ) : ?>
        <span class="wiki-language-tab-actions">
            <?php if ( $dropdown_languages ) : ?>
                <select class="wiki-language-tab-select" aria-label="<?php esc_attr_e( 'Other article languages', 'wordopedia' ); ?>" onchange="if (this.value) window.location.href = this.value;">
                    <option value="" selected><?php esc_html_e( 'Other languages', 'wordopedia' ); ?></option>
                    <?php foreach ( $dropdown_languages as $translation ) : ?>
                        <option value="<?php echo esc_url( $translation['app_url'] ); ?>">
                            <?php echo esc_html( $translation['language_label'] . ' (' . $translation['language'] . ')' ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <?php if ( current_user_can( 'edit_posts' ) && ! empty( $tabs_article['page_id'] ) ) : ?>
                <form class="wiki-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( App::NONCE_SAVE_ARTICLE ); ?>
                    <input type="hidden" name="action" value="wordopedia_save_article">
                    <input type="hidden" name="page_id" value="<?php echo esc_attr( $tabs_article['page_id'] ); ?>">
                    <input type="hidden" name="title" value="<?php echo esc_attr( $tabs_article['title'] ?? '' ); ?>">
                    <input type="hidden" name="language" value="<?php echo esc_attr( $current_language ); ?>">
                    <button class="wiki-btn" type="submit"><?php esc_html_e( 'Save article', 'wordopedia' ); ?></button>
                </form>
            <?php endif; ?>
        </span>
    <?php endif; ?>
</nav>
