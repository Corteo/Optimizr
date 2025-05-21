<?php
// Chemin : functions/seo/seo-sitemap.php

// Génère le sitemap XML
function haptic_GPT_generate_sitemap($return_output = false)
{
    $post_types = get_option('hpt_sitemap_post_types', ['post', 'page']);
    $post_types = array_filter($post_types);

    $posts = get_posts([
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => -1,
    ]);

    $xml = new DOMDocument('1.0', 'UTF-8');
    $urlset = $xml->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    foreach ($posts as $post) {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc', get_permalink($post));
        $lastmod = $xml->createElement('lastmod', get_the_modified_time('c', $post));
        $url->appendChild($loc);
        $url->appendChild($lastmod);
        $urlset->appendChild($url);
    }

    $xml->appendChild($urlset);
    if ($return_output) {
        return $xml->saveXML();
    } else {
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml->saveXML();
        exit;
    }
}

// Point d'accès du sitemap
function haptic_GPT_add_sitemap_endpoint()
{
    add_rewrite_rule('^sitemap\.xml$', 'index.php?hpt_sitemap=1', 'top');
}
add_action('init', 'haptic_GPT_add_sitemap_endpoint');

// Traitement de la requête
function haptic_GPT_sitemap_query_var($vars)
{
    $vars[] = 'hpt_sitemap';
    return $vars;
}
add_filter('query_vars', 'haptic_GPT_sitemap_query_var');

function haptic_GPT_template_redirect()
{
    if (get_query_var('hpt_sitemap')) {
        haptic_GPT_generate_sitemap();
    }
}
add_action('template_redirect', 'haptic_GPT_template_redirect');


// --- Génération automatique du sitemap dans un fichier physique ---
function haptic_GPT_write_sitemap_to_file()
{
    $sitemap_content = haptic_GPT_generate_sitemap(true);
    file_put_contents(ABSPATH . 'sitemap.xml', $sitemap_content);
    haptic_GPT_generate_html_sitemap();
    update_option('hpt_sitemap_last_generated', current_time('mysql'));
    haptic_GPT_log_sitemap_regeneration(wp_doing_cron() ? 'cron' : 'manuel');
}

// Génère automatiquement un sitemap lors des mises à jour de contenu
add_action('save_post', 'haptic_GPT_write_sitemap_to_file');
add_action('delete_post', 'haptic_GPT_write_sitemap_to_file');
add_action('publish_post', 'haptic_GPT_write_sitemap_to_file');


// --- Modification dynamique du contenu du robots.txt ---
function haptic_GPT_add_robots_sitemap($output, $public)
{
    $output .= "\nSitemap: " . home_url('/sitemap.xml');
    return $output;
}
add_filter('robots_txt', 'haptic_GPT_add_robots_sitemap', 10, 2);


// --- Tâche cron quotidienne pour générer le sitemap ---
if (!wp_next_scheduled('haptic_GPT_daily_sitemap_cron')) {
    wp_schedule_event(time(), 'daily', 'haptic_GPT_daily_sitemap_cron');
}
add_action('haptic_GPT_daily_sitemap_cron', 'haptic_GPT_write_sitemap_to_file');

// --- Journalisation des écritures de sitemap ---
function haptic_GPT_log_sitemap_regeneration($origin = 'automatique')
{
    $log_entry = sprintf(
        "[%s] Génération du sitemap (%s)\n",
        current_time('mysql'),
        $origin
    );
    $log_file = plugin_dir_path(__FILE__) . 'sitemap.log';
    // Force l'encodage UTF-8 du log
    $log_entry = mb_convert_encoding($log_entry, 'UTF-8', mb_detect_encoding($log_entry, 'UTF-8, ISO-8859-1', true));
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function haptic_GPT_generate_html_sitemap()
{
    $post_types = get_option('hpt_sitemap_post_types', ['post', 'page']);
    $post_types = array_filter($post_types);

    // Dernières pages modifiées
    $recently_modified = get_posts([
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => 6,
        'orderby' => 'modified',
        'order' => 'DESC',
    ]);

    // Reste des contenus par date de publication
    $others = get_posts([
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'post__not_in' => wp_list_pluck($recently_modified, 'ID'),
    ]);

    ob_start();
?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <title>Plan du site</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>

    <body class="bg-light">
        <div class="container my-5">
            <h1 class="mb-4">Plan du site - <?php echo bloginfo('title'); ?></h1>

            <h2 class="h4">🕒 Dernières pages modifiées</h2>
            <div class="row row-cols-1 row-cols-md-2 g-4 mb-5">
                <?php foreach ($recently_modified as $post) : setup_postdata($post); ?>
                    <div class="col">
                        <div class="card h-100 border-warning">
                            <div class="card-body">
                                <h5 class="card-title mb-1">
                                    <a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
                                </h5>
                                <p class="card-text"><?php echo esc_html(wp_strip_all_tags(get_the_excerpt($post))); ?></p>
                                <p class="mb-1"><small class="text-muted">Modifié le <?php echo esc_html(get_the_modified_date('d/m/Y', $post)); ?></small></p>
                                <p class="mb-0"><strong>Type :</strong> <?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach;
                wp_reset_postdata(); ?>
            </div>

            <h2 class="h4">📄 Tous les contenus (par date de publication)</h2>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php foreach ($others as $post) : setup_postdata($post); ?>
                    <div class="col">
                        <div class="card h-100">
                            <?php if (has_post_thumbnail($post)) : ?>
                                <img src="<?php echo esc_url(get_the_post_thumbnail_url($post, 'medium')); ?>" class="card-img-top" alt="<?php echo esc_attr(get_the_title($post)); ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
                                </h5>
                                <p class="card-text"><?php echo esc_html(wp_strip_all_tags(get_the_excerpt($post))); ?></p>
                                <p class="mb-1"><strong>Type :</strong> <?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?></p>
                                <p class="mb-1"><small class="text-muted">Publié le <?php echo esc_html(get_the_date('d/m/Y', $post)); ?></small></p>
                                <?php
                                $categories = get_the_category($post);
                                $tags = get_the_tags($post);
                                ?>
                                <?php if (!empty($categories)) : ?>
                                    <div class="mb-1">
                                        <strong>Catégories :</strong>
                                        <?php foreach ($categories as $cat) : ?>
                                            <span class="badge bg-primary"><?php echo esc_html($cat->name); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($tags)) : ?>
                                    <div class="mb-1">
                                        <strong>Étiquettes :</strong>
                                        <?php foreach ($tags as $tag) : ?>
                                            <span class="badge bg-secondary"><?php echo esc_html($tag->name); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach;
                wp_reset_postdata(); ?>
            </div>
        </div>
    </body>

    </html>
<?php

    $html = ob_get_clean();
    file_put_contents(ABSPATH . 'sitemap.html', $html);
}
