/**
 * Éditeur de motifs SEO (onglet SEO de la page d'options).
 *
 * Champ contenteditable où le texte se tape librement et où les variables
 * ({titre_page}, {nom_site}…) sont des étiquettes que l'on peut :
 * - insérer d'un clic ou par glisser-déposer depuis la liste « Insérer » ;
 * - déplacer par glisser-déposer, y compris d'un champ à l'autre ;
 * - choisir au clavier en tapant « { » ;
 * - supprimer avec leur croix ou la touche Retour arrière.
 *
 * La valeur texte est recopiée à chaque modification dans le champ caché
 * indiqué par data-hidden-input, puis l'évènement « haptic:seo-pattern-change »
 * prévient les aperçus et la barre d'enregistrement (js/optimizr-admin.js).
 */
(function () {
    'use strict';

    // Fournies par PHP (haptic_seo_pattern_variables()) : { '{code}': 'Libellé' }
    var VARIABLES = window.hapticSeoVariables || {};
    var CODES = Object.keys(VARIABLES);
    var TOKEN_MIME = 'application/x-haptic-seo-token';

    var dragSource = null;   // Étiquette en cours de déplacement (null si elle vient de la liste)
    var dropCaret = null;    // Repère visuel de la position de dépôt

    function escapeRegExp(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    var CODE_PATTERN = CODES.length ? new RegExp('(' + CODES.map(escapeRegExp).join('|') + ')', 'g') : null;

    function normalize(text) {
        return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    /* ---------- Rendu et lecture du champ ---------- */

    function createToken(code) {
        var token = document.createElement('span');
        token.className = 'optimizr-token';
        token.contentEditable = 'false';
        token.draggable = true;
        token.setAttribute('data-seo-variable', code);

        var label = document.createElement('span');
        label.textContent = VARIABLES[code] || code.replace(/[{}]/g, '');
        token.appendChild(label);

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'optimizr-token__remove';
        remove.tabIndex = -1;
        remove.setAttribute('aria-label', 'Retirer ' + label.textContent);
        remove.textContent = '×';
        token.appendChild(remove);

        return token;
    }

    // Texte brut → fragment (texte + étiquettes)
    function parse(text) {
        var fragment = document.createDocumentFragment();
        var parts = CODE_PATTERN ? text.split(CODE_PATTERN) : [text];

        parts.forEach(function (part) {
            if (!part) return;
            fragment.appendChild(VARIABLES[part] ? createToken(part) : document.createTextNode(part));
        });

        return fragment;
    }

    // Champ → texte brut
    function serialize(node) {
        var text = '';

        node.childNodes.forEach(function (child) {
            if (child.nodeType === Node.TEXT_NODE) {
                text += child.nodeValue;
            } else if (child.nodeType === Node.ELEMENT_NODE) {
                if (child.matches('.optimizr-token')) {
                    text += child.getAttribute('data-seo-variable');
                } else if (child === dropCaret) {
                    return;
                } else if (child.tagName === 'BR') {
                    text += ' ';
                } else {
                    // Le navigateur peut envelopper la saisie dans des <div> ou <span>
                    text += serialize(child);
                }
            }
        });

        return text;
    }

    function clean(text) {
        return text.replace(/[ ​]/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function render(editor, text) {
        editor.textContent = '';
        editor.appendChild(parse(text));
    }

    function sync(editor) {
        var hidden = document.getElementById(editor.getAttribute('data-hidden-input'));
        if (!hidden) return;

        var value = clean(serialize(editor));
        if (hidden.value !== value) {
            hidden.value = value;
            document.dispatchEvent(new CustomEvent('haptic:seo-pattern-change'));
        }
    }

    function syncAll() {
        document.querySelectorAll('[data-hidden-input]').forEach(sync);
    }

    /* ---------- Position du curseur ---------- */

    var savedRanges = new WeakMap();

    function editorOf(node) {
        var el = node && (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement);
        return el ? el.closest('[data-hidden-input]') : null;
    }

    function saveRange() {
        var sel = window.getSelection();
        if (!sel.rangeCount) return;
        var range = sel.getRangeAt(0);
        var editor = editorOf(range.startContainer);
        if (editor) savedRanges.set(editor, range.cloneRange());
    }

    function rangeAtEnd(editor) {
        var range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false);
        return range;
    }

    function placeCaret(range) {
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    // Position de dépôt sous le pointeur, jamais à l'intérieur d'une étiquette
    function rangeFromPoint(editor, x, y) {
        var range = null;

        if (document.caretPositionFromPoint) {
            var pos = document.caretPositionFromPoint(x, y);
            if (pos) {
                range = document.createRange();
                range.setStart(pos.offsetNode, pos.offset);
            }
        } else if (document.caretRangeFromPoint) {
            range = document.caretRangeFromPoint(x, y);
        }

        if (!range || !editor.contains(range.startContainer)) {
            return rangeAtEnd(editor);
        }

        var container = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement;
        var token = container.closest('.optimizr-token');
        if (token && editor.contains(token)) {
            var rect = token.getBoundingClientRect();
            range = document.createRange();
            if (x < rect.left + rect.width / 2) {
                range.setStartBefore(token);
            } else {
                range.setStartAfter(token);
            }
        }

        range.collapse(true);
        return range;
    }

    /* ---------- Insertion ---------- */

    function charBefore(range) {
        var node = range.startContainer;
        var offset = range.startOffset;
        if (node.nodeType === Node.TEXT_NODE) {
            if (offset > 0) return node.nodeValue.charAt(offset - 1);
            node = node.previousSibling;
        } else {
            node = node.childNodes[offset - 1] || null;
        }
        while (node && node === dropCaret) node = node.previousSibling;
        if (!node) return '';
        if (node.nodeType === Node.TEXT_NODE) return node.nodeValue.slice(-1);
        return 'x';
    }

    function charAfter(range) {
        var node = range.endContainer;
        var offset = range.endOffset;
        if (node.nodeType === Node.TEXT_NODE) {
            if (offset < node.nodeValue.length) return node.nodeValue.charAt(offset);
            node = node.nextSibling;
        } else {
            node = node.childNodes[offset] || null;
        }
        while (node && node === dropCaret) node = node.nextSibling;
        if (!node) return '';
        if (node.nodeType === Node.TEXT_NODE) return node.nodeValue.charAt(0);
        return 'x';
    }

    // Insère une variable à la position donnée, en ajoutant les espaces qui la séparent du texte
    function insertToken(editor, range, code) {
        var needsSpaceBefore = /\S/.test(charBefore(range));
        var needsSpaceAfter = !/\s/.test(charAfter(range));

        range.deleteContents();

        var fragment = document.createDocumentFragment();
        if (needsSpaceBefore) fragment.appendChild(document.createTextNode(' '));
        var token = createToken(code);
        fragment.appendChild(token);
        var after = document.createTextNode(needsSpaceAfter ? ' ' : '');
        fragment.appendChild(after);
        range.insertNode(fragment);

        var caret = document.createRange();
        caret.setStart(after, after.nodeValue.length);
        caret.collapse(true);
        editor.focus();
        placeCaret(caret);
        savedRanges.set(editor, caret.cloneRange());

        syncAll();
    }

    function insertText(editor, range, text) {
        range.deleteContents();
        var fragment = parse(text);
        var last = fragment.lastChild;
        range.insertNode(fragment);

        if (last) {
            var caret = document.createRange();
            caret.setStartAfter(last);
            caret.collapse(true);
            placeCaret(caret);
        }
        sync(editor);
    }

    /* ---------- Suggestions au clavier (« { ») ---------- */

    var suggest = {
        box: null,
        editor: null,
        items: [],
        active: 0,
        query: null
    };

    function buildSuggestBox() {
        var box = document.createElement('ul');
        box.className = 'optimizr-suggest';
        box.id = 'optimizr-seo-suggest';
        box.setAttribute('role', 'listbox');
        box.hidden = true;
        // mousedown : garde le focus dans le champ
        box.addEventListener('mousedown', function (e) {
            e.preventDefault();
            var li = e.target.closest('li');
            if (li) chooseSuggestion(Number(li.dataset.index));
        });
        document.body.appendChild(box);
        suggest.box = box;
    }

    // Texte tapé depuis le dernier « { » avant le curseur, ou null
    function currentQuery() {
        var sel = window.getSelection();
        if (!sel.rangeCount || !sel.isCollapsed) return null;
        var range = sel.getRangeAt(0);
        if (range.startContainer.nodeType !== Node.TEXT_NODE) return null;
        var before = range.startContainer.nodeValue.slice(0, range.startOffset);
        var match = before.match(/\{([^\s{}]*)$/);
        return match ? { text: match[1], length: match[0].length, range: range } : null;
    }

    function updateSuggestions(editor) {
        var query = currentQuery();
        if (!query) {
            closeSuggestions();
            return;
        }

        var needle = normalize(query.text);
        suggest.items = CODES.filter(function (code) {
            return !needle || normalize(code).indexOf(needle) !== -1 || normalize(VARIABLES[code]).indexOf(needle) !== -1;
        });

        if (!suggest.items.length) {
            closeSuggestions();
            return;
        }

        suggest.editor = editor;
        suggest.query = query;
        suggest.active = Math.min(suggest.active, suggest.items.length - 1);

        suggest.box.textContent = '';
        suggest.items.forEach(function (code, index) {
            var li = document.createElement('li');
            li.id = 'optimizr-seo-suggest-' + index;
            li.setAttribute('role', 'option');
            li.dataset.index = index;
            li.textContent = VARIABLES[code];
            suggest.box.appendChild(li);
        });
        highlightSuggestion();

        var rect = query.range.getBoundingClientRect();
        if (!rect.width && !rect.height) rect = editor.getBoundingClientRect();
        suggest.box.style.left = (rect.left + window.scrollX) + 'px';
        suggest.box.style.top = (rect.bottom + window.scrollY + 6) + 'px';
        suggest.box.hidden = false;
        editor.setAttribute('aria-expanded', 'true');
    }

    function highlightSuggestion() {
        Array.prototype.forEach.call(suggest.box.children, function (li, index) {
            var active = index === suggest.active;
            li.classList.toggle('is-active', active);
            li.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (suggest.editor) {
            suggest.editor.setAttribute('aria-activedescendant', 'optimizr-seo-suggest-' + suggest.active);
        }
    }

    function closeSuggestions() {
        if (!suggest.box || suggest.box.hidden) return;
        suggest.box.hidden = true;
        suggest.active = 0;
        if (suggest.editor) {
            suggest.editor.setAttribute('aria-expanded', 'false');
            suggest.editor.removeAttribute('aria-activedescendant');
        }
    }

    function chooseSuggestion(index) {
        var code = suggest.items[index];
        var query = currentQuery();
        if (!code || !query) return;

        // Remplace « {texte tapé » par l'étiquette
        var range = query.range.cloneRange();
        range.setStart(range.startContainer, range.startOffset - query.length);
        closeSuggestions();
        insertToken(suggest.editor, range, code);
    }

    /* ---------- Glisser-déposer ---------- */

    function removeDropCaret() {
        if (dropCaret && dropCaret.parentNode) {
            var parent = dropCaret.parentNode;
            parent.removeChild(dropCaret);
            parent.normalize();
        }
    }

    function showDropCaret(editor, x, y) {
        if (!dropCaret) {
            dropCaret = document.createElement('span');
            dropCaret.className = 'optimizr-drop-caret';
            dropCaret.setAttribute('aria-hidden', 'true');
            dropCaret.contentEditable = 'false';
        }
        var range = rangeFromPoint(editor, x, y);
        range.insertNode(dropCaret);
    }

    function isVariableDrag(e) {
        var types = Array.prototype.slice.call(e.dataTransfer.types || []);
        return types.indexOf(TOKEN_MIME) !== -1 || types.indexOf('text/plain') !== -1;
    }

    function startDrag(e, code, source) {
        dragSource = source;
        e.dataTransfer.effectAllowed = source ? 'move' : 'copy';
        e.dataTransfer.setData(TOKEN_MIME, code);
        e.dataTransfer.setData('text/plain', code);
        if (source) source.classList.add('is-dragging');
    }

    function endDrag() {
        if (dragSource) dragSource.classList.remove('is-dragging');
        dragSource = null;
        removeDropCaret();
    }

    /* ---------- Initialisation ---------- */

    function initEditor(editor) {
        var hidden = document.getElementById(editor.getAttribute('data-hidden-input'));
        if (!hidden) return;

        render(editor, hidden.value);
        editor.setAttribute('aria-autocomplete', 'list');
        editor.setAttribute('aria-controls', 'optimizr-seo-suggest');
        editor.setAttribute('aria-expanded', 'false');

        editor.addEventListener('input', function () {
            sync(editor);
            updateSuggestions(editor);
        });

        editor.addEventListener('keydown', function (e) {
            var open = suggest.box && !suggest.box.hidden && suggest.editor === editor;

            if (open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                e.preventDefault();
                var step = e.key === 'ArrowDown' ? 1 : -1;
                suggest.active = (suggest.active + step + suggest.items.length) % suggest.items.length;
                highlightSuggestion();
            } else if (open && (e.key === 'Enter' || e.key === 'Tab')) {
                e.preventDefault();
                chooseSuggestion(suggest.active);
            } else if (open && e.key === 'Escape') {
                e.preventDefault();
                closeSuggestions();
            } else if (e.key === 'Enter') {
                // Motif sur une seule ligne
                e.preventDefault();
            }
        });

        // Collage en texte brut ; les variables collées deviennent des étiquettes
        editor.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text/plain').replace(/\s+/g, ' ');
            var sel = window.getSelection();
            if (sel.rangeCount) insertText(editor, sel.getRangeAt(0), text);
        });

        editor.addEventListener('click', function (e) {
            var remove = e.target.closest('.optimizr-token__remove');
            if (remove && editor.contains(remove)) {
                e.preventDefault();
                remove.closest('.optimizr-token').remove();
                editor.focus();
                sync(editor);
            }
        });

        // Tokens déplaçables à l'intérieur des champs
        editor.addEventListener('dragstart', function (e) {
            var token = e.target.closest && e.target.closest('.optimizr-token');
            if (!token) {
                // Glisser une sélection de texte : laissé au navigateur mais sans dépôt dans le champ
                return;
            }
            startDrag(e, token.getAttribute('data-seo-variable'), token);
        });

        editor.addEventListener('dragend', endDrag);

        editor.addEventListener('dragover', function (e) {
            if (!isVariableDrag(e)) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = dragSource ? 'move' : 'copy';
            showDropCaret(editor, e.clientX, e.clientY);
        });

        editor.addEventListener('dragleave', function (e) {
            if (!editor.contains(e.relatedTarget)) removeDropCaret();
        });

        editor.addEventListener('drop', function (e) {
            if (!isVariableDrag(e)) return;
            e.preventDefault();

            var code = e.dataTransfer.getData(TOKEN_MIME) || e.dataTransfer.getData('text/plain');
            var range;
            if (dropCaret && editor.contains(dropCaret)) {
                range = document.createRange();
                range.setStartBefore(dropCaret);
                range.collapse(true);
            } else {
                range = rangeFromPoint(editor, e.clientX, e.clientY);
            }

            if (dragSource) {
                var sourceEditor = editorOf(dragSource);
                dragSource.remove();
                if (sourceEditor) sourceEditor.normalize();
            }
            removeDropCaret();

            if (VARIABLES[code]) {
                insertToken(editor, range, code);
            } else if (code) {
                insertText(editor, range, code.replace(/\s+/g, ' '));
            }

            endDrag();
        });

        // Texte tapé à la main (« {nom_site} ») : converti en étiquette à la sortie du champ
        editor.addEventListener('blur', function () {
            closeSuggestions();
            var value = clean(serialize(editor));
            render(editor, value);
            sync(editor);
        });
    }

    function initPicker() {
        document.querySelectorAll('.optimizr-token-add[data-seo-variable]').forEach(function (chip) {
            var code = chip.getAttribute('data-seo-variable');
            var editor = document.getElementById(chip.getAttribute('data-target'));
            if (!editor) return;

            chip.addEventListener('dragstart', function (e) {
                startDrag(e, code, null);
            });
            chip.addEventListener('dragend', endDrag);

            // Clic : insère à la dernière position du curseur dans le champ, sinon à la fin
            chip.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });
            chip.addEventListener('click', function () {
                // Sélection courante si elle est dans le champ, sinon la dernière mémorisée, sinon la fin
                var sel = window.getSelection();
                var current = sel.rangeCount ? sel.getRangeAt(0) : null;
                var saved = savedRanges.get(editor);
                var range = current && editorOf(current.startContainer) === editor ? current
                    : saved && editor.contains(saved.startContainer) ? saved
                    : rangeAtEnd(editor);
                insertToken(editor, range, code);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var editors = document.querySelectorAll('[contenteditable][data-hidden-input]');
        if (!editors.length) return;

        buildSuggestBox();
        editors.forEach(initEditor);
        initPicker();

        document.addEventListener('selectionchange', saveRange);
        document.addEventListener('dragend', endDrag);
    });
})();
