/* /Applications/MAMP/htdocs/playground/wp-content/plugins/Optimizr/functions/seo/seo-dynamic-input.js */

document.addEventListener("DOMContentLoaded", function () {

    // Sélectionne tous les champs contenteditable
    const editableFields = document.querySelectorAll('[contenteditable][data-hidden-input]');

    // Tableau d'objets { code, label }
    const SEO_VARIABLES = [
        { code: '{titre_page}', label: 'Titre de la page' },
        { code: '{nom_site}', label: 'Nom du site' },
        { code: '{slogan}', label: 'Slogan' },
        { code: '{categorie}', label: 'Catégorie' },
        { code: '{resume}', label: 'Résumé' },
        { code: '{auteur}', label: 'Auteur' },
        { code: '{type_contenu}', label: 'Type de contenu' },
        { code: '{date_publication}', label: 'Date de publication' }
    ];

    /* ----------- Activation Drag & Drop sur les badges variables ----------- */
    document.querySelectorAll('.hpt-draggable').forEach(el => {
        el.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', el.getAttribute('data-seo-variable'));
        });

        // Clic : ajoute la variable à la fin du champ ciblé
        el.addEventListener('click', () => {
            const field = document.getElementById(el.getAttribute('data-target'));
            if (!field) return;
            const code = el.getAttribute('data-seo-variable');
            const raw = haptic_GPT_convertBadgesToText(field.innerHTML);
            field.innerHTML = haptic_GPT_convertTextToBadges((raw ? raw + ' ' : '') + code);
            haptic_GPT_syncAllFields();
        });
    });

    /* ----------- Boîte de suggestions pour l’autocomplétion clavier -------- */
    let suggestionBox = document.createElement('div');
    suggestionBox.className = 'optimizr-suggest';
    suggestionBox.style.display = 'none';
    document.body.appendChild(suggestionBox);

    /* ----------------- Fonctions utilitaires ----------------- */
    function haptic_GPT_convertTextToBadges(text) {
        let container = document.createElement('div');
        let tokens = text.match(/\S+|\s+/g) || [];

        tokens.forEach(token => {
            let found = SEO_VARIABLES.find(v => v.code === token);
            if (found) {
                let badge = document.createElement('span');
                badge.className = 'optimizr-token';
                badge.contentEditable = 'false';
                badge.setAttribute('data-seo-variable', found.code);
                badge.textContent = found.label;

                let closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'optimizr-token__remove';
                closeBtn.setAttribute('aria-label', 'Retirer ' + found.label);
                closeBtn.textContent = '×';
                closeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    badge.remove();
                    haptic_GPT_syncAllFields();
                });
                badge.appendChild(closeBtn);

                container.appendChild(badge);
                container.appendChild(document.createTextNode(' '));
            } else {
                container.appendChild(document.createTextNode(token));
            }
        });
        return container.innerHTML;
    }

    function haptic_GPT_convertBadgesToText(html) {
        let temp = document.createElement('div');
        temp.innerHTML = html;
        let parts = [];
        temp.childNodes.forEach(node => {
            if (node.nodeType === Node.ELEMENT_NODE && node.matches('.optimizr-token')) {
                parts.push(node.getAttribute('data-seo-variable'));
            } else {
                parts.push(node.textContent);
            }
        });
        return parts.join('').replace(/\s+/g, ' ').trim();
    }

    function haptic_GPT_syncAllFields() {
        editableFields.forEach(field => {
            let hiddenId = field.getAttribute('data-hidden-input');
            let hiddenInput = document.getElementById(hiddenId);
            if (!hiddenInput) return;
            hiddenInput.value = haptic_GPT_convertBadgesToText(field.innerHTML);
        });
        // Aperçus SEO et barre d'enregistrement (js/optimizr-admin.js)
        document.dispatchEvent(new CustomEvent('hpt:seo-pattern-change'));
    }

    /* ----------------- Autocomplétion clavier ----------------- */
    function getCaretPos(field) {
        let sel = window.getSelection();
        if (!sel.rangeCount) return { x: 0, y: 0 };
        let range = sel.getRangeAt(0).cloneRange();
        let dummy = document.createElement('span');
        range.insertNode(dummy);
        range.collapse(false);
        let rect = dummy.getBoundingClientRect();
        dummy.remove();
        return { x: rect.left, y: rect.top };
    }

    function showSuggestions(matches, x, y, onSelect) {
        if (!matches.length) {
            suggestionBox.style.display = 'none';
            return;
        }
        suggestionBox.innerHTML = '';
        let ul = document.createElement('ul');

        matches.forEach(item => {
            let li = document.createElement('li');
            li.textContent = item.label;
            li.addEventListener('click', () => onSelect(item));
            ul.appendChild(li);
        });

        suggestionBox.appendChild(ul);
        suggestionBox.style.left = (x + window.scrollX) + 'px';
        suggestionBox.style.top = (y + window.scrollY + 22) + 'px';
        suggestionBox.style.display = 'block';
    }

    function hideSuggestions() {
        suggestionBox.style.display = 'none';
    }

    function insertVariableAsBadge(field, variable) {
        let sel = window.getSelection();
        if (!sel.rangeCount) return;
        let range = sel.getRangeAt(0);
        range.deleteContents();
        range.insertNode(document.createTextNode(variable.code + ' '));

        let raw = haptic_GPT_convertBadgesToText(field.innerHTML);
        field.innerHTML = haptic_GPT_convertTextToBadges(raw);

        field.focus();
        sel = window.getSelection();
        sel.collapse(field.lastChild, 1);

        hideSuggestions();
        haptic_GPT_syncAllFields();
    }

    function currentWord(field) {
        let sel = window.getSelection();
        if (!sel.rangeCount) return '';
        let range = sel.getRangeAt(0);
        let pre = range.cloneRange();
        pre.selectNodeContents(field);
        pre.setEnd(range.endContainer, range.endOffset);
        let text = pre.toString();
        let m = text.match(/[^\s]+$/);
        return m ? m[0] : '';
    }

    /* -------------------- Initialisation --------------------- */
    editableFields.forEach(field => {
        let hiddenId = field.getAttribute('data-hidden-input');
        let hiddenInput = document.getElementById(hiddenId);
        if (!hiddenInput) return;

        field.innerHTML = haptic_GPT_convertTextToBadges(hiddenInput.value);

        field.addEventListener('input', () => {
            let word = currentWord(field);
            if (word.length >= 2) {
                let matches = SEO_VARIABLES.filter(v =>
                    v.code.toLowerCase().startsWith(word.toLowerCase()) ||
                    v.label.toLowerCase().startsWith(word.toLowerCase())
                );
                let pos = getCaretPos(field);
                showSuggestions(matches, pos.x, pos.y, sel => insertVariableAsBadge(field, sel));
            } else {
                hideSuggestions();
            }
        });

        /* ----- Drag & Drop depuis les badges ----- */
        field.addEventListener('dragover', e => e.preventDefault());
        field.addEventListener('drop', e => {
            e.preventDefault();
            // Place le caret à la position du drop
            if (document.caretRangeFromPoint) {
                const range = document.caretRangeFromPoint(e.clientX, e.clientY);
                if (range) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            } else if (e.rangeParent) {
                // Pour compatibilité Firefox
                const range = document.createRange();
                range.setStart(e.rangeParent, e.rangeOffset);
                range.collapse(true);
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }
            const varCode = e.dataTransfer.getData('text/plain');
            if (!varCode) return;
            const variable = SEO_VARIABLES.find(v => v.code === varCode) ||
                { code: varCode, label: varCode.replace(/[{}]/g, '') };
            insertVariableAsBadge(field, variable);
        });

        field.addEventListener('blur', () => {
            setTimeout(() => {
                if (!suggestionBox.matches(':hover')) {
                    field.innerHTML = haptic_GPT_convertTextToBadges(
                        haptic_GPT_convertBadgesToText(field.innerHTML)
                    );
                    haptic_GPT_syncAllFields();
                    hideSuggestions();
                }
            }, 200);
        });
    });
});