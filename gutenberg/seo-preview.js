document.addEventListener("DOMContentLoaded", function () {
    const { __ } = wp.i18n;
    const titleInput = document.getElementById("haptic_og_title");
    const descInput = document.getElementById("haptic_og_desc");
    const imageInput = document.getElementById("haptic_og_image");

    const previewBox = document.getElementById("haptic_seo_preview");
    // La metabox SEO n'est présente que sur les types de contenu du sitemap
    if (!previewBox || !titleInput || !descInput) return;

    const previewTitle = previewBox.querySelector(".seo-title");
    const previewDesc = previewBox.querySelector(".seo-desc");
    const titleCount = document.getElementById("haptic_title_count");
    const descCount = document.getElementById("haptic_desc_count");

    // Un champ vide applique le motif, affiché en placeholder
    function effective(input) {
        return input.value.trim() || input.placeholder || "";
    }

    // Pastille et barre : orange sous le minimum conseillé, vert dans la zone idéale, rouge au-delà du maximum
    function updateCount(input, counter, max) {
        const length = effective(input).length;
        const meter = counter.closest(".optimizr-seo__meter");
        counter.textContent = `${length}/${max}`;
        if (!meter) return;

        const min = parseInt(meter.dataset.min, 10) || Math.round(max * 0.8);
        // La barre va un quart au-delà du maximum pour montrer le dépassement
        const scale = max * 1.25;
        meter.style.setProperty("--osb-fill", `${Math.min(length / scale, 1) * 100}%`);
        meter.style.setProperty("--osb-ideal-from", `${(min / scale) * 100}%`);
        meter.style.setProperty("--osb-ideal-width", `${((max - min) / scale) * 100}%`);

        meter.classList.remove("is-ok", "is-warn", "is-bad");
        meter.classList.add(length > max ? "is-bad" : length >= min ? "is-ok" : "is-warn");
    }

    function updatePreview() {
        previewTitle.textContent = effective(titleInput);
        previewDesc.textContent = effective(descInput);
    }

    // Ajout des événements pour les mises à jour
    titleInput.addEventListener("input", function () {
        updatePreview();
        updateCount(titleInput, titleCount, 60);
    });

    descInput.addEventListener("input", function () {
        updatePreview();
        updateCount(descInput, descCount, 160);
    });

    const thumb = previewBox.querySelector(".seo-thumb");
    const thumbImg = thumb ? thumb.querySelector("img") : null;

    if (imageInput && thumbImg) {
        imageInput.addEventListener("input", function () {
            const url = effective(imageInput);
            // Sans image par défaut, le placeholder n'est qu'un texte d'aide : pas de vignette plutôt qu'une image cassée
            if (!/^https?:\/\//i.test(url)) {
                thumb.hidden = true;
                thumbImg.removeAttribute("src");
                return;
            }
            thumbImg.src = url;
            thumbImg.alt = __("SEO preview", "optimizr");
            thumb.hidden = false;
        });

        // Adresse introuvable : la vignette disparaît
        thumbImg.addEventListener("error", function () {
            thumb.hidden = true;
        });
    }

    // Initialisation de la prévisualisation et des compteurs avec les valeurs actuelles
    updateCount(titleInput, titleCount, 60);
    updateCount(descInput, descCount, 160);
    updatePreview();
});