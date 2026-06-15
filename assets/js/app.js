(function () {
    var appConfig = window.wordopediaAppConfig || {};
    var wordopediaApiUserAgent = appConfig.apiUserAgent || '';
    var isWordPressPlayground = !!appConfig.isPlayground;

    function wordopediaFetchOptions(options) {
        options = options || {};
        if (!isWordPressPlayground && wordopediaApiUserAgent) {
            options.headers = options.headers || {};
            options.headers['Api-User-Agent'] = wordopediaApiUserAgent;
        }

        return options;
    }

    document.querySelectorAll('[data-wiki-nav-menu-root]').forEach(function (root) {
        var toggle = root.querySelector('[data-wiki-nav-menu-toggle]');
        var panel = root.querySelector('[data-wiki-nav-menu-panel]');
        var menu = root.querySelector('.wiki-nav-menu');

        if (!toggle || !panel || !menu) {
            return;
        }

        function setOpen(open) {
            panel.hidden = !open;
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            setOpen(panel.hidden);
        });

        document.addEventListener('click', function (event) {
            if (panel.hidden || root.contains(event.target)) {
                return;
            }

            setOpen(false);
        });

        document.addEventListener('keydown', function (event) {
            if ('Escape' !== event.key || panel.hidden) {
                return;
            }

            setOpen(false);
            toggle.focus();
        });
    });

    function languageDisplayName(language) {
        var name = language.localname || language.label || language.name || language.code || '';
        var autonym = language.name || language.autonym || '';
        var label = autonym && autonym !== name ? name + ' - ' + autonym : name;
        return label + ' (' + language.code + ')';
    }

    function createLanguageMoveButton(list, direction) {
        var button = document.createElement('button');
        var text = list.getAttribute('data-move-' + direction + '-text') || ('up' === direction ? 'Move up' : 'Move down');
        button.className = 'wiki-btn secondary wiki-icon-btn';
        button.type = 'button';
        button.setAttribute('data-wiki-language-move', direction);
        button.setAttribute('aria-label', text);
        button.title = text;
        button.innerHTML = 'up' === direction ? '&uarr;' : '&darr;';
        return button;
    }

    function createLanguageItem(list, language) {
        var item = document.createElement('li');
        var hidden = document.createElement('input');
        var name = document.createElement('span');
        var remove = document.createElement('button');
        var removeText = list.getAttribute('data-remove-text') || 'Remove';

        item.setAttribute('data-wiki-language-item', '');
        item.setAttribute('data-language-code', language.code);

        hidden.type = 'hidden';
        hidden.name = 'languages[]';
        hidden.value = language.code;
        item.appendChild(hidden);

        name.className = 'wiki-language-name';
        name.textContent = languageDisplayName(language);
        item.appendChild(name);

        item.appendChild(createLanguageMoveButton(list, 'up'));
        item.appendChild(createLanguageMoveButton(list, 'down'));

        remove.className = 'wiki-btn secondary wiki-icon-btn';
        remove.type = 'button';
        remove.setAttribute('data-wiki-language-remove', '');
        remove.setAttribute('aria-label', removeText + ' ' + languageDisplayName(language));
        remove.title = removeText;
        remove.innerHTML = '&times;';
        item.appendChild(remove);

        return item;
    }

    function refreshLanguageButtons(list) {
        var items = list.querySelectorAll('[data-wiki-language-item]');
        items.forEach(function (item, index) {
            var up = item.querySelector('[data-wiki-language-move="up"]');
            var down = item.querySelector('[data-wiki-language-move="down"]');
            if (up) {
                up.disabled = 0 === index;
            }
            if (down) {
                down.disabled = index === items.length - 1;
            }
        });
    }

    document.querySelectorAll('[data-wiki-language-order]').forEach(function (list) {
        list.addEventListener('click', function (event) {
            var move = event.target.closest ? event.target.closest('[data-wiki-language-move]') : null;
            var remove = event.target.closest ? event.target.closest('[data-wiki-language-remove]') : null;
            var button = move || remove;
            var item = button ? button.closest('[data-wiki-language-item]') : null;
            var direction = move ? move.getAttribute('data-wiki-language-move') : '';

            if (!button || !item || !list.contains(item)) {
                return;
            }

            if (remove) {
                item.remove();
                refreshLanguageButtons(list);
                return;
            }

            if ('up' === direction && item.previousElementSibling) {
                list.insertBefore(item, item.previousElementSibling);
            } else if ('down' === direction && item.nextElementSibling) {
                list.insertBefore(item.nextElementSibling, item);
            }

            refreshLanguageButtons(list);
            button.focus();
        });

        refreshLanguageButtons(list);
    });

    document.querySelectorAll('[data-wiki-language-picker]').forEach(function (picker) {
        var input = picker.querySelector('[data-wiki-language-search]');
        var results = picker.querySelector('[data-wiki-language-results]');
        var list = document.getElementById(picker.getAttribute('data-language-list') || '');
        var languages = null;
        var loading = false;

        if (!input || !results || !list || !window.fetch || !window.URLSearchParams) {
            return;
        }

        function selectedCodes() {
            return Array.prototype.map.call(list.querySelectorAll('[data-language-code]'), function (item) {
                return item.getAttribute('data-language-code');
            });
        }

        function renderLanguageResults(query) {
            var lowerQuery = query.toLowerCase();
            var selected = selectedCodes();
            var matches = (languages || []).filter(function (language) {
                var text = (language.code + ' ' + (language.localname || '') + ' ' + (language.name || '')).toLowerCase();
                return selected.indexOf(language.code) === -1 && text.indexOf(lowerQuery) !== -1;
            }).slice(0, 8);

            results.textContent = '';
            if (!query) {
                results.hidden = true;
                return;
            }

            if (!matches.length) {
                var empty = document.createElement('div');
                empty.className = 'wiki-language-result';
                empty.textContent = picker.getAttribute('data-empty-text') || 'No languages found.';
                results.appendChild(empty);
                results.hidden = false;
                return;
            }

            matches.forEach(function (language) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'wiki-language-result';
                button.textContent = languageDisplayName(language);
                button.addEventListener('click', function () {
                    list.appendChild(createLanguageItem(list, language));
                    refreshLanguageButtons(list);
                    input.value = '';
                    results.hidden = true;
                    input.focus();
                });
                results.appendChild(button);
            });
            results.hidden = false;
        }

        function loadLanguages(callback) {
            if (languages) {
                callback();
                return;
            }

            if (loading) {
                return;
            }

            loading = true;
            results.textContent = picker.getAttribute('data-loading-text') || 'Loading languages...';
            results.hidden = false;

            var params = new URLSearchParams({
                action: 'sitematrix',
                smtype: 'language|special',
                smlangprop: 'code|name|localname|site',
                smsiteprop: 'url|dbname|code|sitename',
                formatversion: '2',
                format: 'json',
                origin: '*'
            });

            fetch('https://en.wikipedia.org/w/api.php?' + params.toString(), wordopediaFetchOptions({ credentials: 'omit' }))
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (data) {
                    languages = [];
                    if (data && data.sitematrix) {
                        Object.keys(data.sitematrix).forEach(function (key) {
                            var language = data.sitematrix[key];
                            if ('specials' === key && Array.isArray(language)) {
                                language.forEach(function (site) {
                                    if (site && 'simple' === site.code && site.dbname === 'simplewiki' && site.url === 'https://simple.wikipedia.org') {
                                        languages.push({
                                            code: 'simple',
                                            localname: 'Simple English',
                                            name: 'Simple English'
                                        });
                                    }
                                });
                                return;
                            }

                            var sites = language && Array.isArray(language.site) ? language.site : [];
                            var code = language && language.code ? language.code : '';
                            var hasWikipedia = sites.some(function (site) {
                                if (!site) {
                                    return false;
                                }

                                var isWikipediaProject = 'wiki' === site.code || site.dbname === code + 'wiki';
                                return isWikipediaProject && !('closed' in site) && !('private' in site) && !('fishbowl' in site) && site.url === 'https://' + code + '.wikipedia.org';
                            });

                            if (hasWikipedia) {
                                languages.push(language);
                            }
                        });
                    }
                    languages.sort(function (a, b) {
                        return languageDisplayName(a).localeCompare(languageDisplayName(b));
                    });
                    loading = false;
                    callback();
                })
                .catch(function () {
                    languages = [];
                    loading = false;
                    callback();
                });
        }

        input.addEventListener('input', function () {
            var query = input.value.trim();
            loadLanguages(function () {
                renderLanguageResults(query);
            });
        });
        input.addEventListener('focus', function () {
            if (input.value.trim()) {
                input.dispatchEvent(new Event('input'));
            }
        });
    });

    function appendSnippetHidden(parent, name, value) {
        var field = document.createElement('input');
        field.type = 'hidden';
        field.name = name;
        field.value = value || '';
        parent.appendChild(field);
        return field;
    }

    function requestSnippet(form) {
        var ajaxUrl = form.getAttribute('data-wiki-ajax-url') || '';
        if (!ajaxUrl || !window.fetch || !window.FormData) {
            return null;
        }

        return fetch(ajaxUrl, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json'
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                if (!response.ok || !data || !data.success) {
                    var message = data && data.data && data.data.message ? data.data.message : 'Could not save snippet.';
                    throw new Error(message);
                }

                return data.data || {};
            });
        });
    }

    function snippetStatusText(snippet) {
        return snippet && snippet.updated_at_display ? 'Updated ' + snippet.updated_at_display : 'Saved snippet';
    }

    function renderSnippetHtml(snippet) {
        return snippet && snippet.html ? snippet.html : (snippet && snippet.content ? snippet.content : '');
    }

    function updateSnippetCount(delta) {
        var section = document.querySelector('[data-wiki-snippets]');
        if (!section) {
            return;
        }

        var current = parseInt(section.getAttribute('data-snippet-count') || '0', 10);
        var count = Math.max(0, current + delta);
        var label = section.querySelector('[data-wiki-snippet-count]');
        var singular = section.getAttribute('data-count-singular') || 'snippet';
        var plural = section.getAttribute('data-count-plural') || 'snippets';

        section.setAttribute('data-snippet-count', String(count));
        section.hidden = 0 === count;

        if (label) {
            label.textContent = count + ' ' + (1 === count ? singular : plural);
        }
    }

    function createSnippetItem(snippet, sourceForm) {
        var item = document.createElement('li');
        var content = document.createElement('div');
        var tools = document.createElement('div');
        var status = document.createElement('span');
        var buttons = document.createElement('div');
        var adminPostUrl = sourceForm.getAttribute('action') || '';
        var ajaxUrl = sourceForm.getAttribute('data-wiki-ajax-url') || '';
        var postId = snippet && snippet.post_id ? String(snippet.post_id) : '';

        item.className = 'wiki-snippet';
        item.id = 'wiki-snippet-' + postId;

        content.className = 'wiki-snippet-content';
        content.setAttribute('data-wiki-snippet-content', '');
        content.innerHTML = renderSnippetHtml(snippet);
        item.appendChild(content);

        tools.className = 'wiki-snippet-tools';
        status.className = 'wiki-meta';
        status.setAttribute('data-wiki-snippet-status', '');
        status.textContent = snippetStatusText(snippet);
        tools.appendChild(status);

        buttons.className = 'wiki-snippet-buttons';

        if (snippet && snippet.edit_url) {
            var openEditor = document.createElement('a');
            openEditor.className = 'wiki-btn secondary';
            openEditor.href = snippet.edit_url;
            openEditor.textContent = 'Edit snippet';
            buttons.appendChild(openEditor);
        }

        var deleteForm = document.createElement('form');
        deleteForm.className = 'wiki-inline-form wiki-snippet-delete';
        deleteForm.method = 'post';
        deleteForm.action = adminPostUrl;
        deleteForm.setAttribute('data-wiki-snippet-delete', '');
        deleteForm.setAttribute('data-wiki-ajax-url', ajaxUrl);
        appendSnippetHidden(deleteForm, 'action', 'wordopedia_delete_snippet');
        appendSnippetHidden(deleteForm, '_wpnonce', snippet && snippet.delete_nonce ? snippet.delete_nonce : '');
        appendSnippetHidden(deleteForm, 'post_id', postId);
        var deleteButton = document.createElement('button');
        deleteButton.className = 'wiki-btn secondary';
        deleteButton.type = 'submit';
        deleteButton.textContent = 'Delete';
        deleteForm.appendChild(deleteButton);
        buttons.appendChild(deleteForm);

        tools.appendChild(buttons);
        item.appendChild(tools);

        return item;
    }

    function insertSnippet(snippet, sourceForm) {
        var section = document.querySelector('[data-wiki-snippets]');
        var list = section ? section.querySelector('[data-wiki-snippet-list]') : null;
        if (!section || !list || !snippet || !snippet.post_id) {
            return false;
        }

        list.insertBefore(createSnippetItem(snippet, sourceForm), list.firstChild);
        updateSnippetCount(1);
        return true;
    }

    document.addEventListener('submit', function (event) {
        var deleteForm = event.target.closest ? event.target.closest('[data-wiki-snippet-delete]') : null;
        var form = deleteForm;
        var request = form ? requestSnippet(form) : null;

        if (!form || !request) {
            return;
        }

        event.preventDefault();

        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
        }

        request.then(function (data) {
            var snippet = data.snippet || {};
            var item = form.closest('.wiki-snippet');

            if (item) {
                item.remove();
                updateSnippetCount(-1);
            }
        }).catch(function (error) {
            var item = form.closest('.wiki-snippet');
            var status = item ? item.querySelector('[data-wiki-snippet-status]') : null;
            if (status) {
                status.textContent = error.message || 'Could not save snippet.';
            }
        }).finally(function () {
            if (button) {
                button.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-wiki-snippet-form]').forEach(function (form) {
        var article = document.querySelector('[data-wiki-article-snippets]');
        var textInput = form.querySelector('[data-wiki-snippet-text]');
        var htmlInput = form.querySelector('[data-wiki-snippet-html]');
        var cancel = form.querySelector('[data-wiki-snippet-cancel]');
        var submit = form.querySelector('button[type="submit"]');
        var locked = false;

        if (!article || !textInput || !window.getSelection) {
            return;
        }

        function compactText(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function selectionHtml(range) {
            var fragment;
            var container;
            var wrappers;

            if (!range || !range.cloneContents) {
                return '';
            }

            fragment = range.cloneContents();
            container = document.createElement('div');
            container.appendChild(fragment);
            wrappers = selectionInlineWrappers(range);

            return wrappers.reduce(function (html, wrapper) {
                var inner = document.createElement('div');
                var clone = wrapper.cloneNode(false);
                clone.innerHTML = html;
                inner.appendChild(clone);
                return inner.innerHTML;
            }, container.innerHTML);
        }

        function selectionInlineWrappers(range) {
            var wrappers = [];
            var node = range.startContainer;
            var inlineTags = {
                A: true,
                B: true,
                CODE: true,
                DEL: true,
                EM: true,
                I: true,
                INS: true,
                MARK: true,
                S: true,
                SMALL: true,
                STRONG: true,
                SUB: true,
                SUP: true
            };

            if (node && 1 !== node.nodeType) {
                node = node.parentNode;
            }

            while (node && node !== article) {
                if (1 === node.nodeType && inlineTags[node.tagName] && node.contains(range.endContainer)) {
                    wrappers.push(node);
                }
                node = node.parentNode;
            }

            return wrappers;
        }

        function selectionRange() {
            var selection = window.getSelection();
            if (!selection || selection.isCollapsed || !selection.rangeCount) {
                return null;
            }

            var range = selection.getRangeAt(0);
            var container = range.commonAncestorContainer;
            if (container && 1 !== container.nodeType) {
                container = container.parentNode;
            }

            if (!container || !article.contains(container)) {
                return null;
            }

            return range;
        }

        function hideForm(clearSelection) {
            form.hidden = true;
            textInput.value = '';
            if (htmlInput) {
                htmlInput.value = '';
            }

            if (clearSelection) {
                var selection = window.getSelection();
                if (selection && selection.removeAllRanges) {
                    selection.removeAllRanges();
                }
            }
        }

        function positionForm(range) {
            var rect = range.getBoundingClientRect();
            if (!rect) {
                return;
            }

            form.hidden = false;
            var width = form.offsetWidth || 320;
            var left = rect.left + (rect.width / 2);
            left = Math.max(12 + (width / 2), Math.min(window.innerWidth - 12 - (width / 2), left));
            var top = Math.min(window.innerHeight - 12 - (form.offsetHeight || 120), rect.bottom + 10);

            form.style.left = left + 'px';
            form.style.top = Math.max(12, top) + 'px';
        }

        function updateSelection() {
            if (locked) {
                return;
            }

            var range = selectionRange();
            var text = range ? compactText(window.getSelection().toString()).slice(0, 20000) : '';
            var html = range ? selectionHtml(range).slice(0, 50000) : '';

            if (!text || text.length < 2) {
                hideForm(false);
                return;
            }

            textInput.value = text;
            if (htmlInput) {
                htmlInput.value = html;
            }
            positionForm(range);
        }

        form.addEventListener('mousedown', function () {
            locked = true;
        });

        form.addEventListener('mouseup', function () {
            window.setTimeout(function () {
                locked = false;
            }, 120);
        });

        form.addEventListener('submit', function (event) {
            if (!textInput.value.trim()) {
                event.preventDefault();
                hideForm(false);
                return;
            }

            var request = requestSnippet(form);
            if (!request) {
                return;
            }

            event.preventDefault();
            if (submit) {
                submit.disabled = true;
                submit.textContent = form.getAttribute('data-saving-text') || 'Saving...';
            }

            request.then(function (data) {
                var inserted = insertSnippet(data.snippet || {}, form);
                if (submit) {
                    submit.textContent = form.getAttribute('data-saved-text') || 'Snippet saved.';
                }
                if (inserted) {
                    hideForm(true);
                } else {
                    window.setTimeout(function () {
                        hideForm(true);
                    }, 900);
                }
            }).catch(function (error) {
                if (submit) {
                    submit.textContent = error.message || form.getAttribute('data-error-text') || 'Could not save snippet.';
                }
            }).finally(function () {
                window.setTimeout(function () {
                    if (submit) {
                        submit.disabled = false;
                        submit.textContent = 'Save snippet';
                    }
                }, 900);
            });
        });

        if (cancel) {
            cancel.addEventListener('click', function () {
                locked = false;
                hideForm(true);
            });
        }

        article.addEventListener('mousedown', function () {
            if (!locked) {
                hideForm(false);
            }
        });
        article.addEventListener('mouseup', function () {
            window.setTimeout(updateSelection, 0);
        });
    });

    document.querySelectorAll('[data-wiki-image-download-open]').forEach(function (button) {
        var dialog = null;

        function dataText(name, fallback) {
            return button.getAttribute(name) || fallback;
        }

        function normalizeImageUrl(value) {
            value = String(value || '').trim();
            if (0 === value.indexOf('//')) {
                value = 'https:' + value;
            }

            try {
                var url = new URL(value, window.location.href);
                return /^https?:$/.test(url.protocol) ? url.href : '';
            } catch (error) {
                return '';
            }
        }

        function imageHost(url) {
            try {
                return new URL(url).host;
            } catch (error) {
                return '';
            }
        }

        function imageFilename(url) {
            try {
                var path = new URL(url).pathname;
                var name = path ? path.split('/').pop() : '';
                return name ? decodeURIComponent(name) : '';
            } catch (error) {
                return '';
            }
        }

        function isLocalUrl(url) {
            return imageHost(url) === window.location.host;
        }

        function isVisibleArticleImage(image) {
            var node = image;
            if (!image || !image.isConnected || image.closest('[hidden], [aria-hidden="true"], .mw-editsection, .noprint, .metadata, .ambox')) {
                return false;
            }

            while (node && 1 === node.nodeType) {
                var style = window.getComputedStyle ? window.getComputedStyle(node) : null;
                if (style && ('none' === style.display || 'hidden' === style.visibility)) {
                    return false;
                }
                node = node.parentElement;
            }

            return true;
        }

        function collectArticleImages() {
            var article = document.querySelector('.wiki-article');
            var seen = {};
            var fallbackLabel = dataText('data-article-image-text', 'Article image');
            var images = [];

            if (!article) {
                return images;
            }

            article.querySelectorAll('img').forEach(function (image) {
                var url = normalizeImageUrl(image.getAttribute('src') || image.src || image.currentSrc);
                var label;

                if (!url || seen[url] || !isVisibleArticleImage(image)) {
                    return;
                }

                seen[url] = true;
                label = image.getAttribute('alt') || image.getAttribute('title') || imageFilename(url) || fallbackLabel;
                images.push({
                    url: url,
                    label: label,
                    host: imageHost(url),
                    isLocal: isLocalUrl(url)
                });
            });

            return images;
        }

        function formatImageCount(count) {
            return dataText('data-remote-images-label', 'Remote images') + ': ' + String(count);
        }

        function hiddenInput(name, value) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value || '';
            return input;
        }

        function closeDialog() {
            if (!dialog) {
                return;
            }

            if (dialog.close && dialog.open) {
                dialog.close();
            }
            dialog.hidden = true;
            dialog.classList.remove('is-open');
        }

        function openDialog() {
            if (!dialog) {
                return;
            }

            dialog.hidden = false;
            if (dialog.showModal) {
                dialog.showModal();
            } else {
                dialog.classList.add('is-open');
            }
        }

        function updateSubmit(form, submit) {
            if (submit) {
                submit.disabled = !form.querySelector('input[name="image_urls[]"]:not(:disabled):checked');
            }
        }

        function createButton(className, text, type) {
            var element = document.createElement('button');
            element.className = className;
            element.type = type || 'button';
            element.textContent = text;
            return element;
        }

        function createDialog(images) {
            var remoteCount = images.filter(function (image) {
                return !image.isLocal;
            }).length;
            var titleId = 'wiki-image-download-title';
            var nextDialog = document.createElement('dialog');
            var form = document.createElement('form');
            var head = document.createElement('div');
            var titleGroup = document.createElement('div');
            var title = document.createElement('h2');
            var info = document.createElement('p');
            var close = createButton('wiki-btn secondary wiki-icon-btn', '\u00d7');
            var toolbar = document.createElement('div');
            var count = document.createElement('span');
            var tools = document.createElement('div');
            var selectAll = createButton('wiki-btn secondary', dataText('data-select-all-text', 'Select all'));
            var selectNone = createButton('wiki-btn secondary', dataText('data-select-none-text', 'Select none'));
            var list = document.createElement('ol');
            var actions = document.createElement('div');
            var cancel = createButton('wiki-btn secondary', dataText('data-cancel-text', 'Cancel'));
            var submit = createButton('wiki-btn', dataText('data-download-selected-text', 'Download selected'), 'submit');

            nextDialog.className = 'wiki-image-dialog';
            nextDialog.id = 'wiki-image-download-dialog';
            nextDialog.hidden = true;
            nextDialog.setAttribute('aria-labelledby', titleId);

            form.className = 'wiki-image-form';
            form.method = 'post';
            form.action = button.getAttribute('data-action-url') || '';
            form.setAttribute('data-wiki-image-form', '');
            form.appendChild(hiddenInput('action', button.getAttribute('data-action-name') || 'wordopedia_sideload_article_images'));
            form.appendChild(hiddenInput('_wpnonce', button.getAttribute('data-nonce') || ''));
            form.appendChild(hiddenInput('post_id', button.getAttribute('data-post-id') || ''));

            head.className = 'wiki-dialog-head';
            titleGroup.className = 'wiki-dialog-title';
            title.id = titleId;
            title.textContent = dataText('data-title', 'Download images');
            info.className = 'wiki-image-info';
            info.textContent = dataText('data-info-text', 'After downloading, selected images will be loaded from your media library.');
            close.setAttribute('aria-label', dataText('data-close-text', 'Close'));
            close.innerHTML = '&times;';
            close.addEventListener('click', closeDialog);
            titleGroup.appendChild(title);
            titleGroup.appendChild(info);
            head.appendChild(titleGroup);
            head.appendChild(close);
            form.appendChild(head);

            toolbar.className = 'wiki-image-toolbar';
            count.className = 'wiki-meta';
            count.textContent = formatImageCount(remoteCount);
            tools.className = 'wiki-image-selection-tools';
            selectAll.addEventListener('click', function () {
                form.querySelectorAll('input[name="image_urls[]"]:not(:disabled)').forEach(function (checkbox) {
                    checkbox.checked = true;
                });
                updateSubmit(form, submit);
            });
            selectNone.addEventListener('click', function () {
                form.querySelectorAll('input[name="image_urls[]"]:not(:disabled)').forEach(function (checkbox) {
                    checkbox.checked = false;
                });
                updateSubmit(form, submit);
            });
            tools.appendChild(selectAll);
            tools.appendChild(selectNone);
            toolbar.appendChild(count);
            toolbar.appendChild(tools);
            form.appendChild(toolbar);

            list.className = 'wiki-image-list';
            if (!images.length) {
                var empty = document.createElement('li');
                var notice = document.createElement('div');
                notice.className = 'wiki-notice';
                notice.textContent = dataText('data-no-images-text', 'No article images found.');
                empty.appendChild(notice);
                list.appendChild(empty);
            }

            images.forEach(function (image, index) {
                var item = document.createElement('li');
                var label = document.createElement('label');
                var checkbox = document.createElement('input');
                var thumb = document.createElement('span');
                var preview = document.createElement('img');
                var details = document.createElement('span');
                var titleText = document.createElement('span');
                var meta = document.createElement('span');
                var inputId = 'wiki-image-choice-' + index;

                label.className = 'wiki-image-choice' + (image.isLocal ? ' is-local' : '');
                label.setAttribute('for', inputId);
                checkbox.id = inputId;
                checkbox.type = 'checkbox';
                checkbox.name = 'image_urls[]';
                checkbox.value = image.url;
                checkbox.disabled = image.isLocal;
                checkbox.checked = !image.isLocal;
                thumb.className = 'wiki-image-thumb';
                preview.src = image.url;
                preview.alt = '';
                preview.loading = 'lazy';
                details.className = 'wiki-image-details';
                titleText.className = 'wiki-image-title';
                titleText.textContent = image.label;
                meta.className = 'wiki-meta';
                meta.textContent = image.isLocal ? dataText('data-already-local-text', 'Already local') : image.host;

                thumb.appendChild(preview);
                details.appendChild(titleText);
                details.appendChild(meta);
                label.appendChild(checkbox);
                label.appendChild(thumb);
                label.appendChild(details);
                item.appendChild(label);
                list.appendChild(item);
            });
            form.appendChild(list);

            actions.className = 'wiki-dialog-actions';
            cancel.addEventListener('click', closeDialog);
            actions.appendChild(cancel);
            actions.appendChild(submit);
            form.appendChild(actions);
            form.addEventListener('change', function (event) {
                if (event.target && 'image_urls[]' === event.target.name) {
                    updateSubmit(form, submit);
                }
            });
            updateSubmit(form, submit);

            nextDialog.appendChild(form);
            nextDialog.addEventListener('cancel', function () {
                window.setTimeout(function () {
                    nextDialog.hidden = true;
                }, 0);
            });
            nextDialog.addEventListener('close', function () {
                nextDialog.hidden = true;
            });
            nextDialog.addEventListener('click', function (event) {
                if (event.target === nextDialog) {
                    closeDialog();
                }
            });

            return nextDialog;
        }

        button.addEventListener('click', function () {
            if (dialog && dialog.parentNode) {
                dialog.parentNode.removeChild(dialog);
            }

            dialog = createDialog(collectArticleImages());
            document.body.appendChild(dialog);
            openDialog();
        });

        document.addEventListener('keydown', function (event) {
            if ('Escape' === event.key && dialog && !dialog.hidden && !dialog.open) {
                closeDialog();
            }
        });
    });

    if (!window.fetch || !window.URLSearchParams) {
        return;
    }

    document.querySelectorAll('[data-wiki-quicksearch]').forEach(function (form) {
        var input = form.querySelector('[data-wiki-quicksearch-input]');
        var language = form.querySelector('input[name="language"]');
        var targetId = form.getAttribute('data-results-target') || '';
        var tabsId = form.getAttribute('data-language-tabs') || '';
        var target = targetId ? document.getElementById(targetId) : document.querySelector('[data-wiki-quicksearch-results]');
        var tabs = tabsId ? document.getElementById(tabsId) : document.querySelector('[data-wiki-search-language-tabs]');
        if (tabs && !tabs.hasAttribute('data-wiki-search-language-tabs')) {
            tabs = null;
        }
        var articleBase = form.getAttribute('data-article-base') || '';
        var controller = null;
        var timer = null;

        if (!input || !language || !target || !articleBase) {
            return;
        }

        if (articleBase.slice(-1) !== '/') {
            articleBase += '/';
        }

        function dataText(name, fallback) {
            return form.getAttribute(name) || fallback;
        }

        function currentLanguage() {
            var value = (language.value || 'en').toLowerCase();
            return /^[a-z][a-z0-9-]{1,15}$/.test(value) ? value : 'en';
        }

        function currentLanguageLabel() {
            var active = tabs ? tabs.querySelector('[data-wiki-language].is-active') : null;
            var label = active ? active.textContent.trim() : '';
            return label || currentLanguage().toUpperCase();
        }

        function articleUrl(title) {
            return articleBase + encodeURIComponent(currentLanguage()) + '?title=' + encodeURIComponent(title);
        }

        function appendHidden(parent, name, value) {
            var field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            field.value = value;
            parent.appendChild(field);
        }

        function plainText(html) {
            var element = document.createElement('div');
            element.innerHTML = String(html || '');
            return (element.textContent || element.innerText || '').replace(/\s+/g, ' ').trim();
        }

        function createNotice(message, className) {
            var notice = document.createElement('div');
            notice.className = 'wiki-notice' + (className ? ' ' + className : '');
            notice.textContent = message;
            return notice;
        }

        function resetResultsRegion() {
            var titleId = target.getAttribute('aria-labelledby') || '';
            var title = titleId ? document.getElementById(titleId) : null;

            target.textContent = '';
            if (title) {
                target.appendChild(title);
            }
        }

        function setNotice(message, className) {
            resetResultsRegion();
            target.appendChild(createNotice(message, className));
        }

        function updateAddress(query) {
            if (!window.history || !window.history.replaceState) {
                return;
            }

            var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
            if (query) {
                url.searchParams.set('q', query);
                url.searchParams.set('language', currentLanguage());
            } else {
                url.searchParams.delete('q');
                url.searchParams.delete('language');
            }
            window.history.replaceState(null, '', url.toString());
        }

        function updateTabs(query) {
            if (!tabs) {
                return;
            }

            tabs.hidden = !query;
            tabs.querySelectorAll('[data-wiki-language]').forEach(function (link) {
                var code = link.getAttribute('data-wiki-language') || '';
                var url = new URL(form.getAttribute('action') || link.href, window.location.href);
                url.searchParams.set('q', query);
                url.searchParams.set('language', code);
                link.href = url.toString();
            });
        }

        function setActiveTab(code) {
            if (!tabs) {
                return;
            }

            tabs.querySelectorAll('[data-wiki-language]').forEach(function (link) {
                var active = code === link.getAttribute('data-wiki-language');
                link.classList.toggle('is-active', active);
                if (active) {
                    link.setAttribute('aria-current', 'true');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        function renderResults(query, items) {
            var list = document.createElement('ul');

            resetResultsRegion();

            if (!items.length) {
                target.appendChild(createNotice(dataText('data-no-results-text', 'No Wikipedia results found.')));
                return;
            }

            list.className = 'wiki-results';
            items.forEach(function (item) {
                var title = item && item.title ? item.title : '';
                var pageId = item && item.pageid ? String(item.pageid) : '';
                var snippet = item && item.snippet ? plainText(item.snippet) : '';
                var wordCount = item && item.wordcount ? Number(item.wordcount) : 0;
                var result = document.createElement('li');
                var titleHeading = document.createElement('h2');
                var titleLink = document.createElement('a');
                var meta = document.createElement('div');
                var languageMeta = document.createElement('span');
                var tools = document.createElement('div');
                var read = document.createElement('a');

                if (!title || !pageId) {
                    return;
                }

                result.className = 'wiki-result';
                titleLink.href = articleUrl(title);
                titleLink.textContent = title;
                titleHeading.appendChild(titleLink);
                result.appendChild(titleHeading);

                meta.className = 'wiki-meta';
                languageMeta.textContent = currentLanguageLabel() + ' (' + currentLanguage() + ')';
                meta.appendChild(languageMeta);
                if (wordCount) {
                    var words = document.createElement('span');
                    words.textContent = wordCount.toLocaleString() + ' ' + dataText('data-words-text', 'words');
                    meta.appendChild(words);
                }
                result.appendChild(meta);

                if (snippet) {
                    var paragraph = document.createElement('p');
                    paragraph.textContent = snippet;
                    result.appendChild(paragraph);
                }

                tools.className = 'wiki-article-tools';
                read.className = 'wiki-btn secondary';
                read.href = articleUrl(title);
                read.textContent = dataText('data-read-text', 'Read');
                tools.appendChild(read);

                if ('1' === form.getAttribute('data-can-save')) {
                    var save = document.createElement('form');
                    var button = document.createElement('button');
                    save.className = 'wiki-inline-form';
                    save.method = 'post';
                    save.action = form.getAttribute('data-save-action') || '';
                    appendHidden(save, 'action', 'wordopedia_save_article');
                    appendHidden(save, '_wpnonce', form.getAttribute('data-save-nonce') || '');
                    appendHidden(save, 'page_id', pageId);
                    appendHidden(save, 'title', title);
                    appendHidden(save, 'language', currentLanguage());
                    button.className = 'wiki-btn secondary';
                    button.type = 'submit';
                    button.textContent = dataText('data-save-text', 'Save article');
                    save.appendChild(button);
                    tools.appendChild(save);
                }

                result.appendChild(tools);
                list.appendChild(result);
            });

            if (!list.children.length) {
                target.appendChild(createNotice(dataText('data-no-results-text', 'No Wikipedia results found.')));
                return;
            }

            target.appendChild(list);
        }

        function fetchResults() {
            var query = input.value.trim();
            var requestLanguage = currentLanguage();
            updateTabs(query);

            if (!query || query.length < 2) {
                if (controller) {
                    controller.abort();
                }
                resetResultsRegion();
                updateAddress('');
                return;
            }

            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            setNotice(dataText('data-searching-text', 'Searching Wikipedia...'));

            var params = new URLSearchParams({
                action: 'query',
                list: 'search',
                srsearch: query,
                srlimit: '12',
                srprop: 'snippet|wordcount|timestamp|size',
                formatversion: '2',
                format: 'json',
                utf8: '1',
                origin: '*'
            });

            fetch('https://' + requestLanguage + '.wikipedia.org/w/api.php?' + params.toString(), wordopediaFetchOptions({
                signal: controller.signal,
                credentials: 'omit'
            }))
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (data) {
                    var pages = data && data.query && Array.isArray(data.query.search) ? data.query.search : [];
                    if (query !== input.value.trim() || requestLanguage !== currentLanguage()) {
                        return;
                    }
                    renderResults(query, pages);
                    updateAddress(query);
                })
                .catch(function (error) {
                    if (!error || error.name !== 'AbortError') {
                        setNotice(dataText('data-no-results-text', 'No Wikipedia results found.'), 'error');
                    }
                });
        }

        function queueFetch(delay) {
            window.clearTimeout(timer);
            timer = window.setTimeout(fetchResults, 'number' === typeof delay ? delay : 500);
        }

        input.addEventListener('input', function () {
            updateTabs(input.value.trim());
            queueFetch();
        });

        if (tabs) {
            tabs.addEventListener('click', function (event) {
                var link = event.target.closest ? event.target.closest('[data-wiki-language]') : null;
                var code = link ? link.getAttribute('data-wiki-language') : '';
                if (!link || !tabs.contains(link) || !code || !input.value.trim()) {
                    return;
                }

                event.preventDefault();
                language.value = code;
                setActiveTab(code);
                updateTabs(input.value.trim());
                queueFetch(0);
                input.focus();
            });
        }

        updateTabs(input.value.trim());
        setActiveTab(currentLanguage());
    });
})();
