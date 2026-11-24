<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mise en page des e-mails envoyés par Optimizr (code de connexion, alerte de connexion, nouvelle adresse de connexion),
 * aux couleurs d'Optimizr : bandeau nuit avec le logo, carte blanche, bouton en dégradé (bleu, ou rouge pour une alerte).
 * Les styles sont écrits sur chaque balise et la page est faite de tableaux : c'est ce que lisent le mieux Gmail,
 * Outlook et Apple Mail. Une version texte accompagne chaque e-mail (messageries sans HTML, filtres anti-spam).
 */

/**
 * Envoie un e-mail mis en page.
 *
 * @param string|string[] $to      Destinataire(s)
 * @param string          $subject Objet
 * @param array           $args    tone (ok, warn, bad), label, title, subtitle, paragraphs (string[]), code,
 *                                 details (libellé => valeur), action_text, action_url, note (encadré)
 * @return bool Envoi accepté par wp_mail()
 */
function haptic_mail_send($to, $subject, array $args)
{
    $html = haptic_mail_html($args);
    $text = haptic_mail_text($args);

    // Version texte jointe à cet envoi seulement (wp_mail() la remet à zéro avant chaque e-mail)
    $alt = function ($phpmailer) use ($text) {
        $phpmailer->AltBody = $text;
    };
    add_action('phpmailer_init', $alt);
    $sent = wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    remove_action('phpmailer_init', $alt);

    return $sent;
}

/** Paramètres complétés par défaut. */
function haptic_mail_args(array $args)
{
    return wp_parse_args($args, [
        'tone' => 'ok',
        'label' => '',
        'title' => '',
        'subtitle' => wp_parse_url(home_url(), PHP_URL_HOST),
        'paragraphs' => [],
        'code' => '',
        'details' => [],
        'action_text' => '',
        'action_url' => '',
        'note' => '',
    ]);
}

/** Pied commun : quel site a envoyé l'e-mail. */
function haptic_mail_footer()
{
    $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    /* translators: 1: site name, 2: site address */
    return sprintf(__('Email sent by %1$s (%2$s), protected by Optimizr.', 'optimizr'), $site, wp_parse_url(home_url(), PHP_URL_HOST));
}

/** Version HTML. */
function haptic_mail_html(array $args)
{
    $a = haptic_mail_args($args);
    $tones = [
        'ok' => ['label' => '#7ef0c4', 'button' => '#3858e9', 'gradient' => 'linear-gradient(135deg, #5b7bff, #1d35b4)'],
        'warn' => ['label' => '#ffc46b', 'button' => '#3858e9', 'gradient' => 'linear-gradient(135deg, #5b7bff, #1d35b4)'],
        'bad' => ['label' => '#ff9b9c', 'button' => '#d63638', 'gradient' => 'linear-gradient(135deg, #d63638, #9c2224)'],
    ];
    $t = $tones[$a['tone']] ?? $tones['ok'];
    $font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    $mono = "'JetBrains Mono', SFMono-Regular, Menlo, Consolas, monospace";
    $logo = HAPTIC_OPTIMIZR_URL . 'images/optimizr-mail-logo.png';
    $preheader = $a['paragraphs'] ? $a['paragraphs'][0] : $a['title'];

    $body = '';
    foreach ($a['paragraphs'] as $paragraph) {
        $body .= '<p style="margin:0 0 14px;">' . esc_html($paragraph) . '</p>';
    }
    if ($a['code'] !== '') {
        // Code à recopier : grand, espacé, sélectionnable d'un double-clic
        $body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 18px;"><tr>'
            . '<td style="background-color:#eef1fd; border:1px solid #c9d3fb; border-radius:12px; padding:14px 26px; font-family:' . esc_attr($mono) . '; font-size:34px; line-height:1.1; font-weight:600; letter-spacing:.18em; color:#1d35b4;">'
            . esc_html($a['code']) . '</td></tr></table>';
    }
    if ($a['details']) {
        $rows = '';
        $count = count($a['details']);
        $i = 0;
        foreach ($a['details'] as $name => $value) {
            $top = $i === 0 ? '14px' : '4px';
            $bottom = $i === $count - 1 ? '14px' : '4px';
            $is_url = (bool) preg_match('#^https?://#', (string) $value);
            $cell = $is_url ? '<a href="' . esc_url($value) . '" style="color:#3858e9;">' . esc_html($value) . '</a>' : esc_html($value);
            $rows .= '<tr><td style="padding:' . $top . ' 0 ' . $bottom . ' 18px; width:110px; font-size:13px; color:#50575e; vertical-align:top;">' . esc_html($name) . '</td>'
                . '<td style="padding:' . $top . ' 18px ' . $bottom . ' 0; font-size:14px; color:#1e1e1e; word-break:break-all;">' . $cell . '</td></tr>';
            $i++;
        }
        $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px; background-color:#f6f7f7; border-radius:12px;">' . $rows . '</table>';
    }
    if ($a['note'] !== '') {
        $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;"><tr>'
            . '<td style="background-color:#fcf1e0; border-left:4px solid #d97706; border-radius:8px; padding:12px 16px; font-size:14px; line-height:1.5; color:#8a4b00;">' . esc_html($a['note']) . '</td></tr></table>';
    }
    if ($a['action_url'] !== '') {
        $url = esc_url($a['action_url']);
        $body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 4px;"><tr>'
            . '<td style="border-radius:8px; background-color:' . $t['button'] . '; background-image:' . $t['gradient'] . ';">'
            . '<a href="' . $url . '" style="display:inline-block; padding:13px 24px; font-family:' . esc_attr($font) . '; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;">' . esc_html($a['action_text']) . '</a>'
            . '</td></tr></table>'
            . '<p style="margin:16px 0 0; font-size:13px; line-height:1.5; color:#50575e;">' . esc_html__('Button not working? Copy this address into your browser:', 'optimizr')
            . '<br><a href="' . $url . '" style="color:#3858e9; word-break:break-all;">' . esc_html($a['action_url']) . '</a></p>';
    }

    $subtitle = $a['subtitle'] !== '' ? '<div style="margin-top:6px; font-family:' . esc_attr($font) . '; font-size:14px; color:#a8b2d8; word-break:break-all;">' . esc_html($a['subtitle']) . '</div>' : '';

    return '<!DOCTYPE html><html lang="' . esc_attr(get_bloginfo('language')) . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">'
        . '<title>' . esc_html($a['title']) . '</title>'
        . '<style>@media (max-width:620px){.oc-pad{padding-left:22px !important;padding-right:22px !important}.oc-title{font-size:24px !important}}</style></head>'
        . '<body style="margin:0; padding:0; background-color:#f6f7f7; -webkit-text-size-adjust:100%;">'
        . '<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">' . esc_html($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f7f7;"><tr><td align="center" style="padding:32px 12px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px;">'
        // Bandeau nuit
        . '<tr><td class="oc-pad" style="background-color:#0b1233; background-image:radial-gradient(60% 100% at 90% 0%, rgba(56,88,233,.55), rgba(11,18,51,0) 70%); border-radius:18px 18px 0 0; padding:28px 40px 30px;">'
        . '<img src="' . esc_url($logo) . '" width="144" height="40" alt="Optimizr" style="display:block; border:0; width:144px; height:40px; color:#eef1ff; font:700 18px ' . esc_attr($font) . ';">'
        . '<div style="margin-top:26px; font-family:' . esc_attr($mono) . '; font-size:12px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:' . $t['label'] . ';">' . esc_html($a['label']) . '</div>'
        . '<h1 class="oc-title" style="margin:8px 0 0; font-family:\'Bricolage Grotesque\', ' . esc_attr($font) . '; font-size:28px; line-height:1.15; font-weight:800; color:#eef1ff;">' . esc_html($a['title']) . '</h1>'
        . $subtitle
        . '</td></tr>'
        // Carte
        . '<tr><td class="oc-pad" style="background-color:#ffffff; border:1px solid #e2e4e7; border-top:0; border-radius:0 0 18px 18px; padding:32px 40px 34px; font-family:' . esc_attr($font) . '; font-size:15px; line-height:1.6; color:#1e1e1e;">'
        . $body
        . '</td></tr>'
        // Pied
        . '<tr><td style="padding:22px 40px 0; font-family:' . esc_attr($font) . '; font-size:12px; line-height:1.6; color:#50575e; text-align:center;">' . esc_html(haptic_mail_footer()) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Version texte. */
function haptic_mail_text(array $args)
{
    $a = haptic_mail_args($args);
    $lines = $a['label'] !== '' ? [$a['label'], $a['title']] : [$a['title']];
    if ($a['subtitle'] !== '') {
        $lines[] = $a['subtitle'];
    }
    $lines[] = '';
    foreach ($a['paragraphs'] as $paragraph) {
        $lines[] = $paragraph;
        $lines[] = '';
    }
    if ($a['code'] !== '') {
        $lines[] = $a['code'];
        $lines[] = '';
    }
    foreach ($a['details'] as $name => $value) {
        $lines[] = $name . ' : ' . $value;
    }
    if ($a['details']) {
        $lines[] = '';
    }
    if ($a['note'] !== '') {
        $lines[] = $a['note'];
        $lines[] = '';
    }
    if ($a['action_url'] !== '') {
        $lines[] = $a['action_text'] . ' : ' . $a['action_url'];
        $lines[] = '';
    }
    $lines[] = '--';
    $lines[] = haptic_mail_footer();

    return implode("\n", $lines);
}
