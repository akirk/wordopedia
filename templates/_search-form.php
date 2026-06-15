<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

$wiki_search_query = isset( $wiki_search_query ) ? (string) $wiki_search_query : '';
$wiki_search_language = isset( $wiki_search_language ) ? (string) $wiki_search_language : App::get_default_language();
$wiki_search_results_target = isset( $wiki_search_results_target ) ? (string) $wiki_search_results_target : 'wiki-search-results';
$wiki_search_language_tabs = isset( $wiki_search_language_tabs ) ? (string) $wiki_search_language_tabs : 'wiki-search-language-tabs';
$wiki_search_label_id = isset( $wiki_search_label_id ) ? (string) $wiki_search_label_id : 'wordopedia-search-label';
?>
<form
    class="wiki-search wiki-wordopedia-search wiki-quicksearch"
    method="get"
    action="<?php echo esc_url( App::get_app_url() ); ?>"
    aria-labelledby="<?php echo esc_attr( $wiki_search_label_id ); ?>"
    data-wiki-quicksearch
    data-article-base="<?php echo esc_url( App::get_app_url( 'article/' ) ); ?>"
    data-results-target="<?php echo esc_attr( $wiki_search_results_target ); ?>"
    data-language-tabs="<?php echo esc_attr( $wiki_search_language_tabs ); ?>"
    data-searching-text="<?php esc_attr_e( 'Searching Wikipedia...', 'wordopedia' ); ?>"
    data-no-results-text="<?php esc_attr_e( 'No Wikipedia results found.', 'wordopedia' ); ?>"
    data-read-text="<?php esc_attr_e( 'Read', 'wordopedia' ); ?>"
    data-save-text="<?php esc_attr_e( 'Save article', 'wordopedia' ); ?>"
    data-words-text="<?php esc_attr_e( 'words', 'wordopedia' ); ?>"
    data-save-action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
    data-save-nonce="<?php echo esc_attr( wp_create_nonce( App::NONCE_SAVE_ARTICLE ) ); ?>"
    data-can-save="<?php echo esc_attr( current_user_can( 'edit_posts' ) ? '1' : '0' ); ?>"
>
    <label class="wiki-search-field">
        <span id="<?php echo esc_attr( $wiki_search_label_id ); ?>"><?php esc_html_e( 'Search Wikipedia', 'wordopedia' ); ?></span>
        <input type="search" name="q" value="<?php echo esc_attr( $wiki_search_query ); ?>" autocomplete="off" placeholder="<?php esc_attr_e( 'Search Wikipedia', 'wordopedia' ); ?>" data-wiki-quicksearch-input aria-controls="<?php echo esc_attr( $wiki_search_results_target ); ?>">
    </label>
    <input type="hidden" name="language" value="<?php echo esc_attr( $wiki_search_language ); ?>">
    <button class="wiki-btn wiki-search-submit" type="submit"><?php esc_html_e( 'Search', 'wordopedia' ); ?></button>
</form>
