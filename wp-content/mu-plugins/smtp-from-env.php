<?php
/**
 * Plugin Name: SMTP depuis l'environnement
 * Description: Envoie les e-mails de WordPress par SMTP quand les variables MAIL_* sont definies (dev : Mailtrap). Sans elles, ne fait rien.
 *
 * Variables (docker-compose.yml, fichier .env) :
 *   MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD,
 *   MAIL_ENCRYPTION (tls|ssl, facultatif), MAIL_FROM_ADDRESS (facultatif).
 *
 * Volontairement hors du theme : le theme reste generique, et la production
 * choisit son propre envoi (extension SMTP ou ces memes variables).
 */

defined('ABSPATH') || exit;

function mba_smtp_env($key) {
    $value = getenv($key);
    return $value === false ? '' : trim((string) $value);
}

if (mba_smtp_env('MAIL_MAILER') === 'smtp' && mba_smtp_env('MAIL_HOST') !== '') {
    add_action('phpmailer_init', function ($mailer) {
        $mailer->isSMTP();
        $mailer->Host     = mba_smtp_env('MAIL_HOST');
        $mailer->Port     = (int) (mba_smtp_env('MAIL_PORT') ?: 587);
        $mailer->Username = mba_smtp_env('MAIL_USERNAME');
        $mailer->Password = mba_smtp_env('MAIL_PASSWORD');
        $mailer->SMTPAuth = $mailer->Username !== '';

        $encryption = strtolower(mba_smtp_env('MAIL_ENCRYPTION'));
        if (in_array($encryption, array('tls', 'ssl'), true)) {
            $mailer->SMTPSecure = $encryption;
        }
    });

    // Expediteur : MAIL_FROM_ADDRESS, sinon celui de WordPress. En local,
    // WordPress propose « wordpress@localhost », refuse par PHPMailer (pas de
    // domaine valide) : on prend alors l'e-mail d'administration du site.
    add_filter('wp_mail_from', function ($default) {
        $from = mba_smtp_env('MAIL_FROM_ADDRESS');
        if ($from !== '' && is_email($from)) {
            return $from;
        }
        $domain = substr(strrchr((string) $default, '@'), 1);
        if (!is_email($default) || strpos((string) $domain, '.') === false) {
            $admin = get_option('admin_email');
            return is_email($admin) ? $admin : $default;
        }
        return $default;
    }, 99);
}
