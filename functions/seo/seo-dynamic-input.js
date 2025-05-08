/* /Applications/MAMP/htdocs/playground/wp-content/plugins/Optimizr/functions/seo/seo-dynamic-input.js */

document.addEventListener("DOMContentLoaded", function () {

    // Sélectionne tous les champs contenteditable
    const editableFields = document.querySelectorAll('[contenteditable][data-hidden-input]');

    // Tableau d'objets { code, label } sans underscores
    const SEO_VARIABLES = [
        { code: 'titre', label: 'Titre' },
        { code: 'nomsite', label: 'Nom du site' },
        { code: 'slogan', label: 'Slogan' },
        { code: 'categorie', label: 'Catégorie' },
        { code: 'resume', label: 'Résumé' },
        { code: 'auteur', label: 'Auteur' },
        { code: 'typecontenu', label: 'Type de contenu' },
        { code: 'datepublication', label: 'Date de publication' }
    ];

    // Petit div pour l'affichage des suggestions
    let suggestionBox = document.createElement('div');
    suggestionBox.style.position = 'absolute';
    suggestionBox.style.zIndex = '9999';
    suggestionBox.style.background = '#fff';
    suggestionBox.style.border = '1px solid #ccc';
    suggestionBox.style.display = 'none';

    document.body.appendChild(suggestionBox);

    // -------------------------------------------------
    // haptic_GPT_convertTextToBadges
    // Convertit le texte brut en structure HTML (badges)
    // -------------------------------------------------
    function haptic_GPT_convertTextToBadges(text) {
        let tempContainer = document.createElement('div');
        // Découpe en séquences non-espace ou espaces
        let tokens = text.match(/\S+|\s+/g) || [];

        tokens.forEach(token => {
            let foundVar = SEO_VARIABLES.find(item => item.code === token);
            if (foundVar) {
                // Création du badge
                let badge = document.createElement('span');
                badge.className = 'badge bg-primary me-1';
                badge.setAttribute('data-seo-variable', foundVar.code);
                badge.textContent = foundVar.label;

                // Bouton (croix) pour supprimer
                let closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'btn-close btn-close-white ms-1';
                closeBtn.style.float = 'none';
                closeBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    badge.remove();
                    haptic_GPT_syncAllFields();
                });
                badge.appendChild(closeBtn);

                tempContainer.appendChild(badge);
                // Ajoute un espace après (optionnel)
                tempContainer.appendChild(document.createTextNode(' '));
            } else {
                // Texte ou espace
                tempContainer.appendChild(document.createTextNode(token));
            }
        });

        return tempContainer.innerHTML;
    }

    // -------------------------------------------------
    // haptic_GPT_convertBadgesToText
    // Convertit badges + texte en texte brut
    // -------------------------------------------------
    function haptic_GPT_convertBadgesToText(html) {
        let temp = document.createElement('div');
        temp.innerHTML = html;

        let textParts = [];
        temp.childNodes.forEach(node => {
            if (node.nodeType === Node.ELEMENT_NODE && node.matches('.badge')) {
                // Récupère la variable
                let varName = node.getAttribute('data-seo-variable');
                if (varName) {
                    textParts.push(varName);
                }
            } else {
                textParts.push(node.textContent);
            }
        });

        return textParts.join('').replace(/\s+/g, ' ').trim();
    }

    // -------------------------------------------------
    // haptic_GPT_syncAllFields
    // Synchronise la valeur du champ hidden
    // -------------------------------------------------
    function haptic_GPT_syncAllFields() {
        editableFields.forEach(field => {
            let hiddenInputId = field.getAttribute('data-hidden-input');
            let hiddenInput = document.getElementById(hiddenInputId);
            if (!hiddenInput) return;

            let htmlContent = field.innerHTML;
            let rawText = haptic_GPT_convertBadgesToText(htmlContent);
            hiddenInput.value = rawText;
        });
    }

    // -------------------------------------------------
    // getCaretCoordinatesInContentEditable
    // Pour positionner le suggestionBox à l'emplacement du caret
    // -------------------------------------------------
    function getCaretCoordinatesInContentEditable(field) {
        let sel = window.getSelection();
        if (!sel.rangeCount) return { x: 0, y: 0 };
        let range = sel.getRangeAt(0).cloneRange();
        let dummy = document.createElement('span');
        range.insertNode(dummy);
        range.collapse(false);

        let rect = dummy.getBoundingClientRect();
        dummy.parentNode.removeChild(dummy);
        return { x: rect.left, y: rect.top };
    }

    // -------------------------------------------------
    // showSuggestions
    // Affiche la popup avec la liste de correspondances
    // -------------------------------------------------
    function showSuggestions(matches, x, y, onSelect) {
        if (!matches.length) {
            suggestionBox.style.display = 'none';
            return;
        }

        suggestionBox.innerHTML = '';
        let ul = document.createElement('ul');
        ul.style.listStyle = 'none';
        ul.style.margin = '0';
        ul.style.padding = '0';

        matches.forEach(item => {
            let li = document.createElement('li');
            li.style.padding = '5px 10px';
            li.style.cursor = 'pointer';
            li.textContent = item.label;
            li.addEventListener('click', () => onSelect(item));
            ul.appendChild(li);
        });

        suggestionBox.appendChild(ul);
        suggestionBox.style.left = x + 'px';
        suggestionBox.style.top = (y + 20) + 'px';
        suggestionBox.style.display = 'block';
    }

    function hideSuggestions() {
        suggestionBox.style.display = 'none';
    }

    // -------------------------------------------------
    // insertVariableAsBadge
    // Insère la variable choisie comme badge
    // -------------------------------------------------
    function insertVariableAsBadge(field, variable) {
        let sel = window.getSelection();
        if (!sel.rangeCount) return;

        // Supprime le mot (ou la sélection) en cours
        let range = sel.getRangeAt(0);
        range.deleteContents();

        // Insère le code brut (ex: "titre ") à l’emplacement du caret
        range.insertNode(document.createTextNode(variable.code + ' '));

        // Re-transforme en badges
        let rawText = haptic_GPT_convertBadgesToText(field.innerHTML);
        field.innerHTML = haptic_GPT_convertTextToBadges(rawText);

        // Replace le caret
        field.focus();
        sel = window.getSelection();
        sel.collapse(field.lastChild, 1);

        hideSuggestions();
        haptic_GPT_syncAllFields();
    }

    // -------------------------------------------------
    // getCurrentWordUpToCaret
    // Récupère le “mot” en cours de saisie
    // -------------------------------------------------
    function getCurrentWordUpToCaret(field) {
        let sel = window.getSelection();
        if (!sel.rangeCount) return '';

        let range = sel.getRangeAt(0);
        let preCursorRange = range.cloneRange();
        preCursorRange.selectNodeContents(field);
        preCursorRange.setEnd(range.endContainer, range.endOffset);

        let text = preCursorRange.toString();
        // Récupère le dernier groupe non-espace saisi
        let matches = text.match(/[^\s]+$/);
        return matches ? matches[0] : '';
    }

    // -------------------------------------------------
    // Initialisation : Sur chaque zone edit, on gère l'autocomplétion
    // -------------------------------------------------
    editableFields.forEach(field => {
        let hiddenInputId = field.getAttribute('data-hidden-input');
        let hiddenInput = document.getElementById(hiddenInputId);
        if (!hiddenInput) return;

        // Conversion initiale (si une valeur existait)
        let initialValue = hiddenInput.value;
        field.innerHTML = haptic_GPT_convertTextToBadges(initialValue);

        // Sur la frappe au clavier : propose des suggestions si >= 2 lettres
        field.addEventListener('input', function () {
            let currentWord = getCurrentWordUpToCaret(field);
            if (currentWord.length >= 2) {
                let searchTerm = currentWord.toLowerCase();

                // Filtre : code ou label qui commence par la saisie
                let matches = SEO_VARIABLES.filter(v =>
                    v.code.toLowerCase().startsWith(searchTerm) ||
                    v.label.toLowerCase().startsWith(searchTerm)
                );

                let caretPos = getCaretCoordinatesInContentEditable(field);
                showSuggestions(matches, caretPos.x, caretPos.y, (selected) => {
                    insertVariableAsBadge(field, selected);
                });
            } else {
                hideSuggestions();
            }
        });

        // Sur blur : re-transforme et cache la popup si on ne survole pas la liste
        field.addEventListener('blur', function () {
            setTimeout(() => {
                if (!suggestionBox.matches(':hover')) {
                    let rawText = haptic_GPT_convertBadgesToText(field.innerHTML);
                    field.innerHTML = haptic_GPT_convertTextToBadges(rawText);
                    haptic_GPT_syncAllFields();
                    hideSuggestions();
                }
            }, 200);
        });
    });
});