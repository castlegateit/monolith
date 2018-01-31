<?php

namespace Cgit\Monolith\WordPress;

/**
 * Enqueue styles and scripts
 *
 * A slightly more abstracted and intelligent way of enqueueing CSS and
 * JavaScript files, with automatic resource handle generation, automatic
 * version numbers for cache-busting, and basic resource type detection. It
 * should not be necessary to use this class directly; the Terminus::enqueue
 * method provides a friendlier interface to it.
 */
class Resource
{
    /**
     * Source string
     *
     * The original string used to identify the resource when it was enqueued,
     * which might be a registered handle, a relative path, an absolute path, or
     * a URL.
     *
     * @var string
     */
    private $source;

    /**
     * Resource handle
     *
     * The unique resource handle used to identify the resource within
     * WordPress. This might be a pre-registered handle or a handle based on the
     * original source string generated by this class.
     *
     * @var string
     */
    private $handle;

    /**
     * List of dependencies
     *
     * @var array
     */
    private $deps;

    /**
     * Is the resource a script?
     *
     * @var boolean
     */
    private $script = false;

    /**
     * Is the resource stored in the parent theme?
     *
     * @var boolean
     */
    private $parent = false;

    /**
     * Theme directory
     *
     * @var string
     */
    private $themeDir;

    /**
     * Theme directory URL
     *
     * @var string
     */
    private $themeUrl;

    /**
     * Constructor
     *
     * Set properties for the source string, the resource handle, and any
     * dependencies. Attempt to detect the resource type.
     *
     * @param string $source
     * @param array $deps
     * @return void
     */
    public function __construct($source, $deps)
    {
        $this->source = $source;
        $this->handle = self::sanitize($source);
        $this->deps = self::sanitize($deps);

        // Set default resource type and theme directory
        $this->detectScript();
        $this->setParent(false);
    }

    /**
     * Sanitize strings to resource handles
     *
     * Remove forbidden characters from source string(s) to create
     * WordPress-compatible resource identifiers.
     *
     * @param mixed $source
     * @return mixed
     */
    private static function sanitize($source)
    {
        if (is_array($source)) {
            $strs = [];

            foreach ($source as $str) {
                $strs[] = self::sanitize($str);
            }

            return $strs;
        }

        return sanitize_title($source);
    }

    /**
     * Detect script resources
     *
     * A crude attempt to determine the resource type based on the file
     * extension: if it ends in ".js", it must be JavaScript :)
     *
     * @return void
     */
    private function detectScript()
    {
        $this->setScript(substr($this->source, -3) == '.js');
    }

    /**
     * Set resource type to script
     *
     * @param boolean $script
     * @return self
     */
    public function setScript($script)
    {
        $this->script = (bool) $script;

        return $this;
    }

    /**
     * Set resource location to parent theme
     *
     * @param boolean $parent
     * @return self
     */
    public function setParent($parent)
    {
        $this->parent = (bool) $parent;

        if ($this->parent) {
            $this->themeDir = get_template_directory();
            $this->themeUrl = get_template_directory_uri();
        } else {
            $this->themeDir = get_stylesheet_directory();
            $this->themeUrl = get_stylesheet_directory_uri();
        }

        return $this;
    }

    /**
     * Get resource source string
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get resource handle
     *
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Enqueue resource
     *
     * Registered resources are enqueued from WordPress. Absolute paths and URLs
     * are enqueued unmodified. Relative paths are enqueued with a version
     * number based on the time the file was last modified.
     *
     * @return void
     */
    public function enqueue()
    {
        // Which function should we use to enqueue resources?
        $function = $this->script ? 'wp_enqueue_script' : 'wp_enqueue_style';

        // Enqueue registered resources
        if (wp_style_is($this->source, 'registered')) {
            return wp_enqueue_style($this->source);
        }

        if (wp_script_is($this->source, 'registered')) {
            return wp_enqueue_script($this->source);
        }

        // Enqueue resources by URL
        if (!is_null(parse_url($this->source, PHP_URL_HOST))) {
            return $function($this->handle, $this->source, $this->deps);
        }

        // Set the path and URL of absolute and relative resources
        if (substr($this->source, 0, 1) == '/') {
            $path = $_SERVER['DOCUMENT_ROOT'] . $this->source;
            $url = $this->source;
        } else {
            $path = $this->themeDir . '/' . $this->source;
            $url = $this->themeUrl . '/' . $this->source;
        }

        // Check the resource exists
        if (!file_exists($path)) {
            return trigger_error('Resource not found: ' . $this->source);
        }

        // Add a version number and enqueue
        return $function($this->handle, $url, $this->deps, filemtime($path));
    }
}