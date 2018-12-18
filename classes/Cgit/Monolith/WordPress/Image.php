<?php

namespace Cgit\Monolith\WordPress;

/**
 * Consistent access to image attachments
 *
 * This class provides a consistent way of accessing images attachments,
 * featured images, and ACF image fields.
 */
class Image
{
    /**
     * Image ID
     *
     * @var int
     */
    private $id = 0;

    /**
     * Image meta data
     *
     * @var array
     */
    private $meta = [];

    /**
     * Default post ID for featured images
     *
     * @var int
     */
    private $defaultPostId = 0;

    /**
     * Image URLs, widths, and heights
     *
     * @var array
     */
    private $sources = [];

    /**
     * Constructor
     *
     * @param mixed $image
     * @param mixed $post
     * @return void
     */
    public function __construct($image = 0, $post = 0)
    {
        $this->setDefaultPostId();
        $this->set($image, $post);
    }

    /**
     * Set default post ID
     *
     * If we are getting a featured image or a custom field and the post ID has
     * not been specified, this will be used instead.
     *
     * @return void
     */
    private function setDefaultPostId()
    {
        global $post;

        if (is_a($post, 'WP_Post')) {
            $this->defaultPostId = $post->ID;
        }
    }

    /**
     * Load image
     *
     * If the image parameter is a string, assume that it is an ACF field name
     * and use that field. If the image parameter is an image attachment ID or
     * object, use that object. If the image parameter is a post ID or object,
     * attempt to use the featured image for that post.
     *
     * @param mixed $image
     * @param mixed $post
     * @return void
     */
    public function set($image, $post = 0)
    {
        if (is_string($image)) {
            return $this->setImageByField($image, $post);
        }

        if (get_post_type($image) == 'attachment') {
            return $this->setImageByImage($image);
        }

        return $this->setImageByPost($image);
    }

    /**
     * Load image by image object or ID
     *
     * @return void
     */
    private function setImageByImage($image)
    {
        $object = get_post($image);

        if (!$object) {
            return $this->reset();
        }

        $this->id = $object->ID;
        $this->setImageMeta();
    }

    /**
     * Load featured image by post object or ID
     *
     * @return void
     */
    private function setImageByPost($post = 0)
    {
        if (!$post) {
            $post = $this->defaultPostId;
        }

        $object = get_post($post);
        $image_id = get_post_thumbnail_id($object->ID);

        if (!$object || !$image_id) {
            return $this->reset();
        }

        $this->id = $image_id;
        $this->setImageMeta();
    }

    /**
     * Load image from ACF custom field
     *
     * @return void
     */
    private function setImageByField($field, $post = 0)
    {
        if (!function_exists('get_field')) {
            return trigger_error('ACF functions not available');
        }

        $post = $post ?: $this->defaultPostId;
        $object = get_post($post);
        $value = get_field($field, $object->ID);

        // No value
        if (!$value) {
            return $this->reset();
        }

        // Convert ACF array into image ID
        if (is_array($value) && isset($value['id'])) {
            $value = $value['id'];
        }

        $this->setImageByImage($value);
    }

    /**
     * Reset all values
     *
     * @return void
     */
    private function reset()
    {
        $this->id = 0;
        $this->setImageMetaValues();
    }

    /**
     * Set image meta based on the current image ID
     *
     * @return void
     */
    private function setImageMeta()
    {
        // Load the raw post information from WordPress
        $obj = get_post($this->id);
        $meta = get_post_meta($this->id);
        $file = '';
        $alt = '';

        // If the image is not a valid attachment, reset the image meta values
        // and cached data.
        if (!$obj) {
            $this->setImageMetaValues();
            $this->unsetImageSources();

            return;
        }

        if (isset($meta['_wp_attached_file'])) {
            $file = $meta['_wp_attached_file'][0];
        }

        if (isset($meta['_wp_attachment_image_alt'])) {
            $alt = $meta['_wp_attachment_image_alt'][0];
        }

        // Assign the information to the instance property
        $this->setImageMetaValues([
            'url' => $this->url(),
            'file_name' => basename($file),
            'file_path' => wp_upload_dir()['basedir'] . '/' . $file,
            'mime_type' => get_post_mime_type($this->id),
            'title' => $obj->post_title,
            'alt' => $alt,
            'caption' => $obj->post_excerpt,
            'description' => apply_filters('the_content', $obj->post_content),
        ]);

        // Flush cached image URLs and dimensions
        $this->unsetImageSources();
    }

    /**
     * Set image meta values
     *
     * Assign meta values to properties, removing invalid keys and inserting
     * default values for mising keys.
     *
     * @param array $values
     * @return void
     */
    private function setImageMetaValues($values = [])
    {
        $defaults = [
            'url' => '',
            'file_name' => '',
            'file_path' => '',
            'mime_type' => '',
            'title' => '',
            'alt' => '',
            'caption' => '',
            'description' => '',
        ];

        $this->meta = array_merge($defaults,
            array_intersect_key($values, $defaults));
    }

    /**
     * Flush cached image URLs and dimensions
     *
     * @return void
     */
    private function unsetImageSources()
    {
        $this->sources = [];
    }

    /**
     * Sanitize image attributes
     *
     * When provided with an array of key/value pairs representing HTML
     * attributes, this removes any that are not permitted in an HTML image
     * element.
     *
     * @param array $atts
     * @return array
     */
    private static function sanitizeAttributes($atts)
    {
        $permitted = ['alt', 'class', 'id', 'style', 'title'];

        if (!$atts) {
            return [];
        }

        foreach ($atts as $key => $value) {
            if (!in_array($key, $permitted) && strpos($key, 'data-') !== 0) {
                unset($atts[$key]);
            }
        }

        return $atts;
    }

    /**
     * Format HTML attributes
     *
     * Converts an associative array of attribute names and values into a string
     * of HTML attribute(s). Nested arrays are converted into space-separated
     * lists.
     *
     * @param array $atts
     * @return string
     */
    private static function formatAttributes($atts)
    {
        $items = [];

        foreach ($atts as $key => $value) {
            if (is_array($value)) {
                $value = implode(' ', $value);
            }

            $items[] = $key . '="' . $value . '"';
        }

        return implode(' ', $items);
    }

    /**
     * Return image URL, width, or height
     *
     * To reduce the number of database requests, this checks the list of
     * previously queried sources before performing a new request.
     *
     * @param string $key
     * @param string $size
     * @return mixed
     */
    private function source($key = 'url', $size = 'full')
    {
        if (!$this->id) {
            return;
        }

        if (!isset($this->sources[$size])) {
            $keys = ['url', 'width', 'height'];
            $values = wp_get_attachment_image_src($this->id, $size);

            if (!$values) {
                return;
            }

            $this->sources[$size] = array_combine($keys,
                array_slice($values, 0, 3));
        }

        return $this->sources[$size][$key];
    }

    /**
     * Return image ID
     *
     * @return integer
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * Return image URL
     *
     * @param string $size
     * @return string
     */
    public function url($size = 'full')
    {
        return $this->source('url', $size);
    }

    /**
     * Return image width
     *
     * @param string $size
     * @return integer
     */
    public function width($size = 'full')
    {
        return $this->source('width', $size);
    }

    /**
     * Return image height
     *
     * @param string $size
     * @return integer
     */
    public function height($size = 'full')
    {
        return $this->source('height', $size);
    }

    /**
     * Return image meta
     *
     * @param string $field
     * @return mixed
     */
    public function meta($field = null)
    {
        if (is_null($field)) {
            return $this->meta;
        }

        if (isset($this->meta[$field])) {
            return $this->meta[$field];
        }

        return;
    }

    /**
     * Return data URI
     *
     * @param string $size
     * @return string
     */
    public function data($size = false)
    {
        if (!$this->id) {
            return;
        }

        $path = $this->meta('file_path');

        if ($size) {
            $path = str_replace(
                $this->meta('file_name'),
                basename($this->url($size)),
                $path
            );
        }

        return 'data:' . $this->meta('mime_type') . ';base64,'
            . base64_encode(file_get_contents($path));
    }

    /**
     * Return HTML element
     *
     * Provided with a single size, this will create an HTML <img> element with
     * the specified attributes. Provided with an array of sizes, this will
     * create a responsive <picture> element.
     *
     * The data option lets you return the image source as a base64 encoded
     * string. This only applies to <img> elements; responsive base64 encoded
     * <picture> elements would waste bandwidth instead of saving it!
     *
     * @param string $size
     * @param array $atts
     * @param boolean $data
     * @param boolean $dimensions
     * @return string
     */
    public function element($size = 'full', $atts = [], $data = false,
        $dimensions = true)
    {
        if (!$this->id) {
            return;
        }

        if (is_array($size)) {
            return $this->responsiveElement($size, $atts);
        }

        // Restrict the attributes to valid image attributes and set the image
        // src and alt attributes.
        $atts = self::sanitizeAttributes($atts);
        $atts['src'] = $data ? $this->data($size) : $this->url($size);

        if (!isset($atts['alt'])) {
            $atts['alt'] = $this->meta('alt');
        }

        // Add image dimensions?
        if ($dimensions) {
            $atts['width'] = $this->width($size);
            $atts['height'] = $this->height($size);
        }

        // Put the required image attributes at the start of the list and
        // arrange the others in alphabetical order.
        ksort($atts);
        $atts = ['alt' => $atts['alt']] + $atts;
        $atts = ['src' => $atts['src']] + $atts;

        // Return the image element
        return '<img ' . self::formatAttributes($atts) . ' />';
    }

    /**
     * Return responsive HTML element
     *
     * Provided with an array of sizes, this will create a responsive <picture>
     * element with <source> elements for each size.
     *
     * @param array $sizes
     * @param array $atts
     * @return string
     */
    private function responsiveElement($sizes, $atts = [])
    {
        // List of source elements
        $sources = [];

        // Make sure that the alt text is in the array of image attributes, not
        // the array of picture element attributes.
        $picture_atts = array_diff_key($atts, ['alt' => 0]);
        $image_atts = [];

        if (isset($atts['alt'])) {
            $image_atts['alt'] = $atts['alt'];
        }

        ksort($picture_atts);

        // Assemble the list of source elements based on the sizes and media
        // queries submitted.
        foreach ($sizes as $size => $media) {
            $source_atts = [
                'srcset' => $this->url($size),
                'media' => $media,
            ];

            $sources[] = '<source ' . self::formatAttributes($source_atts)
                . ' />';
        }

        // Add an image element to the end of the list of sources, using the
        // last source size as the image size.
        $image_size = array_slice(array_keys($sizes), -1)[0];
        $sources[] = $this->element($image_size, $image_atts);

        // Assemble and return the HTML output
        return '<picture ' . self::formatAttributes($picture_atts) . '>'
            . implode(PHP_EOL, $sources) . '</picture>';
    }
}
