document.addEventListener("DOMContentLoaded", function () {
    const titleInput = document.getElementById("hpt_og_title");
    const descInput = document.getElementById("hpt_og_desc");
    const imageInput = document.getElementById("hpt_og_image");

    const previewBox = document.getElementById("hpt_seo_preview");
    // La metabox SEO n'est présente que sur les types de contenu du sitemap
    if (!previewBox || !titleInput || !descInput) return;

    const previewTitle = previewBox.querySelector(".seo-title");
    const previewDesc = previewBox.querySelector(".seo-desc");
    const titleCount = document.getElementById("hpt_title_count");
    const descCount = document.getElementById("hpt_desc_count");

    // Un champ vide applique le motif, affiché en placeholder
    function effective(input) {
        return input.value.trim() || input.placeholder || "";
    }

    function updateCount(input, counter, max) {
        let length = effective(input).length;
        counter.textContent = `${length}/${max}`;

        if (length > max) {
            counter.style.color = "red";
        } else if (length >= max * 0.8) {
            counter.style.color = "orange";
        } else {
            counter.style.color = "green";
        }
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

    imageInput.addEventListener("input", function () {
        const url = effective(imageInput);
        let img = previewBox.querySelector("img");
        // Sans image par défaut, le placeholder n'est qu'un texte d'aide : pas d'aperçu plutôt qu'une image cassée
        if (!/^https?:\/\//i.test(url)) {
            if (img) img.remove();
            return;
        }
        if (!img) {
            img = document.createElement("img");
            previewBox.appendChild(img);
        }
        img.src = url;
        img.alt = "Prévisualisation SEO";
    });

    // Initialisation de la prévisualisation et des compteurs avec les valeurs actuelles
    updateCount(titleInput, titleCount, 60);
    updateCount(descInput, descCount, 160);
    updatePreview();
});