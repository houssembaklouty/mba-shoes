<?php
/**
 * Creation generique d'un produit variable (production).
 * Usage : wp eval-file create_product_var.php '<json>'
 * JSON : {name, sku, cat, price, default_color, default_size,
 *         sizes: ["40",...],
 *         colors: [{slug, name?, hex?, file, title}, ...]}
 * La 1re couleur fournit l'image principale ; les autres vont en galerie.
 *
 * Recherche les images par NOM DE FICHIER dans la mediatheque (jamais par
 * ID suppose) et accepte jpeg/png/webp (mime detecte).
 */

defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-admin/includes/image.php';

$spec = json_decode(isset($args) && $args ? $args[0] : '{}', true);
foreach (array('name', 'sku', 'cat', 'price', 'sizes', 'colors') as $k) {
    if (empty($spec[$k])) {
        wp_die("Parametre manquant : $k");
    }
}

if (wc_get_product_id_by_sku($spec['sku'])) {
    echo "PRODUIT_DEJA_LA\n";
    return;
}

/** Retrouve l'ID d'une piece jointe par nom de fichier, 0 si absente. */
function wcmcs_find_attachment_by_file($file) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
         WHERE p.post_type = 'attachment'
           AND m.meta_key = '_wp_attached_file'
           AND m.meta_value LIKE %s
         LIMIT 1",
        '%' . $wpdb->esc_like(basename($file))
    ));
}

/** Enregistre un fichier de uploads/ en mediatheque si absent. */
function wcmcs_register_image($file, $title) {
    $existing = wcmcs_find_attachment_by_file($file);
    if ($existing) {
        return $existing;
    }
    $path = wp_get_upload_dir()['basedir'] . '/' . $file;
    if (!file_exists($path)) {
        wp_die("Fichier introuvable : {$file}");
    }
    $att = wp_insert_attachment(array(
        'post_mime_type' => wp_check_filetype($path)['type'] ?: 'image/jpeg',
        'post_title'     => $title,
        'post_status'    => 'inherit',
    ), $file, 0, true);
    if (is_wp_error($att)) {
        wp_die($att->get_error_message());
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $path));
    return (int) $att;
}

// --- Images ----------------------------------------------------------------
foreach ($spec['colors'] as $i => $c) {
    $spec['colors'][$i]['id'] = wcmcs_register_image($c['file'], $c['title']);
}

// --- Termes couleur (creation + pastille hex si fournis) --------------------
$color_ids = array();
foreach ($spec['colors'] as $i => $c) {
    $term = get_term_by('slug', $c['slug'], 'pa_color');
    if (!$term) {
        $r = wp_insert_term($c['name'], 'pa_color', array('slug' => $c['slug']));
        $term = get_term(is_wp_error($r) ? 0 : (int) $r['term_id'], 'pa_color');
    }
    if (!empty($c['hex'])) {
        update_term_meta($term->term_id, 'wparabic_color', $c['hex']);
    }
    if ($term) {
        $color_ids[] = $term->term_id;
    }
}

// --- Pointures (creation si absentes) ----------------------------------------
$size_ids = array();
foreach ($spec['sizes'] as $s) {
    $t = get_term_by('slug', (string) $s, 'pa_size');
    if (!$t) {
        $r = wp_insert_term((string) $s, 'pa_size', array('slug' => (string) $s));
        $t = get_term(is_wp_error($r) ? 0 : (int) $r['term_id'], 'pa_size');
    }
    if ($t) {
        $size_ids[] = $t->term_id;
    }
}

// --- Produit --------------------------------------------------------------------
$cat = get_term_by('slug', $spec['cat'], 'product_cat');
if (!$cat) {
    $r = wp_insert_term($spec['cat'], 'product_cat', array('slug' => $spec['cat']));
    $cat = get_term(is_wp_error($r) ? 0 : (int) $r['term_id'], 'product_cat');
}

$product = new WC_Product_Variable();
$product->set_name($spec['name']);
$product->set_sku($spec['sku']);
$product->set_status('publish');
$product->set_category_ids(array($cat->term_id));
$product->set_image_id($spec['colors'][0]['id']);
$product->set_gallery_image_ids(array_slice(array_column($spec['colors'], 'id'), 1));

$attr_color = new WC_Product_Attribute();
$attr_color->set_id(wc_attribute_taxonomy_id_by_name('pa_color'));
$attr_color->set_name('pa_color');
$attr_color->set_options($color_ids);
$attr_color->set_position(0);
$attr_color->set_visible(true);
$attr_color->set_variation(true);

$attr_size = new WC_Product_Attribute();
$attr_size->set_id(wc_attribute_taxonomy_id_by_name('pa_size'));
$attr_size->set_name('pa_size');
$attr_size->set_options($size_ids);
$attr_size->set_position(1);
$attr_size->set_visible(true);
$attr_size->set_variation(true);

$product->set_attributes(array($attr_color, $attr_size));
$product->save();

foreach ($spec['colors'] as $c) {
    foreach ($size_ids as $size_id) {
        $size = get_term($size_id, 'pa_size');
        $v = new WC_Product_Variation();
        $v->set_parent_id($product->get_id());
        $v->set_attributes(array('pa_color' => $c['slug'], 'pa_size' => $size->slug));
        $v->set_regular_price((string) $spec['price']);
        $v->set_image_id($c['id']);
        $v->save();
    }
}

$product->set_default_attributes(array(
    'pa_color' => $spec['default_color'] ?: $spec['colors'][0]['slug'],
    'pa_size'  => (string) ($spec['default_size'] ?: $spec['sizes'][0]),
));
$product->save();

// --- Controle : aucune variation incomplete ------------------------------------
$completes = 0;
foreach ($product->get_children() as $vid) {
    $v = wc_get_product($vid);
    if ($v && $v->get_regular_price() && $v->get_image_id('edit')) {
        $completes++;
    }
}
printf("PRODUIT_CREE id=%d variations=%d completes=%d\n", $product->get_id(), count($product->get_children()), $completes);
