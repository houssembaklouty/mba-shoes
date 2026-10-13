<?php
/**
 * Plugin must-use de durcissement WordPress — 100cuir.com.
 * Pose apres l'incident du 13/10/2026. Sans dependance.
 *
 * 1. XML-RPC totalement desactive (brute force, pingbacks).
 * 2. Enumeration des utilisateurs bloquee (REST /wp/v2/users, ?author=).
 * 3. Limitation des tentatives de connexion : 5 echecs par IP, 15 min.
 * 4. Version de WordPress masquee (generator, en-tetes, flux, scripts).
 * 5. Edition de fichiers desactivee (DISALLOW_FILE_EDIT si non defini).
 */

defined('ABSPATH') || exit;

/* --- 1. XML-RPC : ferme ---------------------------------------------- */
add_filter('xmlrpc_enabled', '__return_false');
// Et la page renvoie une erreur meme avant le filtre (system.multicall etc.)
add_action('xmlrpc_call', function () {
    wp_die('XML-RPC désactivé sur ce site.', 'XML-RPC désactivé', 423);
});

/* --- 2. Enumeration utilisateurs -------------------------------------- */
// REST : la liste des comptes n'est visible que connecte.
add_filter('rest_endpoints', function ($endpoints) {
    if (!is_user_logged_in()) {
        unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});

// ?author=1 : intercepte AVANT que WordPress ne redirige vers
// /author/login/ (qui revelerait le pseudo). Ici : renvoi muet a l'accueil.
add_action('init', function () {
    if (isset($_GET['author']) && !is_user_logged_in()) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});

// /author/... ne revelent plus les logins non plus.
add_action('template_redirect', function () {
    if (is_author()) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});

/* --- 3. Limitation des tentatives de connexion ------------------------ */
// 5 echecs par IP et 15 minutes de blocage (stockage transient, leger).
add_action('wp_login_failed', function ($username) {
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-f:.]/', '', $_SERVER['REMOTE_ADDR']) : 'inconnu';
    $key = 'wph_lock_' . md5($ip);
    $n   = (int) get_transient($key) + 1;
    set_transient($key, $n, 15 * MINUTE_IN_SECONDS);
    if ($n >= 5) {
        wp_die(
            'Trop de tentatives de connexion échouées. Réessayez dans 15 minutes.',
            'Accès temporairement bloqué',
            429
        );
    }
});

// Reussite : on oublie les echecs de l'IP.
add_action('wp_login', function () {
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-f:.]/', '', $_SERVER['REMOTE_ADDR']) : 'inconnu';
    delete_transient('wph_lock_' . md5($ip));
});

/* --- 4. Version WordPress masquee -------------------------------------- */
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');
foreach (array('rss2_head', 'commentsrss2_head', 'atom_head', 'rdf_header') as $hook) {
    add_filter($hook, function () { add_filter('the_generator', '__return_empty_string'); });
}
// ?ver=x.y des scripts : remplace par une valeur fixe.
add_filter('style_loader_src', function ($src) { return remove_query_arg('ver', $src); }, 99);
add_filter('script_loader_src', function ($src) { return remove_query_arg('ver', $src); }, 99);

/* --- 5. Pas d'edition de fichiers via l'admin --------------------------- */
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

/* En-tete anti-clickjacking pour l'admin (defense en profondeur). */
add_action('send_headers', function () {
    if (is_admin()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
});
