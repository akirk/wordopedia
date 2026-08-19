# Wordopedia

Wordopedia is a logged-in WordPress app for building a personal encyclopedia from Wikipedia. It is powered by [WpApp](https://github.com/akirk/wp-app) and runs inside WordPress at `/wordopedia/`.

The app lets users search live Wikipedia, read articles in a clean app interface, save useful articles into WordPress, refetch those articles later, and collect selected passages as reusable snippets.

## What the app does

Wordopedia turns Wikipedia into a private research space inside WordPress. Users can search Wikipedia in their preferred language versions, read articles in the app, switch between available translations, and follow rewritten article links without leaving the Wordopedia interface where possible.

When an article is worth keeping, Wordopedia saves it as a local WordPress post with its Wikipedia page ID, language, source URL, thumbnail, revision, and saved-date metadata. Saved articles can be refreshed from Wikipedia later, grouped with a hierarchical Lists taxonomy, and searched from the saved-article view.

The app also supports passage-level notes. Users can highlight useful text while reading an article and save it as a snippet attached to the saved article. Snippets have their own browser with search and language filtering.

Under the hood, Wordopedia stores data with native WordPress post, post meta, taxonomy, and user meta APIs. When the WordPress Abilities API is available, it also exposes assistant-friendly operations for searching, fetching, saving, listing, refetching, and annotating Wikipedia articles.

All app routes require a logged-in WordPress user. Wordopedia is a shared library: reads require `read`; saves require `edit_posts`; updates and refetches also require `edit_post`; snippet deletes require `delete_post`.

## Data model

Wordopedia does not create custom database tables.

- Saved articles use the `wordopedia_article` custom post type.
- Saved snippets use the `wordopedia_snippet` custom post type and are attached to saved articles as child posts.
- Article lists use the `wordopedia_list` taxonomy.
- Wikipedia page IDs, language codes, source URLs, thumbnail URLs, revision IDs, saved dates, and refetch dates are stored as post meta.
- Preferred language versions are stored per user in user meta.

Saved article and snippet post types are visible in the WordPress admin and REST API, but are not public front-end post types. Wordopedia provides the front-end reading interface.

## Wikipedia workflow

1. Search Wikipedia from `/wordopedia/`.
2. Open a result to read the live article inside Wordopedia.
3. Switch languages from the preferred language tabs or the article's translation list.
4. Save the article into WordPress when it should become part of the personal encyclopedia.
5. Assign saved articles to lists from the WordPress admin when grouping is useful.
6. Highlight useful passages while reading and save them as snippets.
7. Use the saved article and saved snippet screens to find local material later.
8. Refetch a saved article when the local copy should be updated from Wikipedia.

## Assistant and abilities integration

When WordPress provides the Abilities API, Wordopedia registers abilities under the `wordopedia` category:

- `wordopedia/search-wikipedia`
- `wordopedia/get-article`
- `wordopedia/list-article-media`
- `wordopedia/get-media-file`
- `wordopedia/save-article`
- `wordopedia/list-saved-articles`
- `wordopedia/get-saved-article`
- `wordopedia/refetch-saved-article`
- `wordopedia/save-snippet`
- `wordopedia/get-snippet`
- `wordopedia/search-snippets`

The plugin also adds AI assistant domain, instruction, and welcome-tip hints so assistant responses can link back to Wordopedia app URLs, cite Wikipedia source URLs where appropriate, find article media such as SVG files with Wikimedia Commons attribution metadata, and suggest useful Wordopedia tasks from the app screens.

## Requirements

- WordPress with pretty permalinks enabled.
- PHP 7.4 or newer.
- Composer dependencies installed, including `akirk/wp-app`.
- Logged-in WordPress users for app access.

## Development

Install dependencies:

```sh
composer install
```

Run the PHP test suite:

```sh
composer test
```

The repository includes a WordPress Playground `blueprint.json` that installs the distribution branch and opens Wordopedia at `/wordopedia/`. [Try Wordopedia in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wordopedia/main/blueprint.json) · [Try it with a demo article](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wordopedia/main/demo.json)

## Wikimedia API usage

The app reads live Wikipedia data through the Wikimedia Action API. To avoid overloading Wikimedia services, requests are kept user-driven and cacheable:

- Search responses are cached briefly in WordPress transients.
- Article metadata and article HTML are cached longer, while explicit article refreshes bypass the cache.
- Live browser search waits before requesting, ignores one-character searches, and cancels superseded searches.
- API requests use JSON, include `origin=*` for CORS, and surface Wikimedia API errors and `Retry-After` rate-limit responses instead of hiding them behind a generic HTTP error.
- Normal WordPress installs send a descriptive `User-Agent` for server-side requests and `Api-User-Agent` for browser-side requests.

WordPress Playground runs PHP HTTP requests through a browser-backed CORS proxy. That proxy does not allow forwarding the `User-Agent` request header, so the app detects Playground with `PLAYGROUND_AUTO_LOGIN_AS_USER` and avoids sending that header there. This is a Playground transport workaround only; normal WordPress installs still identify requests according to Wikimedia API guidance.

## License

GPL-2.0-or-later.
