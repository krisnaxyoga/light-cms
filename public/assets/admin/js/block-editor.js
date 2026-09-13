/* LightCMS Block Editor — a Gutenberg-inspired block editor in plain JS
 * (PRD §2.2 "custom block editor inspired by Gutenberg", §3.2).
 *
 * Output contract: the hidden <input name="content"> is kept in sync with
 *   [{ type, attrs, content }, ...]
 * exactly as App\Libraries\Editor\BlockParser expects. Nothing here talks
 * to a REST API — the surrounding <form> still posts to the same
 * PostController endpoints the old JSON textarea did.
 *
 * No framework, no build step, no dependencies.
 */
(function () {
  'use strict';

  /* ================================================================== */
  /*  Small DOM / string utilities                                       */
  /* ================================================================== */

  const $  = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  function h(tag, attrs, children) {
    const node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (key) {
        const value = attrs[key];
        if (value === null || value === undefined || value === false) return;
        if (key === 'class') node.className = value;
        else if (key === 'html') node.innerHTML = value;
        else if (key === 'text') node.textContent = value;
        else if (key === 'dataset') Object.assign(node.dataset, value);
        else if (key === 'style' && typeof value === 'object') Object.assign(node.style, value);
        else if (key.slice(0, 2) === 'on') node.addEventListener(key.slice(2), value);
        else if (typeof value === 'boolean') { if (key in node) node[key] = value; else if (value) node.setAttribute(key, ''); }
        else node.setAttribute(key, value);
      });
    }
    (children || []).forEach(function (child) {
      if (child === null || child === undefined || child === false) return;
      node.append(child.nodeType ? child : document.createTextNode(String(child)));
    });
    return node;
  }

  const uid = () => 'b' + Math.random().toString(36).slice(2, 9) + Date.now().toString(36).slice(-3);

  function debounce(fn, ms) {
    let timer;
    return function () {
      const ctx = this, args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
    };
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function stripTags(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html || '';
    return tmp.textContent || '';
  }

  function safeHref(href) {
    href = String(href || '').trim();
    return /^(https?:|mailto:|tel:|\/|#|\.\/|\.\.\/)/i.test(href) ? href : '';
  }

  // "example.com/page" is what people actually type — upgrade it to https
  // before the safety check, otherwise it would just be thrown away.
  function normalizeHref(value) {
    let href = String(value || '').trim();
    if (!href) return '';
    if (!/^([a-z][a-z0-9+.-]*:|\/|#|\.\/|\.\.\/)/i.test(href) && /^[\w-]+(\.[\w-]+)+/.test(href)) href = 'https://' + href;
    return safeHref(href);
  }

  function toggleRelToken(rel, token, on) {
    const tokens = String(rel || '').split(/\s+/).filter(Boolean).filter(t => t.toLowerCase() !== token);
    if (on) tokens.push(token);
    return tokens.join(' ');
  }

  function titleCase(str) {
    return str.replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\b\w/g, c => c.toUpperCase());
  }

  /* ================================================================== */
  /*  Icons (24x24 paths, Material-style)                                */
  /* ================================================================== */

  const ICONS = {
    plus: 'M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z',
    close: 'M18.3 5.7 12 12l6.3 6.3-1.4 1.4L12 13.4l-6.3 6.3-1.4-1.4L10.6 12 4.3 5.7l1.4-1.4L12 10.6l6.3-6.3z',
    undo: 'M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62C8.77 11.23 10.54 10.5 12.5 10.5c3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z',
    redo: 'M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z',
    trash: 'M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z',
    copy: 'M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z',
    up: 'M7.41 15.41 12 10.83l4.59 4.58L18 14l-6-6-6 6z',
    down: 'M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z',
    drag: 'M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
    cog: 'M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6a3.6 3.6 0 1 1 0-7.2 3.6 3.6 0 0 1 0 7.2z',
    more: 'M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
    bold: 'M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z',
    italic: 'M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z',
    link: 'M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z',
    code: 'M9.4 16.6 4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0 4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z',
    strike: 'M10 19h4v-3h-4v3zM5 4v3h5v3h4V7h5V4H5zM3 14h18v-2H3v2z',
    paragraph: 'M9 4a4 4 0 0 0 0 8h1v8h2V6h2v14h2V6h2V4H9z',
    heading: 'M5 4v16h2v-7h10v7h2V4h-2v7H7V4z',
    list: 'M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z',
    quote: 'M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z',
    image: 'M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z',
    gallery: 'M22 16V4c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2zm-11-4 2.03 2.71L16 11l4 5H8l3-4zM2 6v14c0 1.1.9 2 2 2h14v-2H4V6H2z',
    video: 'M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z',
    audio: 'M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z',
    table: 'M3 3v18h18V3H3zm8 16H5v-6h6v6zm0-8H5V5h6v6zm8 8h-6v-6h6v6zm0-8h-6V5h6v6z',
    button: 'M20 6H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2zm0 10H4V8h16v8zM7 11h10v2H7z',
    separator: 'M4 11h16v2H4z',
    spacer: 'M12 2 8 6h3v12H8l4 4 4-4h-3V6h3z',
    columns: 'M4 4h4v16H4zm6 0h4v16h-4zm6 0h4v16h-4z',
    embed: 'M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-9.4 13.6L5.8 12.8 9.6 9l1.4 1.4-2.4 2.4 2.4 2.4-1.4 1.4zm4.8 0-1.4-1.4 2.4-2.4-2.4-2.4L14.4 9l3.8 3.8-3.8 3.8z',
    accordion: 'M3 5h18v4H3zm0 6h18v2H3zm0 4h18v4H3z',
    cta: 'M20 2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4l4 4 4-4h4a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-3 10H7v-2h10v2zm0-4H7V6h10v2z',
    html: 'M4 5h16v14H4zm2 2v10h12V7zm3.5 2.5L7.6 12l1.9 2.5-1 1.1L5.9 12l2.6-3.6zm5 0L17.1 12l-2.6 3.6-1-1.1L15.4 12l-1.9-2.5z',
    desktop: 'M21 2H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7l-2 3v1h8v-1l-2-3h7a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm0 14H3V4h18v12z',
    tablet: 'M18.5 0h-13A2.5 2.5 0 0 0 3 2.5v19A2.5 2.5 0 0 0 5.5 24h13a2.5 2.5 0 0 0 2.5-2.5v-19A2.5 2.5 0 0 0 18.5 0zM12 23c-.83 0-1.5-.67-1.5-1.5S11.17 20 12 20s1.5.67 1.5 1.5S12.83 23 12 23zm7-4H5V3h14v16z',
    mobile: 'M17 1H7a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm0 18H7V5h10v14z',
    check: 'M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
    eye: 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
    back: 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
    swap: 'M6.99 11 3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z',
    alignnone: 'M3 3h18v2H3zm0 16h18v2H3zM3 7h18v10H3z',
    alignleft: 'M3 3h18v2H3zm0 16h18v2H3zM3 7h9v10H3zm11 2h7v2h-7zm0 4h7v2h-7z',
    aligncenter: 'M3 3h18v2H3zm0 16h18v2H3zM7 7h10v10H7z',
    alignright: 'M3 3h18v2H3zm0 16h18v2H3zm9-12h9v10h-9zM3 9h7v2H3zm0 4h7v2H3z',
    alignwide: 'M3 3h18v2H3zm0 16h18v2H3zM1 7h22v10H1z',
    upload: 'M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z',
    library: 'M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9-2.5-3.01L14 14.5l-1.5-2-3.5 4.5h11l-3.5-4.5z',
    fullscreen: 'M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z',
    search: 'M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z',
    ordered: 'M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z',
    addrow: 'M22 10a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4zm-2 4H4v-4h16v4zM11 2v4h2V2h-2zm0 16v4h2v-4h-2z',
    addcol: 'M14 2h-4a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm0 18h-4V4h4v16zM2 11v2h4v-2H2zm16 0v2h4v-2h-4z',
    info: 'M11 7h2v2h-2zm0 4h2v6h-2zm1-9a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z'
  };

  function icon(name, size) {
    return '<svg class="lcms-icon" width="' + (size || 24) + '" height="' + (size || 24) + '" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' + (ICONS[name] || ICONS.info) + '"/></svg>';
  }

  /* ================================================================== */
  /*  Inline HTML sanitizer for contenteditable output                   */
  /* ================================================================== */

  const INLINE_TAGS = { B: 'strong', STRONG: 'strong', I: 'em', EM: 'em', U: 'u', S: 's', STRIKE: 's', DEL: 's', A: 'a', CODE: 'code', MARK: 'mark', SUB: 'sub', SUP: 'sup' };
  const BLOCKISH = /^(DIV|P|LI|UL|OL|H[1-6]|BLOCKQUOTE|TR|SECTION|ARTICLE|HEADER|FOOTER|PRE)$/;

  function sanitizeInline(html) {
    const tpl = document.createElement('template');
    tpl.innerHTML = html || '';

    function clean(node) {
      let out = '';
      node.childNodes.forEach(function (child) {
        if (child.nodeType === 3) { out += escapeHtml(child.nodeValue); return; }
        if (child.nodeType !== 1) return;
        const tag = child.tagName;
        if (tag === 'BR') { out += '<br>'; return; }
        if (INLINE_TAGS[tag]) {
          const mapped = INLINE_TAGS[tag];
          const inner = clean(child);
          if (mapped === 'a') {
            const href = safeHref(child.getAttribute('href'));
            out += href ? '<a href="' + escapeHtml(href) + '">' + inner + '</a>' : inner;
          } else {
            out += '<' + mapped + '>' + inner + '</' + mapped + '>';
          }
          return;
        }
        if (BLOCKISH.test(tag)) { out += clean(child) + '<br>'; return; }
        out += clean(child);
      });
      return out;
    }

    return clean(tpl.content)
      .replace(/ /g, ' ')
      .replace(/(<br>\s*)+$/g, '')
      .replace(/^(\s*<br>)+/g, '')
      .trim();
  }

  /* ================================================================== */
  /*  Block registry — MUST stay in sync with BlockRenderer.php          */
  /* ================================================================== */

  const CATEGORIES = [['text', 'Text'], ['media', 'Media'], ['design', 'Design'], ['embed', 'Embeds'], ['advanced', 'Advanced']];

  const TYPES = {
    paragraph: { label: 'Paragraph', icon: 'paragraph', category: 'text', text: true, keywords: 'p text body', create: () => ({ type: 'paragraph', attrs: {}, content: '' }) },
    heading:   { label: 'Heading', icon: 'heading', category: 'text', text: true, keywords: 'title h1 h2 h3 subtitle', create: () => ({ type: 'heading', attrs: { level: 2 }, content: '' }) },
    list:      { label: 'List', icon: 'list', category: 'text', keywords: 'bullet numbered ul ol', create: () => ({ type: 'list', attrs: { ordered: false, items: [''] }, content: '' }) },
    quote:     { label: 'Quote', icon: 'quote', category: 'text', text: true, keywords: 'blockquote cite', create: () => ({ type: 'quote', attrs: { cite: '' }, content: '' }) },
    code:      { label: 'Code', icon: 'code', category: 'text', keywords: 'pre source snippet', create: () => ({ type: 'code', attrs: { language: 'plaintext' }, content: '' }) },
    image:     { label: 'Image', icon: 'image', category: 'media', keywords: 'photo picture img link backlink', create: () => ({ type: 'image', attrs: { url: '', alt: '', caption: '', align: '', href: '', link_target: '', rel: '' }, content: '' }) },
    gallery:   { label: 'Gallery', icon: 'gallery', category: 'media', keywords: 'images photos grid', create: () => ({ type: 'gallery', attrs: { images: [] }, content: '' }) },
    video:     { label: 'Video', icon: 'video', category: 'media', keywords: 'movie mp4 youtube', create: () => ({ type: 'video', attrs: { url: '', embed: '' }, content: '' }) },
    audio:     { label: 'Audio', icon: 'audio', category: 'media', keywords: 'sound mp3 podcast', create: () => ({ type: 'audio', attrs: { url: '' }, content: '' }) },
    button:    { label: 'Button', icon: 'button', category: 'design', text: true, keywords: 'link cta', create: () => ({ type: 'button', attrs: { url: '', style: 'primary' }, content: '' }) },
    table:     { label: 'Table', icon: 'table', category: 'design', keywords: 'grid rows columns', create: () => ({ type: 'table', attrs: { rows: [['', ''], ['', '']] }, content: '' }) },
    columns:   { label: 'Columns', icon: 'columns', category: 'design', keywords: 'layout grid row', create: () => ({ type: 'columns', attrs: { columns: [{ blocks: [] }, { blocks: [] }] }, content: '' }) },
    separator: { label: 'Separator', icon: 'separator', category: 'design', keywords: 'hr divider line', create: () => ({ type: 'separator', attrs: {}, content: '' }) },
    spacer:    { label: 'Spacer', icon: 'spacer', category: 'design', keywords: 'space gap margin', create: () => ({ type: 'spacer', attrs: { height: 40 }, content: '' }) },
    embed:     { label: 'Embed', icon: 'embed', category: 'embed', keywords: 'youtube vimeo twitter instagram url', create: () => ({ type: 'embed', attrs: { url: '' }, content: '' }) },
    html:      { label: 'Custom HTML', icon: 'html', category: 'embed', keywords: 'raw markup code', create: () => ({ type: 'html', attrs: {}, content: '' }) },
    accordion: { label: 'Accordion / FAQ', icon: 'accordion', category: 'advanced', keywords: 'faq toggle details', create: () => ({ type: 'accordion', attrs: { items: [{ title: '', content: '' }] }, content: '' }) },
    cta:       { label: 'Call to Action', icon: 'cta', category: 'advanced', keywords: 'banner promo', create: () => ({ type: 'cta', attrs: { title: '', text: '', url: '', button_label: 'Learn more' }, content: '' }) }
  };

  const TRANSFORMS = {
    paragraph: ['heading', 'quote', 'list', 'code'],
    heading: ['paragraph', 'quote'],
    quote: ['paragraph', 'heading'],
    list: ['paragraph'],
    code: ['paragraph', 'html'],
    html: ['code']
  };

  /* ================================================================== */
  /*  State                                                              */
  /* ================================================================== */

  const state = {
    blocks: [],
    selected: null,
    history: [],
    future: [],
    typing: false,
    mode: 'visual',        // visual | code
    sidebarOpen: true,
    tab: 'post',           // post | block
    device: 'desktop',
    dirty: false,
    submitting: false,
    dragging: null
  };

  let config = {};
  let dom = {};

  /* ---- tree helpers ------------------------------------------------ */

  function withIds(blocks) {
    return (blocks || []).map(function (raw) {
      // PHP encodes an empty attrs array as [] (not {}); normalize so later
      // Object.assign()'d attributes aren't silently dropped by JSON.stringify.
      const rawAttrs = raw.attrs && !Array.isArray(raw.attrs) ? raw.attrs : {};
      const block = { clientId: uid(), type: raw.type, attrs: JSON.parse(JSON.stringify(rawAttrs)), content: typeof raw.content === 'string' ? raw.content : '' };
      if (block.type === 'columns') {
        block.attrs.columns = (block.attrs.columns || []).map(col => ({ blocks: withIds(col.blocks || []) }));
      }
      return block;
    });
  }

  function serialize(blocks) {
    return (blocks || []).map(function (block) {
      const out = { type: block.type, attrs: JSON.parse(JSON.stringify(block.attrs || {})), content: block.content || '' };
      if (block.type === 'columns') {
        out.attrs.columns = (block.attrs.columns || []).map(col => ({ blocks: serialize(col.blocks || []) }));
      }
      return out;
    });
  }

  // Find a block anywhere in the tree: returns { block, list, index, parent }
  function locate(id, list, parent) {
    list = list || state.blocks;
    for (let i = 0; i < list.length; i++) {
      const block = list[i];
      if (block.clientId === id) return { block: block, list: list, index: i, parent: parent || null };
      if (block.type === 'columns') {
        for (const col of block.attrs.columns || []) {
          const found = locate(id, col.blocks, block);
          if (found) return found;
        }
      }
    }
    return null;
  }

  function flatten(list, out) {
    out = out || [];
    (list || []).forEach(function (block) {
      out.push(block);
      if (block.type === 'columns') (block.attrs.columns || []).forEach(col => flatten(col.blocks, out));
    });
    return out;
  }

  function isText(block) { return !!(TYPES[block.type] && TYPES[block.type].text); }

  function collectText(list) {
    let text = '';
    (list || []).forEach(function (block) {
      const a = block.attrs || {};
      switch (block.type) {
        case 'list': text += ' ' + (a.items || []).map(stripTags).join(' '); break;
        case 'table': text += ' ' + (a.rows || []).map(r => r.map(stripTags).join(' ')).join(' '); break;
        case 'accordion': text += ' ' + (a.items || []).map(i => (i.title || '') + ' ' + stripTags(i.content || '')).join(' '); break;
        case 'cta': text += ' ' + (a.title || '') + ' ' + (a.text || ''); break;
        case 'image': text += ' ' + (a.caption || ''); break;
        case 'columns': text += ' ' + (a.columns || []).map(c => collectText(c.blocks)).join(' '); break;
        case 'code': text += ' ' + (block.content || ''); break;
        default: text += ' ' + stripTags(block.content || '');
      }
    });
    return text;
  }

  /* ---- history ----------------------------------------------------- */

  function pushHistory() {
    state.history.push(JSON.stringify(serialize(state.blocks)));
    if (state.history.length > 100) state.history.shift();
    state.future = [];
    updateUndoButtons();
  }

  const endTyping = debounce(function () { state.typing = false; }, 800);

  // Call BEFORE mutating text content from a keystroke — pushes one
  // history entry per typing burst instead of one per character.
  function beforeTextChange() {
    if (!state.typing) { pushHistory(); state.typing = true; }
    endTyping();
  }

  function undo() {
    if (!state.history.length) return;
    state.future.push(JSON.stringify(serialize(state.blocks)));
    state.blocks = withIds(JSON.parse(state.history.pop()));
    state.selected = null;
    state.typing = false;
    render();
    markDirty();
  }

  function redo() {
    if (!state.future.length) return;
    state.history.push(JSON.stringify(serialize(state.blocks)));
    state.blocks = withIds(JSON.parse(state.future.pop()));
    state.selected = null;
    render();
    markDirty();
  }

  function updateUndoButtons() {
    if (dom.undoBtn) dom.undoBtn.disabled = state.history.length === 0;
    if (dom.redoBtn) dom.redoBtn.disabled = state.future.length === 0;
  }

  // Wrap any structural mutation: history + render + dirty flag.
  function mutate(fn, opts) {
    opts = opts || {};
    pushHistory();
    fn();
    if (!opts.skipRender) render();
    markDirty();
  }

  /* ---- dirty / output sync ------------------------------------------ */

  function syncOutput() {
    dom.output.value = JSON.stringify(serialize(state.blocks));
    updateWordCount();
  }

  function markDirty() {
    state.dirty = true;
    syncOutput();
    setSaveIndicator('Unsaved changes');
    scheduleLocalSave();
    scheduleAnalyze();
  }

  function setSaveIndicator(text) {
    if (dom.saveIndicator) dom.saveIndicator.textContent = text;
  }

  function updateWordCount() {
    const words = (collectText(state.blocks).trim().match(/\S+/g) || []).length;
    const minutes = Math.max(1, Math.ceil(words / 200));
    if (dom.wordCount) dom.wordCount.textContent = words + ' word' + (words === 1 ? '' : 's') + ' · ' + minutes + ' min read';
  }

  /* ================================================================== */
  /*  Selection & focus                                                  */
  /* ================================================================== */

  function wrapperFor(id) { return dom.canvas.querySelector('.lcms-blk[data-client-id="' + id + '"]'); }

  function select(id, opts) {
    opts = opts || {};
    if (state.selected === id && !opts.force) return;
    const prev = state.selected ? wrapperFor(state.selected) : null;
    if (prev) { prev.classList.remove('is-selected'); const tb = prev.querySelector(':scope > .lcms-blk__toolbar'); if (tb) tb.remove(); }
    state.selected = id;
    const wrap = id ? wrapperFor(id) : null;
    if (wrap) {
      wrap.classList.add('is-selected');
      const found = locate(id);
      if (found) wrap.prepend(buildToolbar(found));
    }
    if (id) { state.tab = 'block'; } else if (state.tab === 'block') { state.tab = 'post'; }
    renderSidebarTabs();
    renderInspector();
    closePopovers();
  }

  function focusBlock(id, position) {
    const wrap = wrapperFor(id);
    if (!wrap) return;
    const editable = wrap.querySelector(':scope > .lcms-blk__body [contenteditable], :scope > .lcms-blk__body textarea, :scope > .lcms-blk__body input');
    select(id, { force: true });
    if (!editable) { wrap.focus(); return; }
    editable.focus();
    if (editable.isContentEditable) placeCaret(editable, position === 'start' ? 'start' : (typeof position === 'number' ? position : 'end'));
    else if (typeof editable.setSelectionRange === 'function') { const len = editable.value.length; editable.setSelectionRange(position === 'start' ? 0 : len, position === 'start' ? 0 : len); }
  }

  function placeCaret(editable, where) {
    const sel = window.getSelection();
    const range = document.createRange();
    if (typeof where === 'number') {
      // caret at a plain-text offset
      const walker = document.createTreeWalker(editable, NodeFilter.SHOW_TEXT);
      let remaining = where, node;
      while ((node = walker.nextNode())) {
        if (remaining <= node.nodeValue.length) { range.setStart(node, remaining); range.collapse(true); sel.removeAllRanges(); sel.addRange(range); return; }
        remaining -= node.nodeValue.length;
      }
      where = 'end';
    }
    range.selectNodeContents(editable);
    range.collapse(where === 'start');
    sel.removeAllRanges();
    sel.addRange(range);
  }

  function caretInfo(editable) {
    const sel = window.getSelection();
    if (!sel.rangeCount || !editable.contains(sel.anchorNode)) return null;
    const range = sel.getRangeAt(0);
    const before = document.createRange(); before.setStart(editable, 0); before.setEnd(range.startContainer, range.startOffset);
    const after = document.createRange(); after.setStart(range.endContainer, range.endOffset); after.setEnd(editable, editable.childNodes.length);
    const beforeFrag = document.createElement('div'); beforeFrag.append(before.cloneContents());
    const afterFrag = document.createElement('div'); afterFrag.append(after.cloneContents());
    return {
      collapsed: range.collapsed,
      atStart: beforeFrag.textContent.length === 0 && !beforeFrag.querySelector('img'),
      atEnd: afterFrag.textContent.length === 0 && !afterFrag.querySelector('img'),
      beforeHtml: sanitizeInline(beforeFrag.innerHTML),
      afterHtml: sanitizeInline(afterFrag.innerHTML),
      beforeLength: beforeFrag.textContent.length
    };
  }

  /* ================================================================== */
  /*  Structural operations                                              */
  /* ================================================================== */

  function insertBlock(type, list, index, preset) {
    let block;
    mutate(function () {
      block = Object.assign(TYPES[type].create(), { clientId: uid() });
      if (preset) { Object.assign(block.attrs, preset.attrs || {}); if (preset.content != null) block.content = preset.content; }
      list.splice(index, 0, block);
    });
    focusBlock(block.clientId, 'start');
    return block;
  }

  function removeBlock(id) {
    const found = locate(id);
    if (!found) return;
    const prev = found.list[found.index - 1];
    const next = found.list[found.index + 1];
    mutate(function () { found.list.splice(found.index, 1); });
    const target = prev || next || (found.parent ? found.parent : null);
    if (target) focusBlock(target.clientId, 'end'); else select(null);
  }

  function duplicateBlock(id) {
    const found = locate(id);
    if (!found) return;
    const copy = withIds(serialize([found.block]))[0];
    mutate(function () { found.list.splice(found.index + 1, 0, copy); });
    focusBlock(copy.clientId, 'end');
  }

  function moveBlock(id, direction) {
    const found = locate(id);
    if (!found) return;
    const to = found.index + direction;
    if (to < 0 || to >= found.list.length) return;
    mutate(function () { found.list.splice(to, 0, found.list.splice(found.index, 1)[0]); });
    select(id, { force: true });
    const wrap = wrapperFor(id); if (wrap) wrap.scrollIntoView({ block: 'nearest' });
  }

  function moveBlockTo(id, targetList, targetIndex) {
    const found = locate(id);
    if (!found) return;
    // don't drop a columns block into one of its own columns
    if (found.block.type === 'columns' && flatten([found.block]).some(b => b !== found.block && locate(b.clientId, targetList))) return;
    mutate(function () {
      found.list.splice(found.index, 1);
      if (targetList === found.list && targetIndex > found.index) targetIndex--;
      targetList.splice(targetIndex, 0, found.block);
    });
    select(id, { force: true });
  }

  function transformBlock(id, toType) {
    const found = locate(id);
    if (!found) return;
    const from = found.block;
    mutate(function () {
      const plainParts = from.type === 'list' ? (from.attrs.items || []) : [from.content || ''];
      if (toType === 'paragraph' && from.type === 'list') {
        const items = (from.attrs.items || []).filter(Boolean);
        const blocks = (items.length ? items : ['']).map(item => Object.assign(TYPES.paragraph.create(), { clientId: uid(), content: item }));
        found.list.splice(found.index, 1, ...blocks);
        found.block = blocks[0];
        return;
      }
      const next = Object.assign(TYPES[toType].create(), { clientId: from.clientId });
      if (toType === 'list') next.attrs.items = [from.content || ''];
      else if (toType === 'code') next.content = stripTags(plainParts.join('\n'));
      else if (toType === 'html' && from.type === 'code') next.content = from.content;
      else if (toType === 'code' && from.type === 'html') next.content = from.content;
      else if (from.type === 'code') next.content = escapeHtml(from.content);
      else next.content = from.content;
      if (toType === 'heading' && from.type === 'heading') next.attrs.level = from.attrs.level;
      found.list.splice(found.index, 1, next);
      found.block = next;
    });
    focusBlock(found.block.clientId, 'end');
  }

  function updateAttrs(id, patch, opts) {
    const found = locate(id);
    if (!found) return;
    opts = opts || {};
    if (!opts.silent) pushHistory();
    Object.assign(found.block.attrs, patch);
    markDirty();
    if (!opts.keepBody) refreshBlock(id);
    if (opts.refreshInspector) renderInspector();
  }

  /* ================================================================== */
  /*  Rendering                                                          */
  /* ================================================================== */

  function render() {
    if (state.mode === 'code') { renderCodeMode(); return; }
    dom.canvas.innerHTML = '';
    renderList(state.blocks, dom.canvas, null);
    if (state.selected) {
      const wrap = wrapperFor(state.selected);
      const found = locate(state.selected);
      if (wrap && found) { wrap.classList.add('is-selected'); wrap.prepend(buildToolbar(found)); }
      else state.selected = null;
    }
    renderSidebarTabs();
    renderInspector();
    syncOutput();
    updateUndoButtons();
  }

  function renderList(list, container, parentBlock) {
    list.forEach(function (block, index) {
      container.append(buildGap(list, index));
      container.append(buildBlock(block, list, index));
    });
    container.append(buildAppender(list, parentBlock));
  }

  function buildGap(list, index) {
    const gap = h('div', { class: 'lcms-gap', ondragover: e => onGapDragOver(e, gap), ondragleave: () => gap.classList.remove('is-drop'), ondrop: e => onGapDrop(e, list, index, gap) });
    const btn = h('button', { type: 'button', class: 'lcms-gap__btn', title: 'Add block', 'aria-label': 'Add block', html: icon('plus', 18), onclick: function (e) { e.stopPropagation(); openInserter({ anchor: btn, onPick: type => insertBlock(type, list, index) }); } });
    gap.append(btn);
    return gap;
  }

  function buildAppender(list, parentBlock) {
    const appender = h('div', { class: 'lcms-appender', ondragover: e => onGapDragOver(e, appender), ondragleave: () => appender.classList.remove('is-drop'), ondrop: e => onGapDrop(e, list, list.length, appender) });
    const last = list[list.length - 1];
    const showHint = !(last && last.type === 'paragraph' && !last.content);
    if (showHint) {
      appender.append(h('div', { class: 'lcms-appender__hint', text: parentBlock ? 'Add block' : 'Type / to choose a block', onclick: function () { insertBlock('paragraph', list, list.length); } }));
    }
    const btn = h('button', { type: 'button', class: 'lcms-appender__btn', title: 'Add block', 'aria-label': 'Add block', html: icon('plus', 18), onclick: function (e) { e.stopPropagation(); openInserter({ anchor: btn, onPick: type => insertBlock(type, list, list.length) }); } });
    appender.append(btn);
    return appender;
  }

  function buildBlock(block, list, index) {
    const wrap = h('div', {
      class: 'lcms-blk lcms-blk--' + block.type,
      dataset: { clientId: block.clientId, type: block.type },
      tabindex: isText(block) || block.type === 'list' || block.type === 'code' || block.type === 'html' ? -1 : 0,
      onmousedown: function (e) { e.stopPropagation(); if (state.selected !== block.clientId) select(block.clientId); },
      onfocus: function (e) { if (e.target === wrap && state.selected !== block.clientId) select(block.clientId); },
      onkeydown: function (e) { if (e.target !== wrap) return; onWrapperKey(e, block); },
      ondragover: e => onBlockDragOver(e, wrap),
      ondragleave: () => wrap.classList.remove('is-drop-before', 'is-drop-after'),
      ondrop: e => onBlockDrop(e, block, wrap)
    });
    wrap.append(h('div', { class: 'lcms-blk__type', text: TYPES[block.type] ? TYPES[block.type].label : block.type }));
    wrap.append(buildBody(block));
    return wrap;
  }

  function refreshBlock(id) {
    const found = locate(id);
    const wrap = wrapperFor(id);
    if (!found || !wrap) return;
    const body = wrap.querySelector(':scope > .lcms-blk__body');
    if (body) body.replaceWith(buildBody(found.block));
    wrap.className = 'lcms-blk lcms-blk--' + found.block.type + (state.selected === id ? ' is-selected' : '');
  }

  function buildBody(block) {
    const body = h('div', { class: 'lcms-blk__body' });
    const view = VIEWS[block.type] || VIEWS.unknown;
    const node = view(block);
    if (node) body.append(node);
    return body;
  }

  function onWrapperKey(e, block) {
    if (e.key === 'Backspace' || e.key === 'Delete') { e.preventDefault(); removeBlock(block.clientId); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); navigate(block.clientId, -1); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); navigate(block.clientId, 1); }
    else if (e.key === 'Enter') { e.preventDefault(); const f = locate(block.clientId); if (f) insertBlock('paragraph', f.list, f.index + 1); }
  }

  function navigate(id, direction) {
    const all = flatten(state.blocks);
    const idx = all.findIndex(b => b.clientId === id);
    const target = all[idx + direction];
    if (target) focusBlock(target.clientId, direction < 0 ? 'end' : 'start');
  }

  /* ---- rich text (paragraph / heading / quote / button / captions) --- */

  function richText(block, tag, opts) {
    opts = opts || {};
    const editable = h(tag, {
      class: 'lcms-rt' + (opts.class ? ' ' + opts.class : ''),
      contenteditable: 'true',
      spellcheck: 'true',
      'data-placeholder': opts.placeholder || '',
      html: opts.get ? opts.get() : block.content
    });

    editable.addEventListener('input', function () {
      beforeTextChange();
      const html = sanitizeInline(editable.innerHTML);
      if (opts.set) opts.set(html); else block.content = html;
      markDirty();
      if (block.type === 'paragraph' && slash.open && slash.blockId === block.clientId) {
        const text = editable.textContent;
        if (text.charAt(0) !== '/') closePopovers(); else filterInserter(text.slice(1));
      }
    });

    editable.addEventListener('keydown', function (e) {
      if (opts.onKey && opts.onKey(e, editable) === false) return;
      const meta = e.metaKey || e.ctrlKey;
      if (meta && !e.shiftKey && !e.altKey) {
        if (e.key === 'b') { e.preventDefault(); format('bold'); return; }
        if (e.key === 'i') { e.preventDefault(); format('italic'); return; }
        if (e.key === 'k') { e.preventDefault(); format('link'); return; }
      }
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        if (opts.singleLine) { const f = locate(block.clientId); if (f) insertBlock('paragraph', f.list, f.index + 1); return; }
        const info = caretInfo(editable);
        const found = locate(block.clientId);
        if (!info || !found) return;
        mutate(function () {
          if (opts.set) opts.set(info.beforeHtml); else found.block.content = info.beforeHtml;
          const next = Object.assign(TYPES.paragraph.create(), { clientId: uid(), content: info.afterHtml });
          found.list.splice(found.index + 1, 0, next);
          state.selected = next.clientId;
        });
        focusBlock(state.selected, 'start');
        return;
      }
      if (e.key === 'Enter' && e.shiftKey) { e.preventDefault(); document.execCommand('insertLineBreak'); return; }
      if (e.key === 'Backspace' && !opts.set) {
        const info = caretInfo(editable);
        if (info && info.collapsed && info.atStart) {
          e.preventDefault();
          const found = locate(block.clientId);
          if (!found) return;
          const prev = found.list[found.index - 1];
          if (!prev) { if (found.parent) select(found.parent.clientId); return; }
          if (isText(prev) && prev.type !== 'button') {
            const offset = stripTags(prev.content).length;
            mutate(function () { prev.content = (prev.content || '') + (found.block.content || ''); found.list.splice(found.index, 1); });
            focusBlock(prev.clientId, offset);
          } else if (!found.block.content) {
            removeBlock(block.clientId);
          } else {
            focusBlock(prev.clientId, 'end');
          }
          return;
        }
      }
      if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        const info = caretInfo(editable);
        if (info && info.collapsed && ((e.key === 'ArrowUp' && info.atStart) || (e.key === 'ArrowDown' && info.atEnd))) {
          e.preventDefault();
          navigate(block.clientId, e.key === 'ArrowUp' ? -1 : 1);
        }
        return;
      }
      if (e.key === '/' && block.type === 'paragraph' && !opts.set && editable.textContent === '') {
        setTimeout(function () { openInserter({ anchor: editable, slashFor: block.clientId, onPick: function (type) { replaceEmptyParagraph(block.clientId, type); } }); }, 0);
      }
      if (e.key === 'Escape') { select(null); editable.blur(); }
    });

    editable.addEventListener('paste', function (e) {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      const paragraphs = text.split(/\n{2,}/).map(s => s.trim()).filter(Boolean);
      if (paragraphs.length > 1 && !opts.set && block.type === 'paragraph') {
        const found = locate(block.clientId);
        const info = caretInfo(editable) || { beforeHtml: block.content, afterHtml: '' };
        mutate(function () {
          found.block.content = info.beforeHtml + escapeHtml(paragraphs[0]).replace(/\n/g, '<br>');
          const rest = paragraphs.slice(1).map((p, i, arr) => Object.assign(TYPES.paragraph.create(), { clientId: uid(), content: escapeHtml(p).replace(/\n/g, '<br>') + (i === arr.length - 1 ? info.afterHtml : '') }));
          found.list.splice(found.index + 1, 0, ...rest);
          state.selected = rest[rest.length - 1].clientId;
        });
        focusBlock(state.selected, 'end');
        return;
      }
      document.execCommand('insertText', false, text.replace(/\n+/g, opts.singleLine ? ' ' : '\n'));
    });

    editable.addEventListener('focus', function () { if (state.selected !== block.clientId) select(block.clientId); });
    return editable;
  }

  function replaceEmptyParagraph(id, type) {
    const found = locate(id);
    if (!found) return;
    mutate(function () {
      const next = Object.assign(TYPES[type].create(), { clientId: id });
      found.list.splice(found.index, 1, next);
    });
    focusBlock(id, 'start');
  }

  function format(command) {
    const sel = window.getSelection();
    if (!sel.rangeCount) return;
    const editable = sel.anchorNode && (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement).closest('[contenteditable]');
    if (!editable) return;
    if (command === 'link') {
      const existing = sel.anchorNode.parentElement.closest('a');
      const url = window.prompt('Link URL', existing ? existing.getAttribute('href') : 'https://');
      if (url === null) return;
      if (url === '') document.execCommand('unlink');
      else if (sel.isCollapsed && existing) existing.setAttribute('href', url);
      else if (sel.isCollapsed) return;
      else document.execCommand('createLink', false, url);
    } else if (command === 'code') {
      const parentCode = sel.anchorNode.parentElement.closest('code');
      if (parentCode) { const text = document.createTextNode(parentCode.textContent); parentCode.replaceWith(text); }
      else if (!sel.isCollapsed) { const range = sel.getRangeAt(0); const code = document.createElement('code'); code.append(range.extractContents()); range.insertNode(code); }
    } else {
      document.execCommand(command === 'strike' ? 'strikeThrough' : command);
    }
    editable.dispatchEvent(new Event('input', { bubbles: true }));
  }

  /* ---- per-type views ------------------------------------------------ */

  const VIEWS = {
    unknown: block => h('div', { class: 'lcms-placeholder', text: 'Unknown block type "' + block.type + '" — it will be kept as-is.' }),

    paragraph: block => richText(block, 'p', { placeholder: 'Type / to choose a block' }),

    heading: block => richText(block, 'h' + Math.min(6, Math.max(1, block.attrs.level || 2)), { placeholder: 'Heading' }),

    quote: function (block) {
      const wrap = h('blockquote', { class: 'lcms-quote' });
      wrap.append(richText(block, 'p', { placeholder: 'Write a quote…' }));
      wrap.append(richText(block, 'cite', { placeholder: 'Add citation', singleLine: true, get: () => escapeHtml(block.attrs.cite || ''), set: html => { block.attrs.cite = stripTags(html); } }));
      return wrap;
    },

    button: function (block) {
      const wrap = h('div', { class: 'lcms-button-wrap' });
      wrap.append(richText(block, 'span', { class: 'lcms-btn lcms-btn--' + (block.attrs.style || 'primary') + ' lcms-button-label', placeholder: 'Add text…', singleLine: true }));
      if (!block.attrs.url) wrap.append(h('span', { class: 'lcms-hint', text: 'No link set — add one in the block sidebar.' }));
      return wrap;
    },

    list: function (block) {
      const list = h(block.attrs.ordered ? 'ol' : 'ul', { class: 'lcms-rt lcms-list', contenteditable: 'true' });
      (block.attrs.items && block.attrs.items.length ? block.attrs.items : ['']).forEach(item => list.append(h('li', { html: item })));
      const readItems = () => Array.from(list.children).filter(c => c.tagName === 'LI').map(li => sanitizeInline(li.innerHTML));
      list.addEventListener('input', function () {
        beforeTextChange();
        const stray = Array.from(list.children).filter(c => c.tagName !== 'LI');
        if (stray.length) {
          // Browser "exited" the list on Enter — turn that into a paragraph after the list.
          const found = locate(block.clientId);
          const text = sanitizeInline(stray.map(s => s.innerHTML).join(''));
          stray.forEach(s => s.remove());
          block.attrs.items = readItems();
          mutate(function () {
            const next = Object.assign(TYPES.paragraph.create(), { clientId: uid(), content: text });
            found.list.splice(found.index + 1, 0, next);
            state.selected = next.clientId;
          });
          focusBlock(state.selected, 'end');
          return;
        }
        block.attrs.items = readItems();
        markDirty();
      });
      list.addEventListener('keydown', function (e) {
        const items = readItems();
        if (e.key === 'Backspace' && items.length === 1 && !stripTags(items[0])) { e.preventDefault(); transformBlock(block.clientId, 'paragraph'); return; }
        if (e.key === 'Escape') { select(null); list.blur(); }
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
          const info = caretInfo(list);
          if (info && info.collapsed && ((e.key === 'ArrowUp' && info.atStart) || (e.key === 'ArrowDown' && info.atEnd))) { e.preventDefault(); navigate(block.clientId, e.key === 'ArrowUp' ? -1 : 1); }
        }
      });
      list.addEventListener('paste', function (e) { e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain')); });
      list.addEventListener('focus', function () { if (state.selected !== block.clientId) select(block.clientId); });
      return list;
    },

    code: function (block) {
      const ta = h('textarea', { class: 'lcms-code', spellcheck: 'false', placeholder: 'Write code…', rows: 3 });
      ta.value = block.content || '';
      autosize(ta);
      ta.addEventListener('input', function () { beforeTextChange(); block.content = ta.value; markDirty(); autosize(ta); });
      ta.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') { e.preventDefault(); const s = ta.selectionStart; ta.setRangeText('  ', s, ta.selectionEnd, 'end'); ta.dispatchEvent(new Event('input')); }
        if (e.key === 'Escape') { select(null); ta.blur(); }
      });
      ta.addEventListener('focus', function () { if (state.selected !== block.clientId) select(block.clientId); });
      const wrap = h('div', { class: 'lcms-code-wrap' }, [h('span', { class: 'lcms-code-lang', text: block.attrs.language || 'plaintext' }), ta]);
      return wrap;
    },

    html: function (block) {
      const wrap = h('div', { class: 'lcms-html-wrap' });
      const ta = h('textarea', { class: 'lcms-code', spellcheck: 'false', placeholder: '<div>Custom HTML…</div>', rows: 3 });
      ta.value = block.content || '';
      autosize(ta);
      const preview = h('div', { class: 'lcms-html-preview', hidden: true });
      const toggle = h('button', { type: 'button', class: 'lcms-mini-btn', text: 'Preview', onclick: function () {
        const showing = !preview.hidden;
        preview.hidden = showing; ta.hidden = !showing;
        toggle.textContent = showing ? 'Preview' : 'HTML';
        if (!showing) preview.innerHTML = block.content;
      } });
      ta.addEventListener('input', function () { beforeTextChange(); block.content = ta.value; markDirty(); autosize(ta); });
      ta.addEventListener('focus', function () { if (state.selected !== block.clientId) select(block.clientId); });
      wrap.append(h('div', { class: 'lcms-html-bar' }, [h('span', { text: 'Custom HTML' }), toggle]), ta, preview);
      return wrap;
    },

    image: function (block) {
      const a = block.attrs;
      if (!a.url) {
        return mediaPlaceholder('image', 'Image', 'Upload an image file, pick one from your media library, or add one with a URL.', function (items) {
          const item = items[0];
          updateAttrs(block.clientId, { url: item.url, alt: item.alt || a.alt || '' }, { refreshInspector: true });
        });
      }
      const fig = h('figure', { class: 'lcms-figure ' + (a.align || '') + (a.href ? ' is-linked' : '') });
      const img = h('img', { src: a.url, alt: a.alt || '', draggable: false });
      if (a.href) {
        // Rendered as a real anchor so alignment/wrapping matches the
        // frontend, but clicks stay inside the editor.
        fig.append(h('a', { href: a.href, class: 'lcms-image-link', title: 'Links to ' + a.href + (a.link_target === '_blank' ? ' (opens in a new tab)' : ''), onclick: e => e.preventDefault() }, [img]));
      } else {
        fig.append(img);
      }
      fig.append(richText(block, 'figcaption', { placeholder: 'Add caption', singleLine: true, get: () => escapeHtml(a.caption || ''), set: html => { a.caption = stripTags(html); } }));
      return fig;
    },

    gallery: function (block) {
      const a = block.attrs;
      const grid = h('div', { class: 'lcms-gallery-grid' });
      (a.images || []).forEach(function (img, i) {
        const cell = h('div', { class: 'lcms-gallery-cell' }, [h('img', { src: img.url, alt: img.alt || '', draggable: false })]);
        cell.append(h('button', { type: 'button', class: 'lcms-cell-remove', title: 'Remove image', html: icon('close', 16), onclick: function (e) { e.stopPropagation(); mutate(function () { a.images.splice(i, 1); }); select(block.clientId, { force: true }); } }));
        grid.append(cell);
      });
      const add = h('button', { type: 'button', class: 'lcms-gallery-add', html: icon('plus', 20) + ' Add images', onclick: function (e) {
        e.stopPropagation();
        openMediaPicker({ multiple: true, accept: 'image', onSelect: function (items) { mutate(function () { a.images = (a.images || []).concat(items.map(i => ({ url: i.url, alt: i.alt || '' }))); }); select(block.clientId, { force: true }); } });
      } });
      grid.append(add);
      if (!(a.images || []).length) return mediaPlaceholder('gallery', 'Gallery', 'Upload images, or pick several from your media library.', function (items) { updateAttrs(block.clientId, { images: items.map(i => ({ url: i.url, alt: i.alt || '' })) }); }, { multiple: true });
      return grid;
    },

    video: function (block) {
      const a = block.attrs;
      if (a.embed) {
        const wrap = h('div', { class: 'lcms-embed-preview' });
        wrap.append(h('div', { class: 'lcms-embed-preview__label', text: 'Embedded video' }));
        wrap.append(h('div', { class: 'lcms-embed-preview__frame', html: a.embed }));
        return wrap;
      }
      if (!a.url) {
        return mediaPlaceholder('video', 'Video', 'Upload a video, pick one from the media library, or paste a URL / embed code in the block sidebar.', function (items) { updateAttrs(block.clientId, { url: items[0].url }, { refreshInspector: true }); }, { accept: 'video' });
      }
      return h('video', { src: a.url, controls: true, class: 'lcms-media-preview' });
    },

    audio: function (block) {
      const a = block.attrs;
      if (!a.url) return mediaPlaceholder('audio', 'Audio', 'Upload an audio file, pick one from the media library, or paste a URL.', function (items) { updateAttrs(block.clientId, { url: items[0].url }, { refreshInspector: true }); }, { accept: 'audio' });
      return h('audio', { src: a.url, controls: true, class: 'lcms-media-preview' });
    },

    table: function (block) {
      const a = block.attrs;
      const table = h('table', { class: 'lcms-table-edit' });
      const tbody = h('tbody');
      (a.rows || []).forEach(function (row, r) {
        const tr = h('tr');
        row.forEach(function (cell, c) {
          const td = h('td', { contenteditable: 'true', class: 'lcms-rt', html: cell, 'data-placeholder': '' });
          td.addEventListener('input', function () { beforeTextChange(); a.rows[r][c] = sanitizeInline(td.innerHTML); markDirty(); });
          td.addEventListener('focus', function () { if (state.selected !== block.clientId) select(block.clientId); });
          td.addEventListener('keydown', function (e) { if (e.key === 'Escape') { select(null); td.blur(); } });
          td.addEventListener('paste', function (e) { e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain')); });
          tr.append(td);
        });
        tbody.append(tr);
      });
      table.append(tbody);
      return h('div', { class: 'lcms-table-wrap' }, [table]);
    },

    separator: () => h('hr', { class: 'lcms-separator' }),

    spacer: block => h('div', { class: 'lcms-spacer-preview', style: { height: (block.attrs.height || 40) + 'px' } }, [h('span', { text: (block.attrs.height || 40) + 'px' })]),

    columns: function (block) {
      const wrap = h('div', { class: 'lcms-columns-edit', style: { gridTemplateColumns: 'repeat(' + (block.attrs.columns || []).length + ', 1fr)' } });
      (block.attrs.columns || []).forEach(function (col) {
        const colEl = h('div', { class: 'lcms-column-edit' });
        renderList(col.blocks, colEl, block);
        wrap.append(colEl);
      });
      return wrap;
    },

    embed: function (block) {
      const a = block.attrs;
      const wrap = h('div', { class: 'lcms-embed-card' });
      const input = h('input', { type: 'url', class: 'lcms-embed-input', placeholder: 'Paste a YouTube, Vimeo, Twitter or Instagram URL…', value: a.url || '' });
      input.addEventListener('input', function () { beforeTextChange(); a.url = input.value.trim(); markDirty(); });
      input.addEventListener('focus', function () { if (state.selected !== block.clientId) select(block.clientId); });
      wrap.append(h('div', { class: 'lcms-embed-card__head', html: icon('embed', 20) + '<span>Embed</span>' }), input);
      if (a.url) wrap.append(h('div', { class: 'lcms-hint', text: 'The theme renders this URL as an embed on the frontend.' }));
      return wrap;
    },

    accordion: function (block) {
      const a = block.attrs;
      const wrap = h('div', { class: 'lcms-accordion-edit' });
      (a.items || []).forEach(function (item, i) {
        const row = h('div', { class: 'lcms-accordion-item' });
        const title = h('input', { type: 'text', class: 'lcms-accordion-title', placeholder: 'Question / title', value: item.title || '' });
        title.addEventListener('input', function () { beforeTextChange(); item.title = title.value; markDirty(); });
        title.addEventListener('focus', function () { if (state.selected !== block.clientId) select(block.clientId); });
        const body = richText(block, 'div', { class: 'lcms-accordion-body', placeholder: 'Answer / content', get: () => item.content || '', set: html => { item.content = html; } });
        const remove = h('button', { type: 'button', class: 'lcms-mini-btn lcms-mini-btn--danger', title: 'Remove item', html: icon('trash', 16), onclick: function () { mutate(function () { a.items.splice(i, 1); if (!a.items.length) a.items.push({ title: '', content: '' }); }); select(block.clientId, { force: true }); } });
        row.append(h('div', { class: 'lcms-accordion-head' }, [title, remove]), body);
        wrap.append(row);
      });
      wrap.append(h('button', { type: 'button', class: 'lcms-mini-btn', html: icon('plus', 16) + ' Add item', onclick: function () { mutate(function () { a.items.push({ title: '', content: '' }); }); select(block.clientId, { force: true }); } }));
      return wrap;
    },

    cta: function (block) {
      const a = block.attrs;
      const wrap = h('div', { class: 'lcms-cta lcms-cta-edit' });
      wrap.append(richText(block, 'h3', { placeholder: 'Call to action title', singleLine: true, get: () => escapeHtml(a.title || ''), set: html => { a.title = stripTags(html); } }));
      wrap.append(richText(block, 'p', { placeholder: 'Supporting text', singleLine: true, get: () => escapeHtml(a.text || ''), set: html => { a.text = stripTags(html); } }));
      wrap.append(richText(block, 'span', { class: 'lcms-btn lcms-btn--primary', placeholder: 'Button label', singleLine: true, get: () => escapeHtml(a.button_label || ''), set: html => { a.button_label = stripTags(html); } }));
      return wrap;
    }
  };

  function autosize(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.max(60, ta.scrollHeight + 2) + 'px';
  }

  function mediaPlaceholder(iconName, label, hint, onSelect, opts) {
    opts = opts || {};
    const box = h('div', { class: 'lcms-placeholder lcms-placeholder--media' });
    box.append(h('div', { class: 'lcms-placeholder__head', html: icon(iconName, 22) + '<strong>' + escapeHtml(label) + '</strong>' }));
    box.append(h('p', { class: 'lcms-placeholder__hint', text: hint }));
    const actions = h('div', { class: 'lcms-placeholder__actions' });
    actions.append(h('button', { type: 'button', class: 'lcms-btn lcms-btn--primary', text: 'Upload', onclick: e => { e.stopPropagation(); openMediaPicker({ tab: 'upload', multiple: !!opts.multiple, accept: opts.accept || 'image', onSelect: onSelect }); } }));
    actions.append(h('button', { type: 'button', class: 'lcms-btn', text: 'Media Library', onclick: e => { e.stopPropagation(); openMediaPicker({ tab: 'library', multiple: !!opts.multiple, accept: opts.accept || 'image', onSelect: onSelect }); } }));
    actions.append(h('button', { type: 'button', class: 'lcms-btn', text: 'Insert from URL', onclick: e => { e.stopPropagation(); openMediaPicker({ tab: 'url', multiple: false, accept: opts.accept || 'image', onSelect: onSelect }); } }));
    box.append(actions);
    return box;
  }

  /* ================================================================== */
  /*  Block toolbar                                                      */
  /* ================================================================== */

  function tbButton(name, title, onclick, extra) {
    extra = extra || {};
    return h('button', Object.assign({ type: 'button', class: 'lcms-tb__btn' + (extra.active ? ' is-active' : ''), title: title, 'aria-label': title, html: (extra.html || icon(name, 20)), onmousedown: e => e.preventDefault(), onclick: function (e) { e.stopPropagation(); onclick(e); } }, extra.attrs || {}));
  }

  function buildToolbar(found) {
    const block = found.block;
    const tb = h('div', { class: 'lcms-blk__toolbar', onmousedown: e => e.stopPropagation() });
    const def = TYPES[block.type] || { icon: 'info', label: block.type };

    // type switcher / transforms
    const transforms = TRANSFORMS[block.type] || [];
    const typeBtn = tbButton(def.icon, def.label + (transforms.length ? ' — change block type' : ''), function () {
      if (!transforms.length) return;
      openMenu(typeBtn, transforms.map(t => ({ icon: TYPES[t].icon, label: TYPES[t].label, onPick: () => transformBlock(block.clientId, t) })), 'Transform to');
    }, { html: icon(def.icon, 20) + (transforms.length ? '<span class="lcms-tb__caret">▾</span>' : '') });
    tb.append(h('div', { class: 'lcms-tb__group' }, [typeBtn]));

    // move / drag
    const handle = tbButton('drag', 'Drag to move', () => {}, { attrs: { draggable: true } });
    handle.addEventListener('dragstart', function (e) { state.dragging = block.clientId; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', block.clientId); const wrap = wrapperFor(block.clientId); if (wrap) { wrap.classList.add('is-dragging'); e.dataTransfer.setDragImage(wrap, 20, 20); } });
    handle.addEventListener('dragend', function () { const wrap = wrapperFor(block.clientId); if (wrap) wrap.classList.remove('is-dragging'); state.dragging = null; $$('.is-drop, .is-drop-before, .is-drop-after', dom.canvas).forEach(n => n.classList.remove('is-drop', 'is-drop-before', 'is-drop-after')); });
    tb.append(h('div', { class: 'lcms-tb__group' }, [
      handle,
      tbButton('up', 'Move up', () => moveBlock(block.clientId, -1), { attrs: { disabled: found.index === 0 } }),
      tbButton('down', 'Move down', () => moveBlock(block.clientId, 1), { attrs: { disabled: found.index === found.list.length - 1 } })
    ]));

    // type-specific
    const specific = h('div', { class: 'lcms-tb__group' });
    if (block.type === 'heading') {
      const lvl = block.attrs.level || 2;
      const lvlBtn = tbButton('heading', 'Heading level', function () {
        openMenu(lvlBtn, [1, 2, 3, 4, 5, 6].map(n => ({ label: 'Heading ' + n, html: '<b>H' + n + '</b>', active: n === lvl, onPick: () => updateAttrs(block.clientId, { level: n }, { refreshInspector: true }) })), 'Level');
      }, { html: '<b class="lcms-tb__text">H' + lvl + '</b><span class="lcms-tb__caret">▾</span>' });
      specific.append(lvlBtn);
    }
    if (block.type === 'list') {
      specific.append(tbButton('list', 'Unordered list', () => updateAttrs(block.clientId, { ordered: false }), { active: !block.attrs.ordered }));
      specific.append(tbButton('ordered', 'Ordered list', () => updateAttrs(block.clientId, { ordered: true }), { active: !!block.attrs.ordered }));
    }
    if (block.type === 'image' && block.attrs.url) {
      [['', 'alignnone', 'No alignment'], ['alignleft', 'alignleft', 'Align left'], ['aligncenter', 'aligncenter', 'Align center'], ['alignright', 'alignright', 'Align right'], ['alignwide', 'alignwide', 'Wide width']].forEach(function (opt) {
        specific.append(tbButton(opt[1], opt[2], () => updateAttrs(block.clientId, { align: opt[0] }, { refreshInspector: true }), { active: (block.attrs.align || '') === opt[0] }));
      });
      const linkBtn = tbButton('link', block.attrs.href ? 'Edit link' : 'Insert link', function () {
        openLinkPopover(linkBtn, { href: block.attrs.href, target: block.attrs.link_target, rel: block.attrs.rel }, function (values) {
          updateAttrs(block.clientId, { href: values.href, link_target: values.target, rel: values.rel }, { refreshInspector: true });
        }, { mediaUrl: block.attrs.url });
      }, { active: !!block.attrs.href });
      specific.append(linkBtn);
      specific.append(tbButton('image', 'Replace image', () => openMediaPicker({ accept: 'image', onSelect: items => updateAttrs(block.clientId, { url: items[0].url, alt: items[0].alt || block.attrs.alt }, { refreshInspector: true }) })));
    }
    if (block.type === 'table') {
      specific.append(tbButton('addrow', 'Add row', () => { mutate(function () { const cols = (block.attrs.rows[0] || ['']).length; block.attrs.rows.push(new Array(cols).fill('')); }); select(block.clientId, { force: true }); }));
      specific.append(tbButton('addcol', 'Add column', () => { mutate(function () { block.attrs.rows.forEach(r => r.push('')); }); select(block.clientId, { force: true }); }));
      specific.append(tbButton('trash', 'Remove last row', () => { if (block.attrs.rows.length <= 1) return; mutate(function () { block.attrs.rows.pop(); }); select(block.clientId, { force: true }); }, { html: icon('addrow', 20) + '<span class="lcms-tb__minus">−</span>' }));
      specific.append(tbButton('trash', 'Remove last column', () => { if ((block.attrs.rows[0] || []).length <= 1) return; mutate(function () { block.attrs.rows.forEach(r => r.pop()); }); select(block.clientId, { force: true }); }, { html: icon('addcol', 20) + '<span class="lcms-tb__minus">−</span>' }));
    }
    if (specific.children.length) tb.append(specific);

    // inline formatting for rich text blocks
    if (isText(block) || block.type === 'list' || block.type === 'table' || block.type === 'accordion') {
      tb.append(h('div', { class: 'lcms-tb__group' }, [
        tbButton('bold', 'Bold (⌘B)', () => format('bold')),
        tbButton('italic', 'Italic (⌘I)', () => format('italic')),
        tbButton('link', 'Link (⌘K)', () => format('link')),
        tbButton('strike', 'Strikethrough', () => format('strike')),
        tbButton('code', 'Inline code', () => format('code'))
      ]));
    }

    // more menu
    const moreBtn = tbButton('more', 'Options', function () {
      openMenu(moreBtn, [
        { icon: 'copy', label: 'Duplicate', hint: '⇧⌘D', onPick: () => duplicateBlock(block.clientId) },
        { icon: 'plus', label: 'Insert before', onPick: () => insertBlock('paragraph', found.list, found.index) },
        { icon: 'plus', label: 'Insert after', onPick: () => insertBlock('paragraph', found.list, found.index + 1) },
        { icon: 'copy', label: 'Copy block JSON', onPick: () => copyText(JSON.stringify(serialize([block])[0], null, 2)) },
        { icon: 'trash', label: 'Remove block', hint: '⌥⇧Z', danger: true, onPick: () => removeBlock(block.clientId) }
      ]);
    });
    tb.append(h('div', { class: 'lcms-tb__group' }, [moreBtn]));
    return tb;
  }

  function copyText(text) {
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(() => toast('Copied to clipboard'));
    else { window.prompt('Copy:', text); }
  }

  /* ================================================================== */
  /*  Drag & drop                                                        */
  /* ================================================================== */

  function onGapDragOver(e, el) { if (!state.dragging) return; e.preventDefault(); e.stopPropagation(); e.dataTransfer.dropEffect = 'move'; el.classList.add('is-drop'); }
  function onGapDrop(e, list, index, el) { if (!state.dragging) return; e.preventDefault(); e.stopPropagation(); el.classList.remove('is-drop'); moveBlockTo(state.dragging, list, index); state.dragging = null; }
  function onBlockDragOver(e, wrap) {
    if (!state.dragging || wrap.dataset.clientId === state.dragging) return;
    e.preventDefault(); e.stopPropagation();
    const rect = wrap.getBoundingClientRect();
    const before = (e.clientY - rect.top) < rect.height / 2;
    wrap.classList.toggle('is-drop-before', before);
    wrap.classList.toggle('is-drop-after', !before);
  }
  function onBlockDrop(e, block, wrap) {
    if (!state.dragging) return;
    e.preventDefault(); e.stopPropagation();
    const before = wrap.classList.contains('is-drop-before');
    wrap.classList.remove('is-drop-before', 'is-drop-after');
    const target = locate(block.clientId);
    if (!target) return;
    moveBlockTo(state.dragging, target.list, target.index + (before ? 0 : 1));
    state.dragging = null;
  }

  /* ================================================================== */
  /*  Popovers: inserter, menus, toasts                                  */
  /* ================================================================== */

  const slash = { open: false, blockId: null, filter: null };
  let activePopover = null;

  function closePopovers() {
    if (activePopover) { activePopover.remove(); activePopover = null; }
    slash.open = false; slash.blockId = null; slash.filter = null;
  }

  function positionPopover(pop, anchor) {
    const rect = anchor.getBoundingClientRect();
    document.body.append(pop);
    const pw = pop.offsetWidth, ph = pop.offsetHeight;
    let top = rect.bottom + 8 + window.scrollY, left = rect.left + window.scrollX;
    if (rect.bottom + ph + 16 > window.innerHeight && rect.top - ph - 8 > 0) top = rect.top - ph - 8 + window.scrollY;
    if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
    pop.style.top = top + 'px';
    pop.style.left = left + 'px';
  }

  function openInserter(opts) {
    closePopovers();
    const pop = h('div', { class: 'lcms-popover lcms-inserter', onmousedown: e => e.stopPropagation() });
    const search = h('input', { type: 'search', class: 'lcms-inserter__search', placeholder: 'Search blocks…', autocomplete: 'off' });
    const listEl = h('div', { class: 'lcms-inserter__list' });
    pop.append(h('div', { class: 'lcms-inserter__head' }, [h('span', { html: icon('search', 18) }), search]), listEl);

    function draw(filter) {
      listEl.innerHTML = '';
      const q = (filter || '').trim().toLowerCase();
      let any = false;
      CATEGORIES.forEach(function (cat) {
        const items = Object.keys(TYPES).filter(function (t) {
          if (TYPES[t].category !== cat[0]) return false;
          if (!q) return true;
          return (TYPES[t].label + ' ' + t + ' ' + TYPES[t].keywords).toLowerCase().includes(q);
        });
        if (!items.length) return;
        any = true;
        const group = h('div', { class: 'lcms-inserter__group' });
        group.append(h('div', { class: 'lcms-inserter__cat', text: cat[1] }));
        const grid = h('div', { class: 'lcms-inserter__grid' });
        items.forEach(function (t) {
          grid.append(h('button', { type: 'button', class: 'lcms-inserter__item', dataset: { type: t }, html: icon(TYPES[t].icon, 24) + '<span>' + escapeHtml(TYPES[t].label) + '</span>', onclick: function () { const pick = opts.onPick; closePopovers(); pick(t); } }));
        });
        group.append(grid);
        listEl.append(group);
      });
      if (!any) listEl.append(h('div', { class: 'lcms-inserter__empty', text: 'No blocks found.' }));
    }
    draw(opts.filter || '');
    search.addEventListener('input', () => draw(search.value));
    pop.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); closePopovers(); }
      if (e.key === 'Enter') { const first = listEl.querySelector('.lcms-inserter__item'); if (first) { e.preventDefault(); first.click(); } }
    });

    if (opts.slashFor) {
      slash.open = true; slash.blockId = opts.slashFor; slash.filter = draw;
      search.parentElement.hidden = true;
    }
    activePopover = pop;
    positionPopover(pop, opts.anchor);
    if (!opts.slashFor) search.focus();
  }

  function filterInserter(text) { if (slash.filter) slash.filter(text); }

  /**
   * Small link editor used by the image block's toolbar button.
   * values: { href, target, rel } — onApply gets the same shape back.
   */
  function openLinkPopover(anchor, values, onApply, opts) {
    opts = opts || {};
    closePopovers();
    const pop = h('div', { class: 'lcms-popover lcms-linkpop', onmousedown: e => e.stopPropagation() });
    const url = h('input', { type: 'text', placeholder: 'Paste or type a URL…', value: values.href || '', autocomplete: 'off', spellcheck: 'false' });
    const newTab = h('input', { type: 'checkbox', checked: values.target === '_blank' });
    const nofollow = h('input', { type: 'checkbox', checked: /(^|\s)nofollow(\s|$)/i.test(values.rel || '') });

    function apply() {
      const href = normalizeHref(url.value);
      if (url.value.trim() && !href) { toast('Only http(s), mailto:, tel: or site-relative links are allowed', 'error'); return; }
      closePopovers();
      onApply({ href: href, target: newTab.checked ? '_blank' : '', rel: toggleRelToken(values.rel, 'nofollow', nofollow.checked) });
    }

    url.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); apply(); } });

    pop.append(h('div', { class: 'lcms-linkpop__row' }, [url, h('button', { type: 'button', class: 'lcms-btn lcms-btn--primary', text: 'Apply', onclick: apply })]));
    if (opts.mediaUrl) pop.append(h('button', { type: 'button', class: 'lcms-mini-btn', text: 'Link to the image file', onclick: function () { url.value = opts.mediaUrl; url.focus(); } }));
    pop.append(h('label', { class: 'lcms-check' }, [newTab, 'Open in a new tab']));
    pop.append(h('label', { class: 'lcms-check' }, [nofollow, 'Add rel="nofollow"']));
    if (values.href) pop.append(h('button', { type: 'button', class: 'lcms-mini-btn lcms-mini-btn--danger', text: 'Remove link', onclick: function () { closePopovers(); onApply({ href: '', target: '', rel: toggleRelToken(values.rel, 'nofollow', false) }); } }));

    activePopover = pop;
    positionPopover(pop, anchor);
    url.focus();
    url.select();
  }

  function openMenu(anchor, items, heading) {
    closePopovers();
    const pop = h('div', { class: 'lcms-popover lcms-menu', onmousedown: e => e.stopPropagation() });
    if (heading) pop.append(h('div', { class: 'lcms-menu__heading', text: heading }));
    items.forEach(function (item) {
      pop.append(h('button', { type: 'button', class: 'lcms-menu__item' + (item.active ? ' is-active' : '') + (item.danger ? ' is-danger' : ''), html: (item.html || icon(item.icon || 'info', 20)) + '<span>' + escapeHtml(item.label) + '</span>' + (item.hint ? '<kbd>' + escapeHtml(item.hint) + '</kbd>' : ''), onclick: function () { closePopovers(); item.onPick(); } }));
    });
    activePopover = pop;
    positionPopover(pop, anchor);
    const first = pop.querySelector('button'); if (first) first.focus();
    pop.addEventListener('keydown', e => { if (e.key === 'Escape') closePopovers(); });
  }

  function toast(message, kind) {
    const el = h('div', { class: 'lcms-toast' + (kind ? ' lcms-toast--' + kind : ''), text: message });
    document.body.append(el);
    setTimeout(() => el.classList.add('is-visible'), 10);
    setTimeout(() => { el.classList.remove('is-visible'); setTimeout(() => el.remove(), 300); }, 2600);
  }

  /* ================================================================== */
  /*  Media picker (upload / library / URL)                              */
  /* ================================================================== */

  function openMediaPicker(opts) {
    opts = opts || {};
    const accept = opts.accept || 'image';
    const multiple = !!opts.multiple;
    let selected = [];

    const overlay = h('div', { class: 'lcms-modal', onmousedown: e => { if (e.target === overlay) close(); } });
    const modal = h('div', { class: 'lcms-modal__box', role: 'dialog', 'aria-modal': 'true' });
    const tabs = h('div', { class: 'lcms-modal__tabs' });
    const body = h('div', { class: 'lcms-modal__body' });
    const footer = h('div', { class: 'lcms-modal__footer' });
    const selectBtn = h('button', { type: 'button', class: 'lcms-btn lcms-btn--primary', text: 'Select', disabled: true, onclick: function () { if (selected.length) { opts.onSelect(selected); close(); } } });
    footer.append(h('button', { type: 'button', class: 'lcms-btn', text: 'Cancel', onclick: close }), selectBtn);
    modal.append(h('div', { class: 'lcms-modal__head' }, [h('strong', { text: 'Select ' + (multiple ? 'media' : accept) }), h('button', { type: 'button', class: 'lcms-tb__btn', 'aria-label': 'Close', html: icon('close', 20), onclick: close })]), tabs, body, footer);
    overlay.append(modal);
    document.body.append(overlay);

    function close() { overlay.remove(); document.removeEventListener('keydown', onKey); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', onKey);

    const views = {
      upload: function () {
        const input = h('input', { type: 'file', accept: accept + '/*', multiple: multiple, hidden: true });
        const zone = h('div', { class: 'lcms-dropzone', html: icon('upload', 32) + '<p><strong>Drop files here</strong> or click to choose</p><p class="lcms-hint">' + (accept === 'image' ? 'JPEG/PNG/GIF are auto-resized, compressed, converted to WebP and thumbnailed on upload.' : 'Files are stored as uploaded.') + '</p>', onclick: () => input.click() });
        const status = h('p', { class: 'lcms-hint' });
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('is-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
        zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('is-over'); doUpload(Array.from(e.dataTransfer.files)); });
        input.addEventListener('change', () => doUpload(Array.from(input.files)));
        async function doUpload(files) {
          if (!files.length) return;
          const results = [];
          for (let i = 0; i < files.length; i++) {
            status.textContent = 'Uploading ' + (i + 1) + ' of ' + files.length + '…';
            try { results.push(await uploadFile(files[i])); }
            catch (err) { toast(err.message || 'Upload failed', 'error'); }
          }
          status.textContent = '';
          if (results.length) { opts.onSelect(multiple ? results : [results[0]]); close(); }
        }
        return h('div', {}, [zone, input, status]);
      },
      library: function () {
        const wrap = h('div', {});
        const grid = h('div', { class: 'lcms-media-grid lcms-media-grid--picker' }, [h('p', { class: 'lcms-hint', text: 'Loading…' })]);
        wrap.append(grid);
        fetch(config.endpoints.mediaList + '?type=' + encodeURIComponent(accept), { credentials: 'same-origin' })
          .then(r => r.json())
          .then(function (data) {
            grid.innerHTML = '';
            if (!data.items || !data.items.length) { grid.append(h('p', { class: 'lcms-hint', text: 'No ' + accept + ' files in the library yet.' })); return; }
            data.items.forEach(function (item) {
              const cell = h('button', { type: 'button', class: 'lcms-media-item lcms-media-item--pick', title: item.filename });
              cell.append(accept === 'image' ? h('img', { src: item.thumb || item.url, alt: item.alt || '' }) : h('div', { class: 'lcms-media-item__icon', text: item.filename }));
              cell.append(h('span', { class: 'lcms-media-item__check', html: icon('check', 16) }));
              cell.addEventListener('click', function () {
                const idx = selected.findIndex(s => s.id === item.id);
                if (!multiple) { selected = idx >= 0 ? [] : [item]; $$('.lcms-media-item--pick', grid).forEach(c => c.classList.remove('is-selected')); }
                else if (idx >= 0) selected.splice(idx, 1);
                if (idx < 0) { if (!multiple) selected = [item]; else selected.push(item); cell.classList.add('is-selected'); } else cell.classList.remove('is-selected');
                selectBtn.disabled = selected.length === 0;
              });
              grid.append(cell);
            });
          })
          .catch(() => { grid.innerHTML = ''; grid.append(h('p', { class: 'lcms-hint', text: 'Could not load the media library.' })); });
        return wrap;
      },
      url: function () {
        const input = h('input', { type: 'url', placeholder: 'https://example.com/file.' + (accept === 'image' ? 'jpg' : accept === 'video' ? 'mp4' : 'mp3'), autofocus: true });
        const alt = accept === 'image' ? h('input', { type: 'text', placeholder: 'Alt text (describe the image)' }) : null;
        const form = h('div', { class: 'lcms-url-form' }, [h('label', { text: 'File URL' }), input, alt ? h('label', { text: 'Alt text' }) : null, alt, h('button', { type: 'button', class: 'lcms-btn lcms-btn--primary', text: 'Insert', onclick: function () { const url = safeHref(input.value); if (!url) { toast('Enter a valid http(s) URL', 'error'); return; } opts.onSelect([{ url: url, alt: alt ? alt.value : '' }]); close(); } })]);
        setTimeout(() => input.focus(), 0);
        return form;
      }
    };

    let current = null;
    function show(name) {
      current = name;
      $$('button', tabs).forEach(b => b.classList.toggle('is-active', b.dataset.tab === name));
      body.innerHTML = '';
      body.append(views[name]());
      footer.style.display = name === 'library' ? '' : 'none';
    }
    [['upload', 'Upload'], ['library', 'Media Library'], ['url', 'Insert from URL']].forEach(function (t) {
      if (t[0] === 'url' && multiple) return;
      tabs.append(h('button', { type: 'button', dataset: { tab: t[0] }, text: t[1], onclick: () => show(t[0]) }));
    });
    show(opts.tab || 'upload');
  }

  async function uploadFile(file) {
    const data = new FormData();
    data.append('file', file);
    data.append(config.csrf.name, config.csrf.hash);
    const res = await fetch(config.endpoints.upload, { method: 'POST', body: data, credentials: 'same-origin' });
    let json = {};
    try { json = await res.json(); } catch (e) { /* non-JSON error page */ }
    if (!res.ok || json.error) throw new Error(json.error || ('Upload failed (' + res.status + ')'));
    return { id: json.id, url: json.url, alt: json.alt || titleCase(file.name.replace(/\.[^.]+$/, '')), variants: json.variants || {} };
  }

  /* ================================================================== */
  /*  Sidebar                                                            */
  /* ================================================================== */

  function renderSidebarTabs() {
    if (!dom.tabPost) return;
    dom.tabPost.classList.toggle('is-active', state.tab === 'post');
    dom.tabBlock.classList.toggle('is-active', state.tab === 'block');
    dom.panelPost.hidden = state.tab !== 'post';
    dom.panelBlock.hidden = state.tab !== 'block';
    dom.sidebar.classList.toggle('is-open', state.sidebarOpen);
    document.body.classList.toggle('lcms-sidebar-open', state.sidebarOpen);
  }

  function field(label, control, hint) {
    const wrap = h('div', { class: 'lcms-field' });
    wrap.append(h('label', { text: label }));
    wrap.append(control);
    if (hint) wrap.append(h('span', { class: 'lcms-hint', text: hint }));
    return wrap;
  }

  function textField(block, label, key, opts) {
    opts = opts || {};
    const input = h(opts.textarea ? 'textarea' : 'input', Object.assign({ type: opts.type || 'text', value: opts.textarea ? undefined : (block.attrs[key] == null ? '' : block.attrs[key]), placeholder: opts.placeholder || '', rows: opts.textarea ? 3 : undefined }));
    if (opts.textarea) input.value = block.attrs[key] || '';
    input.addEventListener('input', function () {
      beforeTextChange();
      block.attrs[key] = opts.type === 'number' ? parseInt(input.value, 10) || 0 : input.value;
      markDirty();
      refreshBlock(block.clientId);
    });
    if (opts.normalize) {
      input.addEventListener('blur', function () {
        const value = opts.normalize(input.value);
        if (value === input.value) return;
        if (input.value.trim() && !value) toast('Only http(s), mailto:, tel: or site-relative links are allowed', 'error');
        input.value = value;
        block.attrs[key] = value;
        markDirty();
        refreshBlock(block.clientId);
      });
    }
    return field(label, input, opts.hint);
  }

  function checkboxField(block, label, key, onValue, offValue) {
    const input = h('input', { type: 'checkbox', checked: block.attrs[key] === onValue });
    input.addEventListener('change', function () { updateAttrs(block.clientId, { [key]: input.checked ? onValue : offValue }); });
    return h('label', { class: 'lcms-check' }, [input, label]);
  }

  function selectField(block, label, key, options, onChange) {
    const sel = h('select');
    options.forEach(o => sel.append(h('option', { value: o[0], selected: String(block.attrs[key]) === String(o[0]), text: o[1] })));
    sel.addEventListener('change', function () { const v = onChange ? onChange(sel.value) : sel.value; updateAttrs(block.clientId, { [key]: v }, { refreshInspector: true }); });
    return field(label, sel);
  }

  function mediaField(block, label, key, accept) {
    const wrap = h('div', { class: 'lcms-field' });
    wrap.append(h('label', { text: label }));
    const input = h('input', { type: 'text', value: block.attrs[key] || '', placeholder: 'https://…' });
    input.addEventListener('input', function () { beforeTextChange(); block.attrs[key] = input.value.trim(); markDirty(); refreshBlock(block.clientId); });
    const row = h('div', { class: 'lcms-field__row' }, [input, h('button', { type: 'button', class: 'lcms-btn', text: 'Choose', onclick: () => openMediaPicker({ accept: accept, onSelect: items => updateAttrs(block.clientId, { [key]: items[0].url }, { refreshInspector: true }) }) })]);
    wrap.append(row);
    return wrap;
  }

  function renderInspector() {
    if (!dom.panelBlock) return;
    dom.panelBlock.innerHTML = '';
    const found = state.selected ? locate(state.selected) : null;
    if (!found) { dom.panelBlock.append(h('p', { class: 'lcms-hint lcms-inspector-empty', text: 'Select a block to edit its settings.' })); return; }
    const block = found.block;
    const def = TYPES[block.type] || { label: block.type, icon: 'info' };
    dom.panelBlock.append(h('div', { class: 'lcms-inspector__head', html: icon(def.icon, 24) + '<div><strong>' + escapeHtml(def.label) + '</strong><span class="lcms-hint">' + escapeHtml(inspectorHint(block.type)) + '</span></div>' }));
    const panel = h('div', { class: 'lcms-inspector__fields' });
    const a = block.attrs;

    switch (block.type) {
      case 'heading':
        panel.append(selectField(block, 'Level', 'level', [1, 2, 3, 4, 5, 6].map(n => [n, 'H' + n]), v => parseInt(v, 10)));
        break;
      case 'quote':
        panel.append(textField(block, 'Citation', 'cite'));
        break;
      case 'code':
        panel.append(textField(block, 'Language', 'language', { placeholder: 'php, js, css…', hint: 'Used as the language-* class for syntax highlighting.' }));
        break;
      case 'image':
        panel.append(mediaField(block, 'Image URL', 'url', 'image'));
        panel.append(textField(block, 'Alt text', 'alt', { textarea: true, hint: 'Describe the image for screen readers and SEO. Decorative images can stay empty.' }));
        panel.append(textField(block, 'Caption', 'caption'));
        panel.append(selectField(block, 'Alignment', 'align', [['', 'None'], ['alignleft', 'Left'], ['aligncenter', 'Center'], ['alignright', 'Right'], ['alignwide', 'Wide']]));
        panel.append(h('div', { class: 'lcms-inspector__sub', text: 'Link' }));
        panel.append(textField(block, 'Link URL', 'href', { placeholder: 'https://example.com/page', normalize: normalizeHref, hint: 'Wraps the image in a link (a backlink, for example). http(s), mailto:, tel: or site-relative only.' }));
        panel.append(checkboxField(block, 'Open in a new tab', 'link_target', '_blank', ''));
        panel.append(textField(block, 'Link rel', 'rel', { placeholder: 'nofollow sponsored ugc', hint: 'noopener noreferrer is added automatically for new-tab links.' }));
        break;
      case 'gallery':
        panel.append(h('p', { class: 'lcms-hint', text: (a.images || []).length + ' image(s). Use the block canvas to add or remove images.' }));
        break;
      case 'video':
        panel.append(mediaField(block, 'Video URL', 'url', 'video'));
        panel.append(textField(block, 'Embed code', 'embed', { textarea: true, placeholder: '<iframe …></iframe>', hint: 'Takes precedence over the URL when set (YouTube/Vimeo embed snippet).' }));
        break;
      case 'audio':
        panel.append(mediaField(block, 'Audio URL', 'url', 'audio'));
        break;
      case 'button':
        panel.append(textField(block, 'Link URL', 'url', { type: 'url', placeholder: 'https://…' }));
        panel.append(selectField(block, 'Style', 'style', [['primary', 'Primary'], ['secondary', 'Secondary']]));
        break;
      case 'spacer':
        panel.append(textField(block, 'Height (px)', 'height', { type: 'number' }));
        break;
      case 'columns': {
        const count = (a.columns || []).length;
        panel.append(selectField(block, 'Columns', 'columns', [1, 2, 3, 4].map(n => [n, n + ' column' + (n > 1 ? 's' : '')]), function (v) {
          const n = parseInt(v, 10);
          const cols = a.columns.slice(0, n);
          // blocks from removed columns are moved into the last remaining column
          a.columns.slice(n).forEach(c => { cols[cols.length - 1].blocks.push(...c.blocks); });
          while (cols.length < n) cols.push({ blocks: [] });
          return cols;
        }));
        panel.append(h('p', { class: 'lcms-hint', text: count + ' column(s). Reducing the count moves those blocks into the last column.' }));
        break;
      }
      case 'embed':
        panel.append(textField(block, 'URL', 'url', { type: 'url' }));
        break;
      case 'table':
        panel.append(h('p', { class: 'lcms-hint', text: (a.rows || []).length + ' × ' + ((a.rows && a.rows[0]) || []).length + '. Add/remove rows and columns from the block toolbar.' }));
        break;
      case 'cta':
        panel.append(textField(block, 'Button URL', 'url', { type: 'url' }));
        panel.append(textField(block, 'Button label', 'button_label'));
        break;
      case 'accordion':
        panel.append(h('p', { class: 'lcms-hint', text: (a.items || []).length + ' item(s). Two or more Q/A pairs also get FAQ schema automatically on save.' }));
        break;
      default:
        panel.append(h('p', { class: 'lcms-hint', text: 'This block has no extra settings.' }));
    }
    dom.panelBlock.append(panel);

    const actions = h('div', { class: 'lcms-inspector__actions' });
    actions.append(h('button', { type: 'button', class: 'lcms-btn', text: 'Duplicate', onclick: () => duplicateBlock(block.clientId) }));
    actions.append(h('button', { type: 'button', class: 'lcms-btn lcms-btn--danger', text: 'Remove block', onclick: () => removeBlock(block.clientId) }));
    dom.panelBlock.append(actions);
  }

  function inspectorHint(type) {
    return {
      paragraph: 'Start with the basic building block of all narrative.',
      heading: 'Introduce new sections and organize content.',
      list: 'Create a bulleted or numbered list.',
      quote: 'Give quoted text visual emphasis.',
      code: 'Display code snippets that respect your spacing.',
      image: 'Insert an image to make a visual statement.',
      gallery: 'Display multiple images in a rich gallery.',
      video: 'Embed a video from your library or a URL.',
      audio: 'Embed a simple audio player.',
      button: 'Prompt visitors to take action with a button.',
      table: 'Create structured content in rows and columns.',
      columns: 'Display content in multiple columns.',
      separator: 'Create a break between ideas.',
      spacer: 'Add white space between blocks.',
      embed: 'Embed videos, images, tweets and more.',
      html: 'Add custom HTML code and preview it.',
      accordion: 'Collapsible Q/A items — great for FAQs.',
      cta: 'A highlighted call-to-action box.'
    }[type] || '';
  }

  /* ================================================================== */
  /*  Code editor mode                                                   */
  /* ================================================================== */

  function renderCodeMode() {
    dom.canvas.innerHTML = '';
    const ta = h('textarea', { class: 'lcms-code-mode', spellcheck: 'false' });
    // rawOverride is set when the stored content didn't parse — show the
    // author exactly what's in the database so they can repair it.
    ta.value = state.rawOverride != null ? state.rawOverride : JSON.stringify(serialize(state.blocks), null, 2);
    state.rawOverride = null;
    ta.addEventListener('input', function () { autosize(ta); dom.output.value = ta.value; state.dirty = true; setSaveIndicator('Unsaved changes'); });
    dom.canvas.append(h('p', { class: 'lcms-hint', text: 'Editing the raw block JSON. Switch back to the visual editor to apply it.' }), ta);
    autosize(ta);
    dom.codeTextarea = ta;
  }

  function setMode(mode) {
    if (mode === state.mode) return;
    if (state.mode === 'code' && dom.codeTextarea) {
      try {
        const parsed = JSON.parse(dom.codeTextarea.value);
        if (!Array.isArray(parsed)) throw new Error('Top level must be an array');
        pushHistory();
        state.blocks = withIds(parsed.filter(b => b && typeof b.type === 'string'));
        markDirty();
      } catch (err) { toast('Invalid JSON: ' + err.message, 'error'); return; }
    }
    state.mode = mode;
    state.selected = null;
    document.body.classList.toggle('lcms-mode-code', mode === 'code');
    if (dom.modeLabel) dom.modeLabel.textContent = mode === 'code' ? 'Visual editor' : 'Code editor';
    render();
  }

  /* ================================================================== */
  /*  Autosave (localStorage) + SEO analysis                             */
  /* ================================================================== */

  function storageKey() { return 'lcms-autosave-' + (config.postId ? 'post-' + config.postId : 'new-' + config.postType); }

  const scheduleLocalSave = debounce(saveLocal, 2000);

  function saveLocal() {
    if (!state.dirty || state.submitting) return;
    try {
      localStorage.setItem(storageKey(), JSON.stringify({ ts: Date.now(), title: dom.title.value, blocks: serialize(state.blocks) }));
      setSaveIndicator('Draft backed up locally');
    } catch (e) { /* storage full / disabled */ }
  }

  function checkLocalRestore() {
    let saved;
    try { saved = JSON.parse(localStorage.getItem(storageKey()) || 'null'); } catch (e) { saved = null; }
    if (!saved || !Array.isArray(saved.blocks)) return;
    const serverTs = config.updatedAt ? Date.parse(config.updatedAt) : 0;
    const same = JSON.stringify(saved.blocks) === JSON.stringify(serialize(state.blocks)) && saved.title === dom.title.value;
    if (same || saved.ts <= serverTs) { localStorage.removeItem(storageKey()); return; }
    const when = new Date(saved.ts);
    const bar = h('div', { class: 'lcms-notice' }, [
      h('span', { text: 'An unsaved draft from ' + when.toLocaleString() + ' was found in this browser.' }),
      h('button', { type: 'button', class: 'lcms-btn lcms-btn--primary', text: 'Restore', onclick: function () { pushHistory(); state.blocks = withIds(saved.blocks); dom.title.value = saved.title || dom.title.value; render(); markDirty(); bar.remove(); } }),
      h('button', { type: 'button', class: 'lcms-btn', text: 'Discard', onclick: function () { localStorage.removeItem(storageKey()); bar.remove(); } })
    ]);
    dom.notices.append(bar);
  }

  const scheduleAnalyze = debounce(runAnalyze, 1500);

  function runAnalyze() {
    if (!config.endpoints.analyze || !dom.seoBadge) return;
    const body = new URLSearchParams();
    body.set('title', dom.title.value);
    body.set('content', JSON.stringify(serialize(state.blocks)));
    body.set('focus_keyword', ($('[name="focus_keyword"]', dom.form) || {}).value || '');
    body.set('meta_description', ($('[name="meta_description"]', dom.form) || {}).value || '');
    body.set(config.csrf.name, config.csrf.hash);
    fetch(config.endpoints.analyze, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
      .then(function (data) {
        [dom.seoBadge, dom.seoBadgeTop].forEach(function (badge) {
          if (!badge) return;
          badge.textContent = data.score;
          badge.className = badge.className.replace(/lcms-seo-badge--\w+/g, '').trim() + ' lcms-seo-badge--' + data.rating;
        });
        if (dom.seoList) {
          dom.seoList.innerHTML = '';
          (data.suggestions || []).forEach(s => dom.seoList.append(h('li', { class: 'lcms-seo-suggestion lcms-seo-suggestion--' + s.type, text: s.message })));
          if (!(data.suggestions || []).length) dom.seoList.append(h('li', { class: 'lcms-seo-suggestion lcms-seo-suggestion--good', text: 'Looking good — no suggestions.' }));
        }
      })
      .catch(() => { /* analysis is advisory; never block editing on it */ });
  }

  /* ================================================================== */
  /*  Top bar actions & global keys                                      */
  /* ================================================================== */

  function submitWith(status) {
    const statusSel = $('[name="status"]', dom.form);
    if (status && statusSel) {
      if (status === 'published' && statusSel.value === 'scheduled') { /* keep scheduled */ }
      else statusSel.value = status;
    }
    if (state.mode === 'code') { setMode('visual'); if (state.mode === 'code') return; }
    syncOutput();
    if (!dom.title.value.trim()) { dom.title.focus(); toast('Add a title first', 'error'); return; }
    state.submitting = true;
    try { localStorage.removeItem(storageKey()); } catch (e) { /* ignore */ }
    dom.form.requestSubmit ? dom.form.requestSubmit() : dom.form.submit();
  }

  function onGlobalKey(e) {
    const meta = e.metaKey || e.ctrlKey;
    const inField = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) && !e.target.classList.contains('lcms-code');
    if (meta && e.key.toLowerCase() === 's') { e.preventDefault(); submitWith(null); return; }
    if (meta && !e.shiftKey && e.key.toLowerCase() === 'z' && !inField) { e.preventDefault(); undo(); return; }
    if (meta && ((e.shiftKey && e.key.toLowerCase() === 'z') || e.key.toLowerCase() === 'y') && !inField) { e.preventDefault(); redo(); return; }
    if (meta && e.shiftKey && e.key.toLowerCase() === 'd' && state.selected) { e.preventDefault(); duplicateBlock(state.selected); return; }
    if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'z' && state.selected) { e.preventDefault(); removeBlock(state.selected); return; }
    if (e.key === 'Escape') { closePopovers(); }
  }

  function setDevice(device) {
    state.device = device;
    dom.canvasWrap.dataset.device = device;
    $$('[data-device]', dom.deviceMenu || document).forEach(b => b.classList.toggle('is-active', b.dataset.device === device));
  }

  /* ================================================================== */
  /*  Init                                                               */
  /* ================================================================== */

  function init(userConfig) {
    config = userConfig;
    dom = {
      root: $('#lcms-editor'),
      form: $('#lcms-post-form'),
      canvas: $('#lcms-canvas'),
      canvasWrap: $('#lcms-canvas-wrap'),
      output: $('#lcms-content'),
      title: $('#lcms-title'),
      sidebar: $('#lcms-sidebar'),
      tabPost: $('#lcms-tab-post'),
      tabBlock: $('#lcms-tab-block'),
      panelPost: $('#lcms-panel-post'),
      panelBlock: $('#lcms-panel-block'),
      undoBtn: $('#lcms-undo'),
      redoBtn: $('#lcms-redo'),
      wordCount: $('#lcms-word-count'),
      saveIndicator: $('#lcms-save-indicator'),
      seoBadge: $('#lcms-seo-score'),
      seoBadgeTop: $('#lcms-seo-score-top'),
      seoList: $('#lcms-seo-suggestions'),
      notices: $('#lcms-notices'),
      modeLabel: $('#lcms-mode-label'),
      deviceMenu: $('#lcms-device-menu')
    };

    state.blocks = withIds(config.blocks || []);

    if (config.rawContent) {
      // Stored content didn't decode — open in code mode on the raw text
      // rather than replacing it with a blank canvas. Saving stays blocked
      // until it parses (submitWith() refuses to leave an invalid code mode).
      state.rawOverride = config.rawContent;
      state.mode = 'code';
      document.body.classList.add('lcms-mode-code');
      dom.output.value = config.rawContent;
      if (dom.modeLabel) dom.modeLabel.textContent = 'Visual editor';
    } else if (!state.blocks.length) {
      state.blocks = withIds([{ type: 'paragraph', attrs: {}, content: '' }]);
    }

    render();

    if (config.rawContent) {
      dom.notices.append(h('div', { class: 'lcms-notice lcms-notice--error' }, [
        h('span', { text: "This post's stored content isn't valid block JSON, so the visual editor can't open it. The raw value is shown below — fix it, then switch back to the visual editor. Saving is blocked until it parses." })
      ]));
    }
    setDevice('desktop');
    updateWordCount();

    // title
    autosize(dom.title);
    dom.title.addEventListener('input', function () { autosize(dom.title); state.dirty = true; setSaveIndicator('Unsaved changes'); scheduleLocalSave(); scheduleAnalyze(); updatePermalinkPreview(); });
    dom.title.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); const first = state.blocks[0]; if (first) focusBlock(first.clientId, 'start'); else insertBlock('paragraph', state.blocks, 0); }
      if (e.key === 'ArrowDown' && dom.title.selectionStart === dom.title.value.length) { const first = state.blocks[0]; if (first) { e.preventDefault(); focusBlock(first.clientId, 'start'); } }
    });
    dom.title.addEventListener('blur', function () {
      const slug = $('[name="slug"]', dom.form);
      if (slug && !slug.value) { slug.value = dom.title.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''); updatePermalinkPreview(); }
    });

    // deselect when clicking empty canvas area
    dom.canvasWrap.addEventListener('mousedown', function (e) { if (e.target === dom.canvasWrap || e.target === dom.canvas || e.target.classList.contains('lcms-editor__paper')) { select(null); } });
    document.addEventListener('mousedown', function (e) { if (activePopover && !activePopover.contains(e.target)) closePopovers(); });
    document.addEventListener('keydown', onGlobalKey);

    // top bar
    $('#lcms-inserter-toggle').addEventListener('click', function (e) {
      e.stopPropagation();
      if (activePopover && activePopover.classList.contains('lcms-inserter')) { closePopovers(); return; }
      const found = state.selected ? locate(state.selected) : null;
      openInserter({ anchor: e.currentTarget, onPick: function (type) { if (found) insertBlock(type, found.list, found.index + 1); else insertBlock(type, state.blocks, state.blocks.length); } });
    });
    dom.undoBtn.addEventListener('click', undo);
    dom.redoBtn.addEventListener('click', redo);
    $('#lcms-sidebar-toggle').addEventListener('click', function () { state.sidebarOpen = !state.sidebarOpen; renderSidebarTabs(); });
    dom.tabPost.addEventListener('click', function () { state.tab = 'post'; renderSidebarTabs(); });
    // Always clickable, even with nothing selected yet — renderInspector()
    // already shows a "Select a block…" hint in that case (set on the
    // initial render() and kept in sync by select()), so there's no dead
    // state to guard against here.
    dom.tabBlock.addEventListener('click', function () { state.tab = 'block'; renderSidebarTabs(); });
    // "Save draft" is only rendered while the post isn't published yet.
    const saveDraft = $('#lcms-save-draft'); if (saveDraft) saveDraft.addEventListener('click', () => submitWith('draft'));
    $('#lcms-publish').addEventListener('click', () => submitWith('published'));
    const switchDraft = $('#lcms-switch-draft'); if (switchDraft) switchDraft.addEventListener('click', () => { if (window.confirm('Switch this ' + config.postType + ' back to draft? It will no longer be visible on the site.')) submitWith('draft'); });

    // more menu (kebab)
    $('#lcms-more').addEventListener('click', function (e) {
      e.stopPropagation();
      openMenu(e.currentTarget, [
        { icon: 'code', label: state.mode === 'code' ? 'Visual editor' : 'Code editor', hint: 'JSON', onPick: () => setMode(state.mode === 'code' ? 'visual' : 'code') },
        { icon: 'fullscreen', label: document.body.classList.contains('lcms-distraction-free') ? 'Exit distraction free' : 'Distraction free', onPick: () => { document.body.classList.toggle('lcms-distraction-free'); } },
        { icon: 'desktop', label: 'Preview: Desktop', active: state.device === 'desktop', onPick: () => setDevice('desktop') },
        { icon: 'tablet', label: 'Preview: Tablet', active: state.device === 'tablet', onPick: () => setDevice('tablet') },
        { icon: 'mobile', label: 'Preview: Mobile', active: state.device === 'mobile', onPick: () => setDevice('mobile') },
        { icon: 'copy', label: 'Copy all blocks (JSON)', onPick: () => copyText(JSON.stringify(serialize(state.blocks), null, 2)) }
      ]);
    });

    // sidebar: media buttons for featured image / og image
    $$('[data-lcms-media-target]', dom.form).forEach(function (btn) {
      btn.addEventListener('click', function () {
        const target = $(btn.dataset.lcmsMediaTarget, dom.form);
        openMediaPicker({ accept: 'image', onSelect: function (items) { target.value = items[0].url; target.dispatchEvent(new Event('input', { bubbles: true })); } });
      });
    });
    $$('[data-lcms-preview-for]', dom.form).forEach(function (img) {
      const input = $(img.dataset.lcmsPreviewFor, dom.form);
      const sync = () => { img.src = input.value || ''; img.hidden = !input.value; };
      input.addEventListener('input', sync); sync();
    });
    $$('[data-lcms-clear]', dom.form).forEach(function (btn) {
      btn.addEventListener('click', function () { const input = $(btn.dataset.lcmsClear, dom.form); input.value = ''; input.dispatchEvent(new Event('input', { bubbles: true })); });
    });

    // sidebar: analyze + counters + dirty tracking
    const analyzeBtn = $('#lcms-analyze-btn'); if (analyzeBtn) analyzeBtn.addEventListener('click', runAnalyze);
    const metaDesc = $('[name="meta_description"]', dom.form); const counter = $('#lcms-meta-desc-count');
    if (metaDesc && counter) { const tick = () => { counter.textContent = metaDesc.value.length; }; metaDesc.addEventListener('input', tick); tick(); }
    dom.form.addEventListener('input', function (e) { if (e.target !== dom.title && !dom.canvas.contains(e.target)) { state.dirty = true; setSaveIndicator('Unsaved changes'); if (/focus_keyword|meta_description/.test(e.target.name || '')) scheduleAnalyze(); } });
    dom.form.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && !dom.canvas.contains(e.target)) e.preventDefault(); });
    const slugInput = $('[name="slug"]', dom.form); if (slugInput) slugInput.addEventListener('input', updatePermalinkPreview);
    updatePermalinkPreview();

    dom.form.addEventListener('submit', function () { syncOutput(); state.submitting = true; try { localStorage.removeItem(storageKey()); } catch (e) { /* ignore */ } });
    window.addEventListener('beforeunload', function (e) { if (state.dirty && !state.submitting) { e.preventDefault(); e.returnValue = ''; } });

    setInterval(saveLocal, 30000); // PRD §3.2.3 autosave every 30s
    checkLocalRestore();
    if (config.postId) runAnalyze();
    setSaveIndicator(config.postId ? 'Saved' : 'Draft');
  }

  function updatePermalinkPreview() {
    const preview = $('#lcms-permalink-preview');
    const slugInput = $('[name="slug"]', dom.form);
    if (!preview || !slugInput) return;
    const slug = slugInput.value || dom.title.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    preview.textContent = config.siteUrl.replace(/\/$/, '') + '/' + (slug || '…');
  }

  window.LcmsBlockEditor = { init: init, state: state, TYPES: TYPES };
})();
