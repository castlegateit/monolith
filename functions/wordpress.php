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
