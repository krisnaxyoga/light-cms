/**
 * Shared progressive enhancement for admin/homepage/index.php AND any other
 * admin screen that renders the same markup conventions (currently also
 * admin/settings/index.php, for its Logo/Favicon fields):
 *
 * 1. Repeater rows ([data-repeater]): "Add another" clones the row
 *    <template>, renumbering its field names; "Remove" deletes a row
 *    from the DOM — with nothing to submit, HomepageContent::fromPost()
 *    drops it on save. No JS at all still works: the extra blank spare
 *    rows already in the markup are enough to add a couple of items.
 *    (A no-op on a page with no [data-repeater] elements, e.g. Settings.)
 *
 * 2. Image fields ([data-lcms-image-field]): "Choose from Media Library"
 *    opens a shared <dialog id="lcms-media-modal">, lists images via the
 *    existing admin/media/list JSON endpoint, and clicking one fills the
 *    text input + preview thumbnail for that field. Needs the including
 *    page to also render that dialog and set window.LCMS_HOMEPAGE.mediaListUrl
 *    (see the bottom of either view above for the exact markup to copy).
 */
(function () {
    'use strict';

    var config = window.LCMS_HOMEPAGE || {};

    /* ================================================================ */
    /*  Repeaters                                                        */
    /* ================================================================ */

    function initRepeaters() {
        document.querySelectorAll('[data-repeater]').forEach(function (group) {
            var template = group.parentElement.querySelector('template[data-repeater-template]');
            var button   = group.parentElement.querySelector('[data-action="add-row"]');

            if (button) {
                button.addEventListener('click', function () {
                    addRow(group, template);
                });
            }

            group.querySelectorAll('[data-action="remove-row"]').forEach(bindRemove);
        });
    }

    function bindRemove(button) {
        button.addEventListener('click', function () {
            var row = button.closest('[data-role="row"]');
            if (row) {
                row.remove();
            }
        });
    }

    function addRow(group, template) {
        if (!template) {
            return;
        }

        var index = group.querySelectorAll('[data-role="row"]').length;
        var html  = template.innerHTML.replace(/\[0\]/g, '[' + index + ']');

        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        var row = wrapper.firstElementChild;

        if (!row) {
            return;
        }

        group.appendChild(row);

        var removeBtn = row.querySelector('[data-action="remove-row"]');
        if (removeBtn) {
            bindRemove(removeBtn);
        }

        initImageField(row.querySelector('[data-lcms-image-field]'));
    }

    /* ================================================================ */
    /*  Image fields + shared media picker modal                        */
    /* ================================================================ */

    var activeField = null;

    function initImageFields() {
        document.querySelectorAll('[data-lcms-image-field]').forEach(initImageField);

        var modal = document.getElementById('lcms-media-modal');
        if (modal) {
            modal.addEventListener('close', function () {
                activeField = null;
            });
        }
    }

    function initImageField(field) {
        if (!field || field.dataset.lcmsBound) {
            return;
        }
        field.dataset.lcmsBound = '1';

        var browseBtn = field.querySelector('[data-action="browse"]');
        var clearBtn  = field.querySelector('[data-action="clear"]');
        var urlInput  = field.querySelector('[data-role="url"]');

        if (browseBtn) {
            browseBtn.addEventListener('click', function () {
                activeField = field;
                openMediaModal();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                setFieldValue(field, '');
            });
        }

        if (urlInput) {
            urlInput.addEventListener('input', function () {
                updatePreview(field, urlInput.value);
            });
        }
    }

    function setFieldValue(field, url) {
        var urlInput = field.querySelector('[data-role="url"]');
        if (urlInput) {
            urlInput.value = url;
        }
        updatePreview(field, url);
    }

    function updatePreview(field, url) {
        var preview     = field.querySelector('[data-role="preview"]');
        var placeholder = field.querySelector('[data-role="placeholder"]');

        if (!preview || !placeholder) {
            return;
        }

        if (url) {
            preview.src = url;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        } else {
            preview.removeAttribute('src');
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
        }
    }

    var mediaLoaded = false;

    function openMediaModal() {
        var modal = document.getElementById('lcms-media-modal');
        if (!modal) {
            return;
        }

        if (typeof modal.showModal === 'function') {
            modal.showModal();
        } else {
            modal.setAttribute('open', 'open');
        }

        if (!mediaLoaded) {
            loadMediaGrid();
        }
    }

    function loadMediaGrid() {
        var grid = document.getElementById('lcms-media-modal-grid');
        if (!grid || !config.mediaListUrl) {
            return;
        }

        fetch(config.mediaListUrl, { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)); })
            .then(function (data) {
                mediaLoaded = true;
                renderMediaGrid(grid, data.items || []);
            })
            .catch(function () {
                grid.innerHTML = '<p class="col-span-full py-8 text-center text-sm text-error">Could not load the media library.</p>';
            });
    }

    function renderMediaGrid(grid, items) {
        if (!items.length) {
            grid.innerHTML = '<p class="col-span-full py-8 text-center text-sm opacity-60">No images uploaded yet. Add one from the Media Library first.</p>';
            return;
        }

        grid.innerHTML = '';

        items.forEach(function (item) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'aspect-square overflow-hidden rounded-lg border border-base-300 bg-base-200 hover:border-primary';
            button.title = item.filename || '';

            var img = document.createElement('img');
            img.src = item.thumb || item.url;
            img.alt = item.alt || '';
            img.loading = 'lazy';
            img.className = 'h-full w-full object-cover';
            button.appendChild(img);

            button.addEventListener('click', function () {
                if (activeField) {
                    setFieldValue(activeField, item.url);
                }

                var modal = document.getElementById('lcms-media-modal');
                if (modal && typeof modal.close === 'function') {
                    modal.close();
                } else if (modal) {
                    modal.removeAttribute('open');
                }
            });

            grid.appendChild(button);
        });
    }

    /* ================================================================ */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initRepeaters();
            initImageFields();
        });
    } else {
        initRepeaters();
        initImageFields();
    }
})();
