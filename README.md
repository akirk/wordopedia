# Wordopedia

- Contributors: akirk
- Tags: encyclopedia, research, notes, bookmarks, wp-app
- Requires at least: 6.0
- Requires PHP: 7.4
- Tested up to: 7.1
- Stable tag: 1.0.0
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Search Wikipedia from inside WordPress, read articles in a clean app, and keep the ones you need as saved posts with snippets and lists.

## Description

Wordopedia is a logged-in WordPress app for building a personal encyclopedia from Wikipedia. It is powered by [WpApp](https://github.com/akirk/wp-app) and runs inside WordPress at `/wordopedia/`.

The app lets users search live Wikipedia, read articles in a clean app interface, save useful articles into WordPress, refetch those articles later, and collect selected passages as reusable snippets.

### What the app does

Wordopedia turns Wikipedia into a private research space inside WordPress. Users can search Wikipedia in their preferred language versions, read articles in the app, switch between available translations, and follow rewritten article links without leaving the Wordopedia interface where possible.

When an article is worth keeping, Wordopedia saves it as a local WordPress post with its Wikipedia page ID, language, source URL, thumbnail, revision, and saved-date metadata. Saved articles can be refreshed from Wikipedia later, grouped with a hierarchical Lists taxonomy, and searched from the saved-article view.

The app also supports passage-level notes. Users can highlight useful text while reading an article and save it as a snippet attached to the saved article. Snippets have their own browser with search and language filtering.

Under the hood, Wordopedia stores data with native WordPress post, post meta, taxonomy, and user meta APIs. When the WordPress Abilities API is available, it also exposes assistant-friendly operations for searching, fetching, saving, listing, refetching, and annotating Wikipedia articles.

All app routes require a logged-in WordPress user. Wordopedia is a shared library: reads require `read`; saves require `edit_posts`; updates and refetches also require `edit_post`; snippet deletes require `delete_post`.

### Data model

Wordopedia does not create custom database tables.

- Saved articles use the `wordopedia_article` custom post type.
- Saved snippets use the `wordopedia_snippet` custom post type and are attached to saved articles as child posts.
- Article lists use the `wordopedia_list` taxonomy.
- Wikipedia page IDs, language codes, source URLs, thumbnail URLs, revision IDs, saved dates, and refetch dates are stored as post meta.
- Preferred language versions are stored per user in user meta.

Saved article and snippet post types are visible in the WordPress admin and REST API, but are not public front-end post types. Wordopedia provides the front-end reading interface.

### Wikipedia workflow

1. Search Wikipedia from `/wordopedia/`.
2. Open a result to read the live article inside Wordopedia.
3. Switch languages from the preferred language tabs or the article's translation list.
4. Save the article into WordPress when it should become part of the personal encyclopedia.
5. Assign saved articles to lists from the WordPress admin when grouping is useful.
6. Highlight useful passages while reading and save them as snippets.
7. Use the saved article and saved snippet screens to find local material later.
8. Refetch a saved article when the local copy should be updated from Wikipedia.

### Assistant and abilities integration

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

### Wikimedia API usage

The app reads live Wikipedia data through the Wikimedia Action API. To avoid overloading Wikimedia services, requests are kept user-driven and cacheable:

- Search responses are cached briefly in WordPress transients.
- Article metadata and article HTML are cached longer, while explicit article refreshes bypass the cache.
- Live browser search waits before requesting, ignores one-character searches, and cancels superseded searches.
- API requests use JSON, include `origin=*` for CORS, and surface Wikimedia API errors and `Retry-After` rate-limit responses instead of hiding them behind a generic HTTP error.
- Normal WordPress installs send a descriptive `User-Agent` for server-side requests and `Api-User-Agent` for browser-side requests.

WordPress Playground runs PHP HTTP requests through a browser-backed CORS proxy. That proxy does not allow forwarding the `User-Agent` request header, so the app detects Playground with `PLAYGROUND_AUTO_LOGIN_AS_USER` and avoids sending that header there. This is a Playground transport workaround only; normal WordPress installs still identify requests according to Wikimedia API guidance.

Wordopedia is an independent plugin. It is not affiliated with, endorsed by, or sponsored by the Wikimedia Foundation. Wikipedia content is made available by the Wikimedia projects under their own licenses and terms of use.

### Development

Development of this plugin happens [on GitHub](https://github.com/akirk/wordopedia). Pull requests are welcome.

Install dependencies:

```sh
composer install
```

Run the PHP test suite:

```sh
composer test
```

The repository includes a WordPress Playground `blueprint.json` that installs the distribution branch and opens Wordopedia at `/wordopedia/`. [Try Wordopedia in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wordopedia/main/blueprint.json) · [Try it with a demo article](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wordopedia/main/demo.json)

[Try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/wordopedia/main/blueprint-openstation.json) — the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

## Installation

1. Upload the `wordopedia` directory to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Make sure pretty permalinks are enabled, then open `/wordopedia/` while logged in.

## Frequently Asked Questions

### Does this plugin create custom database tables?
No. Saved articles and snippets are custom post types, lists are a taxonomy, article details are post meta, and preferred languages are user meta. Deleting the plugin leaves your database as slim as it was before.

### Do I need an account or an API key?
No. The app reads public Wikipedia data through the Wikimedia Action API, which needs no key. You do need to be logged in to your own WordPress, because Wordopedia is a logged-in app rather than a public front end.

### Who can see the saved articles?
Wordopedia behaves like a shared library for the site's logged-in users: reading requires the `read` capability, saving requires `edit_posts`, updating and refetching an article also require `edit_post` on that article, and deleting a snippet requires `delete_post`. The post types are not public, so saved articles are not exposed as front-end posts.

### Can I keep a saved article up to date?
Yes. Every saved article records the Wikipedia revision it came from, and you can refetch it from the article screen to pull the current version into WordPress.

### What are snippets?
Snippets are passages you highlight while reading. They are saved as child posts of the saved article, keep a link back to the source, and have their own browser with search and language filtering.

### Can an AI assistant use this?
Yes, when your WordPress provides the Abilities API. Wordopedia then registers abilities for searching Wikipedia, fetching and saving articles, listing and refetching saved articles, and creating and searching snippets, plus assistant hints so answers can link back into the app and cite the Wikipedia source.

### Is this an official Wikipedia or Wikimedia plugin?
No. Wordopedia is an independent plugin and is not affiliated with, endorsed by, or sponsored by the Wikimedia Foundation.

## Screenshots

1. Reading a Wikipedia article inside the Wordopedia app, with the search field, the language switcher and the Save article button above it.
2. An article on a phone: the search field, the language switcher and the Save article button stacked above the text.

## Changelog

### 1.0.0
- Search Wikipedia across your preferred language versions and read articles inside WordPress.
- Save articles as `wordopedia_article` posts with page ID, language, source URL, thumbnail, revision and date metadata.
- Refetch saved articles to update the local copy from Wikipedia.
- Group saved articles with the hierarchical `wordopedia_list` taxonomy.
- Highlight passages while reading and store them as `wordopedia_snippet` posts with their own searchable browser.
- Register Wikipedia and snippet abilities plus assistant hints when the Abilities API is available.
