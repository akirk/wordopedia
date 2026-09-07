<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
    <footer class="wiki-footer">
        <?php esc_html_e( 'Wordopedia uses content from Wikipedia. Wordopedia is not endorsed by or affiliated with the Wikimedia Foundation.', 'wordopedia' ); ?>
    </footer>
</div>
</main>
<?php wp_app_body_close(); ?>
</body>
</html>
