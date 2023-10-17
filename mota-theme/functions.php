<?php
// Ajouter la prise en charge des images mises en avant
add_theme_support('post-thumbnails');

//Chargement des styles et script
function add_styles_mota()
{
    wp_enqueue_style('styles_mota', get_template_directory_uri() . '/assets/css/styles_mota.css');
}
add_action('wp_enqueue_scripts', 'add_styles_mota');

function add_scripts_mota()
{
    if (is_single()) {
        wp_enqueue_script('mota-single', get_template_directory_uri() . '/assets/js/mota_single.js');
    }
    if (is_page()) {
        wp_enqueue_script('mota-page', get_template_directory_uri() . '/assets/js/mota_page.js');
    }
    wp_enqueue_script('lightbox-mota', get_template_directory_uri() . '/assets/js/lightbox.js');
}

add_action('wp_enqueue_scripts', 'add_scripts_mota');

// Ajout de la propriétée DEFER aux scripts 
function add_defer_attribute($tag, $handle)
{
    $scripts_with_defer = ['mota-single', 'mota-page', 'lightbox-mota'];
    if (in_array($handle, $scripts_with_defer)) {
        return str_replace(' src', ' defer="defer" src', $tag);
    }

    return $tag;
}

add_filter('script_loader_tag', 'add_defer_attribute', 10, 2);

// Création des menus
function register_menus()
{
    register_nav_menus(
        array(
            'main-menu' => 'Main Menu',
            'footer-menu' => 'Footer Menu',
        )
    );
}
add_action('init', 'register_menus');

// Création du custom-post-type
function mota_register_post_types()
{

    // CPT Portfolio
    $labels = array(
        'name' => 'photo',
        'all_items' => 'Tous les photos',
        'singular_name' => 'photo',
        'add_new_item' => 'Ajouter une photo',
        'edit_item' => 'Modifier une photo',
        'menu_name' => 'Photo'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'menu_position' => 5,
        'menu_icon' => 'dashicons-camera',
    );

    register_post_type('photo', $args);


    // Création de taxonomie;

    function create_taxonomy($taxonomy_slug, $singular_name, $plural_name)
    {
        $labels = array(
            'name' => $plural_name,
            'new_item_name' => 'Ajouter un(e) ' . $singular_name,
            'parent_item' => $singular_name . ' parent',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        );

        register_taxonomy($taxonomy_slug, 'photo', $args);
    }

    // Enregistrement de la taxonomie "Catégorie"
    create_taxonomy('categorie', 'Catégorie', 'Catégories');

    // Enregistrement de la taxonomie "Format"
    create_taxonomy('format', 'Format', 'Formats');
}

add_action('init', 'mota_register_post_types');

// Récupération en Ajax des images similaires de la catégorie de la single-page
function mota_load_more_single()
{
    // Vérification de sécurité
    if (
        !isset($_REQUEST['nonce']) or
        !wp_verify_nonce($_REQUEST['nonce'], 'mota_load_more_single')
    ) {
        wp_send_json_error("Vous n’avez pas l’autorisation d’effectuer cette action.", 403);
    }

    // On vérifie que les paramètres ont bien été envoyés
    if (!isset($_POST['postid'])) {
        wp_send_json_error("Le postid est manquant.", 403);
    }
    if (!isset($_POST['catid'])) {
        wp_send_json_error("La catid est manquante.", 403);
    }

    // Récupération des données du formulaire en sécurité
    $postid = intval($_POST['postid']);
    $catid = intval($_POST['catid']);

    $args = array(
        'post_type' => 'photo',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'post__not_in' => array($postid),
        'tax_query' => array(
            array(
                'taxonomy' => 'categorie',
                'field' => 'term_id',
                'terms' => array($catid),
            )
        )
    );

    $codeHTML = '';

    $loop = new WP_Query($args);

    while ($loop->have_posts()) : $loop->the_post();
        ob_start(); // Commence à mettre en mémoire tampon la sortie.
        include 'template_parts/photo_block.php';
        $codeHTML .= ob_get_clean(); // Récupère la sortie tamponnée et l'ajoute à votre variable.
    endwhile;


    wp_reset_postdata();
    wp_send_json($codeHTML);
    wp_die();
}

add_action('wp_ajax_mota_load_more_single', 'mota_load_more_single');
add_action('wp_ajax_nopriv_mota_load_more_single', 'mota_load_more_single');
