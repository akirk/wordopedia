<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

$current_language = isset( $language ) ? (string) $language : App::get_default_language();
$search_query = isset( $language_tabs_query ) ? (string) $language_tabs_query : '';
$tabs_hidden = ! empty( $language_tabs_hidden );
$preferred_languages = App::get_user_languages();
if ( ! in_array( $current_language, $preferred_languages, true ) ) {
    array_unshift( $preferred_languages, $current_language );
    $preferred_languages = App::normalize_language_list( $preferred_languages );
}
?>
<nav class="wiki-language-tabs wiki-search-language-tabs" id="wiki-search-language-tabs" data-wiki-language-tabs data-wiki-search-language-tabs aria-label="<?php esc_attr_e( 'Search languages', 'wordopedia' ); ?>" <?php echo $tabs_hidden ? 'hidden' : ''; ?>>
    <?php foreach ( $preferred_languages as $code ) : ?>
        <?php
        $url = add_query_arg(
            [
                'q'        => $search_query,
                'language' => $code,
            ],
            App::get_app_url()
        );
        ?>
        <a class="<?php echo esc_attr( $code === $current_language ? 'is-active' : '' ); ?>" href="<?php echo esc_url( $url ); ?>" data-wiki-language="<?php echo esc_attr( $code ); ?>">
            <?php echo esc_html( App::get_language_label( $code ) ); ?>
        </a>
    <?php endforeach; ?>
    <a href="<?php echo esc_url( App::get_settings_url() ); ?>"><?php esc_html_e( 'Edit', 'wordopedia' ); ?></a>
</nav>
