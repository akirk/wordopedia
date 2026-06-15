<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Akirk\Wordopedia\App;

$wiki_saved_search = isset( $wiki_saved_search ) ? (string) $wiki_saved_search : '';
$wiki_saved_search_action = isset( $wiki_saved_search_action ) ? (string) $wiki_saved_search_action : App::get_saved_articles_url();
$wiki_saved_search_target = isset( $wiki_saved_search_target ) ? (string) $wiki_saved_search_target : 'wiki-saved-list';
$wiki_saved_search_label_id = isset( $wiki_saved_search_label_id ) ? (string) $wiki_saved_search_label_id : 'wordopedia-saved-search-label';
?>
<form class="wiki-search wiki-compact-search" method="get" action="<?php echo esc_url( $wiki_saved_search_action ); ?>" aria-labelledby="<?php echo esc_attr( $wiki_saved_search_label_id ); ?>">
    <label class="wiki-search-field">
        <span id="<?php echo esc_attr( $wiki_saved_search_label_id ); ?>"><?php esc_html_e( 'Search saved articles', 'wordopedia' ); ?></span>
        <input type="search" name="s" value="<?php echo esc_attr( $wiki_saved_search ); ?>" autocomplete="off" placeholder="<?php esc_attr_e( 'Search saved articles', 'wordopedia' ); ?>" aria-controls="<?php echo esc_attr( $wiki_saved_search_target ); ?>">
    </label>
    <button class="wiki-btn wiki-search-submit" type="submit"><?php esc_html_e( 'Search', 'wordopedia' ); ?></button>
</form>
