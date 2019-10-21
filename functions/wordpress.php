<?php

namespace Cgit\Monolith\WordPress;

/**
 * Enqueue CSS and JavaScript
 *
 * An easier and cache-busting method to enqueue CSS and JavaScript files with
 * version numbers based on the last modified time of the source file. Existing
 * registered file handles (e.g. "jquery") will be enqueued as normal. Absolute
 * paths and URLs will be enqueued unmodified. Relative paths will be enqueued
 * relative to the active theme directory.
 *
 * You can also specify an array of dependencies, specify the resource type
 * (JavaScript true or false), and choose to enqueue relative paths from the
 * parent theme instead of the child theme. Note that, by default, the function
 * will attempt to detect the file type based on its extension.
 *
 * @param mixed $source
 * @param array $deps
 * @param boolean $script
 * @param boolean $parent
 * @return string
 */
function enqueue($source, $deps = [], $script = null, $parent = null) {
    // Enqueue an array of resources, where each one depends on the previous
    // resources in the array.
    if (is_array($source)) {
        foreach ($source as $str) {
            enqueue($str, $deps, $script, $parent);
            $deps[] = $str;
        }

        return;
    }

    // Create and enqueue a new resource
    $resource = new \Cgit\Monolith\WordPress\Resource($source, $deps);

    if (!is_null($script)) {
        $resource->setScript($script);
    }

    if (!is_null($parent)) {
        $resource->setParent($parent);
    }

    $resource->enqueue();

    // Return the resource handle
    return $resource->getHandle();
}

/**
 * Pagination
 *
 * Provides an interface the default WordPress pagination function with
 * sensible default options. Options can be added or overridden in the
 * options array passed to the function.
 *
 * @param array $args
 * @return string
 */
function pagination($args = [])
{
    global $wp_query;

    $defaults = [
        'current' => intval(get_query_var('paged')) ?: 1,
        'total' => $wp_query->max_num_pages,
        'mid_size' => 2,
        'prev_text' => 'Previous',
        'next_text' => 'Next',
    ];

    return paginate_links(array_merge($defaults, $args));
}

/**
 * Post type and taxonomy labels
 *
 * Generates a complete associative array of labels for a custom post type or
 * custom taxonomy, based on single and plural names. If no plural form is
 * provided, the plural will be created by appending "s" to the singular form.
 *
 * @param string $single
 * @param string $plural
 * @return array
 */
function labels($single, $plural = null)
{
    if (is_null($plural)) {
        $plural = $single . 's';
    }

    return [
        'name' => $plural,
        'singular_name' => $single,
        'add_new' => 'Add New',
        'add_new_item' => 'Add New ' . $single,
        'add_or_remove_items' => 'Add or remove ' . $plural,
        'all_items' => 'All ' . $plural,
        'archives' => $single . ' Archives',
        'attributes' => $single . ' Attributes',
        'choose_from_most_used' => 'Choose from the most used ' . $plural,
        'edit_item' => 'Edit ' . $single,
        'insert_into_item' => 'Insert into ' . $single,
        'menu_name' => $plural,
        'new_item' => 'New ' . $single,
        'new_item_name' => 'New ' . $single . ' Name',
        'not_found' => 'No ' . $plural . ' found',
        'not_found_in_trash' => 'No ' . $plural . ' found in Trash',
        'parent_item' => 'Parent ' . $single,
        'parent_item_colon' => 'Parent ' . $single . ':',
        'popular_items' => 'Popular ' . $plural,
        'search_items' => 'Search ' . $plural,
        'separate_items_with_commas' => 'Separate ' . $plural . ' with commas',
        'update_item' => 'Update ' . $single,
        'uploaded_to_this_item' => 'Uploaded to this ' . $single,
        'view_item' => 'View ' . $single,
        'view_items' => 'View ' . $plural,
    ];
}

/**
 * Return SVG file content as safe HTML element
 *
 * This is similar to the SVG embed function in Core, but assumes relative file
 * paths start in the WordPress theme directory.
 *
 * @param string $file
 * @param string $title
 * @param boolean $nofill
 */
function embedSvg($file, $title = false, $nofill = false)
{
    if (!\Cgit\Monolith\Core\startsWith($file, '/')) {
        $file = get_stylesheet_directory() . '/' . $file;
    }

    if (!\Cgit\Monolith\Core\endsWith($file, '.svg')) {
        $file = $file . '.svg';
    }

    return \Cgit\Monolith\Core\embedSvg($file, $title, $nofill);
}

/**
 * Send 404
 *
 * Send a 404 response immediately and prevent any further output. If a 404
 * template is available, this will be output instead.
 *
 * @return void
 */
function send404()
{
    global $wp_query;

    $wp_query->set_404();

    status_header(404);

    $template = get_404_template();

    if ($template) {
        include $template;
    }

    exit;
}

/**
 * Return page(s) based on template
 *
 * @param string $template
 * @param boolean $multiple
 * @return mixed
 */
function pageFromTemplate($template, $multiple = false)
{
    $pages = get_posts([
        'post_type' => 'page',
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => '_wp_page_template',
                'value' => $template,
            ],
        ],
    ]);

    if (!$pages) {
        return null;
    }

    if ($multiple) {
        return $pages;
    }

    return $pages[0];
}
