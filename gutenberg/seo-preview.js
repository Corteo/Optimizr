/*
 * Boîte « SEO & partage » de l'éditeur (functions/seo/seo-meta.php) :
 * compteurs, pastilles du résumé, interrupteur « Visible dans Google », aperçus Google et partage,
 * liens « Revenir au motif » et choix de l'image dans la médiathèque.
 */
document.addEventListener("DOMContentLoaded", function () {
    const { __ } = wp.i18n;
    const box = document.querySelector(".optimizr-seo");
    const titleInput = document.getElementById("haptic_og_title");
    const descInput = document.getElementById("haptic_og_desc");
    const imageInput = document.getElementById("haptic_og_image");
    const previewBox = document.getElementById("haptic_seo_preview");
    // La metabox SEO n'est présente que sur les types de contenu du sitemap
    if (!box || !previewBox || !titleInput || !descInput) return;

    let labels = {};
    try {
        labels = JSON.parse(box.dataset.labels || "{}");
    } catch (e) {
        labels = {};
    }
    const label = (key, fallback) => labels[key] || fallback;

    // Un champ vide applique le motif, affiché en placeholder
    function effective(input) {
        return input.value.trim() || input.placeholder || "";
    }

    // Orange sous le minimum conseillé, vert dans la zone idéale, rouge au-delà du maximum
    function stateOf(length, min, max) {
        return length > max ? "bad" : length >= min ? "ok" : "warn";
    }

    function updateCount(input, counter, max) {
        const length = effective(input).length;
        const meter = counter.closest(".optimizr-seo__meter");
        counter.textContent = `${length}/${max}`;
        if (!meter) return "ok";

        const min = parseInt(meter.dataset.min, 10) || Math.round(max * 0.8);
        // La barre va un quart au-delà du maximum pour montrer le dépassement
        const scale = max * 1.25;
        meter.style.setProperty("--osb-fill", `${Math.min(length / scale, 1) * 100}%`);
        meter.style.setProperty("--osb-ideal-from", `${(min / scale) * 100}%`);
        meter.style.setProperty("--osb-ideal-width", `${((max - min) / scale) * 100}%`);

        const state = stateOf(length, min, max);
        meter.classList.remove("is-ok", "is-warn", "is-bad");
        meter.classList.add(`is-${state}`);
        return state;
    }

    function setChip(name, state, text) {
        const chip = box.querySelector(`[data-chip="${name}"]`);
        if (!chip) return;
        chip.className = `optimizr-seo__chip is-${state}`;
        chip.textContent = text;
    }

    const titleCount = document.getElementById("haptic_title_count");
    const descCount = document.getElementById("haptic_desc_count");

    function updateText() {
        box.querySelectorAll('[data-out="title"]').forEach((el) => (el.textContent = effective(titleInput)));
        box.querySelectorAll('[data-out="desc"]').forEach((el) => (el.textContent = effective(descInput)));

        const titleState = updateCount(titleInput, titleCount, 60);
        const descState = updateCount(descInput, descCount, 160);
        setChip("title", titleState, label("title", "Title: %s").replace("%s", label(titleState, titleState)));
        setChip("desc", descState, label("desc", "Description: %s").replace("%s", label(descState, descState)));
        updateResets();
    }

    // ---------- Image de partage : vignettes du champ et des deux aperçus ----------
    const imageThumbs = box.querySelectorAll("[data-image-thumb]");

    function updateImage() {
        if (!imageInput) return;
        const custom = imageInput.value.trim();
        const url = custom || imageInput.dataset.default || "";
        // Sans image par défaut, le placeholder n'est qu'un texte d'aide : pas de vignette plutôt qu'une image cassée
        const valid = /^https?:\/\//i.test(url);

        imageThumbs.forEach((thumb) => {
            const img = thumb.querySelector("img");
            if (valid) {
                if (img.getAttribute("src") !== url) img.src = url;
                img.alt = __("SEO preview", "optimizr");
            } else {
                img.removeAttribute("src");
            }
            // La vignette de l'aperçu Google disparaît ; les autres affichent un emplacement vide
            if (thumb.classList.contains("seo-thumb")) {
                thumb.hidden = !valid;
            } else {
                thumb.toggleAttribute("data-empty", !valid);
            }
        });

        setChip(
            "image",
            valid ? "ok" : "warn",
            !valid ? label("imageNone", "Image: none") : custom ? label("imageCustom", "Image: chosen") : label("imageDefault", "Image: featured")
        );
        updateResets();
    }

    // Adresse introuvable : la vignette se replie
    imageThumbs.forEach((thumb) => {
        const img = thumb.querySelector("img");
        img.addEventListener("error", function () {
            if (thumb.classList.contains("seo-thumb")) thumb.hidden = true;
            else thumb.setAttribute("data-empty", "");
        });
    });

    // ---------- « Revenir au motif » : visible seulement sur un champ personnalisé ----------
    function updateResets() {
        box.querySelectorAll("[data-reset]").forEach((button) => {
            const field = document.getElementById(button.dataset.reset);
            button.hidden = !field || field.value.trim() === "";
        });
    }

    box.addEventListener("click", function (event) {
        const reset = event.target.closest("[data-reset]");
        if (reset) {
            const field = document.getElementById(reset.dataset.reset);
            if (field) {
                field.value = "";
                field.dispatchEvent(new Event("input", { bubbles: true }));
                field.focus();
            }
            return;
        }

        const tab = event.target.closest("[data-preview]");
        if (tab) {
            box.querySelectorAll("[data-preview]").forEach((b) => b.setAttribute("aria-pressed", String(b === tab)));
            box.querySelectorAll("[data-preview-panel]").forEach((panel) => {
                panel.hidden = panel.dataset.previewPanel !== tab.dataset.preview;
            });
            return;
        }

        if (event.target.closest("[data-media-pick]")) {
            openMedia();
        }
    });

    // ---------- Médiathèque ----------
    let frame = null;
    function openMedia() {
        if (!imageInput || !window.wp || !wp.media) {
            imageInput && imageInput.focus();
            return;
        }
        if (!frame) {
            frame = wp.media({
                title: label("mediaTitle", "Share image"),
                button: { text: label("mediaButton", "Use this image") },
                library: { type: "image" },
                multiple: false,
            });
            frame.on("select", function () {
                const attachment = frame.state().get("selection").first().toJSON();
                // Taille « large » si elle existe : suffisante pour les partages, plus légère que l'original
                const size = attachment.sizes && attachment.sizes.large ? attachment.sizes.large : attachment;
                imageInput.value = size.url || attachment.url;
                imageInput.dispatchEvent(new Event("input", { bubbles: true }));
            });
        }
        frame.open();
    }

    // ---------- Visible dans Google ----------
    const visible = document.getElementById("haptic_seo_visible");
    const summary = box.querySelector(".optimizr-seo__summary");
    const visibilityLabel = box.querySelector("[data-visibility-label]");

    function updateVisibility() {
        if (!visible) return;
        const hidden = !visible.checked;
        summary && summary.classList.toggle("is-hidden", hidden);
        previewBox.classList.toggle("is-noindex", hidden);
        if (visibilityLabel) visibilityLabel.textContent = hidden ? label("hidden", "Hidden from Google (noindex)") : label("visible", "Visible in Google");
    }

    // L'étiquette de l'aperçu masqué vient du PHP (traduite)
    previewBox.style.setProperty("--osb-noindex-label", JSON.stringify(previewBox.dataset.noindexLabel || ""));

    titleInput.addEventListener("input", updateText);
    descInput.addEventListener("input", updateText);
    if (imageInput) imageInput.addEventListener("input", updateImage);
    if (visible) visible.addEventListener("change", updateVisibility);

    // Initialisation avec les valeurs actuelles
    updateText();
    updateImage();
    updateVisibility();
});
