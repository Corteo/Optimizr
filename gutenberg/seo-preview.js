document.addEventListener("DOMContentLoaded", function () {
    const titleInput = document.getElementById("hpt_og_title");
    const descInput = document.getElementById("hpt_og_desc");
    const imageInput = document.getElementById("hpt_og_image");

    const previewBox = document.getElementById("hpt_seo_preview");
    const previewTitle = previewBox.querySelector(".seo-title");
    const previewDesc = previewBox.querySelector(".seo-desc");
    const titleCount = document.getElementById("hpt_title_count");
    const descCount = document.getElementById("hpt_desc_count");

    function updateCount(input, counter, max) {
        let length = input.value.length;
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
        previewTitle.textContent = titleInput.value || "Exemple de titre SEO";
        previewDesc.textContent = descInput.value || "Exemple de description SEO générée automatiquement.";
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
        let img = previewBox.querySelector("img");
        if (!img) {
            img = document.createElement("img");
            previewBox.appendChild(img);
        }
        img.src = imageInput.value;
        img.alt = "Prévisualisation SEO";
    });

    // Initialisation de la prévisualisation et des compteurs avec les valeurs actuelles
    updateCount(titleInput, titleCount, 60);
    updateCount(descInput, descCount, 160);
    updatePreview();
});