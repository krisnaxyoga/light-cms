/**
 * Admin -> Menus builder. Vanilla JS, no framework (matches block-editor.js).
 *
 * The tree lives entirely client-side as a plain nested array of node
 * objects until "Save menu" is clicked, at which point it's serialized into
 * the #lcms-menu-items-json hidden field and the normal <form> submit takes
 * over — same "hidden field synced right before submit" pattern the block
 * editor uses for its #lcms-content field.
 */
(function () {
    'use strict';

    const dataEl = document.getElementById('lcms-menu-data');
    if (! dataEl) {
        return; // no menu selected yet (empty state) — nothing to wire up
    }

    const config = JSON.parse(dataEl.textContent);
    const treeEl = document.getElementById('lcms-menu-tree');
    const countEl = document.getElementById('lcms-menu-count');

    let seq = 0;
    const nextKey = () => 'n' + (++seq);

    /** Server tree (MenuItemModel::treeForMenu shape) -> our working shape. */
    function fromServer(nodes) {
        return (nodes || []).map((node) => ({
            key: nextKey(),
            title: node.title || '',
            url: node.url || '',
            target: node.target === '_blank',
            css_class: node.css_class || '',
            open: false,
            children: fromServer(node.children),
        }));
    }

    let tree = fromServer(config.items);

    function findParentArray(nodes, key, parent) {
        parent = parent || tree;
        for (let i = 0; i < nodes.length; i++) {
            if (nodes[i].key === key) {
                return { list: nodes, index: i };
            }
            const found = findParentArray(nodes[i].children, key, parent);
            if (found) {
                return found;
            }
        }
        return null;
    }

    function countAll(nodes) {
        return nodes.reduce((sum, n) => sum + 1 + countAll(n.children), 0);
    }

    // -- Tree mutations -------------------------------------------------

    function moveUp(key) {
        const loc = findParentArray(tree, key);
        if (loc && loc.index > 0) {
            const [node] = loc.list.splice(loc.index, 1);
            loc.list.splice(loc.index - 1, 0, node);
            render();
        }
    }

    function moveDown(key) {
        const loc = findParentArray(tree, key);
        if (loc && loc.index < loc.list.length - 1) {
            const [node] = loc.list.splice(loc.index, 1);
            loc.list.splice(loc.index + 1, 0, node);
            render();
        }
    }

    /** Nest under the item directly above it at the same level — the "make this a sub-topic" action. */
    function indent(key) {
        const loc = findParentArray(tree, key);
        if (! loc || loc.index === 0) {
            return; // nothing above it at this level to become a child of
        }
        const [node] = loc.list.splice(loc.index, 1);
        const newParent = loc.list[loc.index - 1];
        newParent.children.push(node);
        newParent.open = true;
        render();
    }

    /** Move up one level, becoming its former parent's next sibling. */
    function outdent(key) {
        if (tree.some((n) => n.key === key)) {
            return; // already top-level, nothing to outdent to
        }

        // Find the node's own parent list, then that list's owning array
        // (the grandparent's children, or the root) so we can splice it in
        // right after its former parent.
        const locateOwner = (nodes) => {
            for (const parent of nodes) {
                const idx = parent.children.findIndex((c) => c.key === key);
                if (idx !== -1) {
                    return { siblings: parent.children, index: idx, parentKey: parent.key };
                }
                const deeper = locateOwner(parent.children);
                if (deeper) {
                    return deeper;
                }
            }
            return null;
        };

        const owner = locateOwner(tree);
        if (! owner) {
            return;
        }

        const [node] = owner.siblings.splice(owner.index, 1);
        const grandLoc = findParentArray(tree, owner.parentKey);
        grandLoc.list.splice(grandLoc.index + 1, 0, node);
        render();
    }

    function removeNode(key) {
        if (! window.confirm('Remove this item (and any items nested under it)?')) {
            return;
        }
        const loc = findParentArray(tree, key);
        if (loc) {
            loc.list.splice(loc.index, 1);
            render();
        }
    }

    function findNode(key, nodes) {
        nodes = nodes || tree;
        for (const n of nodes) {
            if (n.key === key) {
                return n;
            }
            const found = findNode(key, n.children);
            if (found) {
                return found;
            }
        }
        return null;
    }

    function addNode(title, url, target, localeCode) {
        title = (title || '').trim();
        url = (url || '').trim();
        if (! title && ! url) {
            return;
        }
        const label = config.multilang && localeCode ? title + ' [' + localeCode.toUpperCase() + ']' : title;
        tree.push({
            key: nextKey(),
            title: label || url,
            url: url,
            target: !! target,
            css_class: '',
            open: false,
            children: [],
        });
        render();
    }

    // -- Rendering --------------------------------------------------------

    function renderList(nodes, depth) {
        if (! nodes.length) {
            return depth === 0 ? '<p class="lcms-menu-empty">No items yet — add something from the left.</p>' : '';
        }

        let html = '<ul>';
        nodes.forEach((node, i) => {
            const isFirst = i === 0;
            const isLast = i === nodes.length - 1;
            html += '<li class="lcms-menu-item" data-key="' + node.key + '">';
            html += '<div class="lcms-menu-item__row">';
            html += '<span class="lcms-menu-item__handle">' + (depth > 0 ? '&#8627;' : '&#9679;') + '</span>';
            html += '<span class="lcms-menu-item__title">' + esc(node.title || '(untitled)') + '</span>';
            html += '<span class="lcms-menu-item__url">' + esc(truncate(node.url, 40)) + '</span>';
            html += '<span class="lcms-menu-item__actions">';
            html += button('outdent', node.key, '&larr;', depth === 0, 'Move up a level');
            html += button('indent', node.key, '&rarr;', isFirst, 'Nest under the item above');
            html += button('up', node.key, '&uarr;', isFirst, 'Move up');
            html += button('down', node.key, '&darr;', isLast, 'Move down');
            html += button('edit', node.key, '&#9998;', false, 'Edit');
            html += button('remove', node.key, '&times;', false, 'Remove');
            html += '</span>';
            html += '</div>';

            if (node.open) {
                html += editPanel(node);
            }

            html += renderList(node.children, depth + 1);
            html += '</li>';
        });
        html += '</ul>';

        return html;
    }

    function button(action, key, glyph, disabled, title) {
        return '<button type="button" data-action="' + action + '" data-key="' + key + '"'
            + (disabled ? ' disabled' : '') + ' title="' + title + '">' + glyph + '</button>';
    }

    function editPanel(node) {
        return '<div class="lcms-menu-item__edit" data-edit-for="' + node.key + '">'
            + field('Navigation label', 'title', node.key, node.title)
            + field('URL', 'url', node.key, node.url)
            + '<label class="label cursor-pointer justify-start gap-2 py-1">'
            +   '<input type="checkbox" class="checkbox checkbox-xs" data-field="target" data-key="' + node.key + '" ' + (node.target ? 'checked' : '') + '>'
            +   '<span class="label-text">Open in new tab</span>'
            + '</label>'
            + field('CSS class (optional)', 'css_class', node.key, node.css_class)
            + '</div>';
    }

    function field(label, name, key, value) {
        return '<label class="form-control w-full">'
            + '<div class="label py-1"><span class="label-text">' + label + '</span></div>'
            + '<input type="text" class="input input-bordered input-sm w-full" data-field="' + name + '" data-key="' + key + '" value="' + esc(value || '') + '">'
            + '</label>';
    }

    function render() {
        treeEl.innerHTML = renderList(tree, 0);
        countEl.textContent = countAll(tree) + ' item' + (countAll(tree) === 1 ? '' : 's');
    }

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str;

        return div.innerHTML;
    }

    function truncate(str, len) {
        str = str || '';

        return str.length > len ? str.slice(0, len) + '…' : str;
    }

    // -- Tree interaction (event delegation) -------------------------------

    treeEl.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (btn) {
            const key = btn.dataset.key;
            const actions = { up: moveUp, down: moveDown, indent, outdent, remove: removeNode };
            if (btn.dataset.action === 'edit') {
                const node = findNode(key);
                node.open = ! node.open;
                render();
            } else {
                actions[btn.dataset.action](key);
            }
        }
    });

    treeEl.addEventListener('input', (e) => {
        const field = e.target.dataset.field;
        if (! field) {
            return;
        }
        const node = findNode(e.target.dataset.key);
        if (! node) {
            return;
        }
        node[field] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
        if (field === 'title' || field === 'url') {
            // Live-update the row's summary line without losing focus/rebuilding the edit panel.
            const row = treeEl.querySelector('li[data-key="' + node.key + '"] > .lcms-menu-item__row');
            if (row) {
                row.querySelector('.lcms-menu-item__title').textContent = node.title || '(untitled)';
                row.querySelector('.lcms-menu-item__url').textContent = truncate(node.url, 40);
            }
        }
    });

    // -- "Add menu items" panel: tabs -------------------------------------

    document.querySelectorAll('[data-lcms-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('[data-lcms-tab]').forEach((t) => t.classList.remove('tab-active'));
            tab.classList.add('tab-active');
            const type = tab.dataset.lcmsTab;
            document.querySelectorAll('[data-lcms-panel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.lcmsPanel !== type);
                panel.classList.toggle('flex', panel.dataset.lcmsPanel === type);
            });
        });
    });

    // -- Custom link -------------------------------------------------------

    const customAdd = document.getElementById('lcms-custom-add');
    if (customAdd) {
        customAdd.addEventListener('click', () => {
            const title = document.getElementById('lcms-custom-title');
            const url = document.getElementById('lcms-custom-url');
            const target = document.getElementById('lcms-custom-target');
            addNode(title.value, url.value, target.checked, null);
            title.value = '';
            url.value = '';
            target.checked = false;
        });
    }

    // -- Page/post/category search ------------------------------------------

    let searchTimers = {};

    document.querySelectorAll('[data-lcms-search]').forEach((input) => {
        const type = input.dataset.lcmsSearch;

        const runSearch = () => {
            const resultsEl = document.querySelector('[data-lcms-results="' + type + '"]');
            resultsEl.innerHTML = '<p class="p-3 text-xs opacity-50">Searching&hellip;</p>';

            fetch(config.searchUrl + '?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(input.value), { credentials: 'same-origin' })
                .then((res) => res.json())
                .then((items) => {
                    if (! items.length) {
                        resultsEl.innerHTML = '<p class="p-3 text-xs opacity-50">Nothing found.</p>';

                        return;
                    }
                    resultsEl.innerHTML = items.map((item) => (
                        '<label>'
                        + '<input type="checkbox" class="checkbox checkbox-xs mt-0.5" data-title="' + esc(item.title) + '" data-url="' + esc(item.url) + '" data-locale="' + esc(item.locale || '') + '">'
                        + '<span>' + esc(item.title) + (item.locale ? ' <span class="opacity-50">[' + item.locale.toUpperCase() + ']</span>' : '') + '</span>'
                        + '</label>'
                    )).join('');
                });
        };

        input.addEventListener('input', () => {
            clearTimeout(searchTimers[type]);
            searchTimers[type] = setTimeout(runSearch, 300);
        });

        // Seed the panel with an initial (blank-query) listing on first open.
        input.addEventListener('focus', () => {
            if (! input.dataset.loaded) {
                input.dataset.loaded = '1';
                runSearch();
            }
        }, { once: true });
    });

    document.querySelectorAll('[data-lcms-add-selected]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.lcmsAddSelected;
            const checked = document.querySelectorAll('[data-lcms-results="' + type + '"] input:checked');
            checked.forEach((cb) => addNode(cb.dataset.title, cb.dataset.url, false, cb.dataset.locale));
            checked.forEach((cb) => { cb.checked = false; });
        });
    });

    // -- Save ---------------------------------------------------------------

    document.getElementById('lcms-menu-settings').addEventListener('submit', () => {
        const toServer = (nodes) => nodes.map((n) => ({
            title: n.title,
            url: n.url,
            target: n.target,
            css_class: n.css_class,
            children: toServer(n.children),
        }));
        document.getElementById('lcms-menu-items-json').value = JSON.stringify(toServer(tree));
    });

    render();
})();
