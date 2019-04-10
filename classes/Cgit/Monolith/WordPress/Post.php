<?php

namespace Cgit\Monolith\WordPress;

/**
 * Access to filtered post properties
 *
 * It is relatively difficult to get the final, filtered values of various
 * WordPress post properties without printing them inside the loop. This class
 * provides easier access to these properties, including the post title, URL,
 * and content.
 */
class Post
{
    /**
     * WordPress post object instance
     *
     * @var WP_Post
     */
    private $post;

    /**
     * Post ID
     *
     * @var int
     */
    private $id;

    /**
     * Post title
     *
     * @var string
     */
    private $title;

    /**
     * Post URL
     *
     * @var string
     */
    private $url;

    /**
     * Post content
     *
     * @var string
     */
    private $content;

    /**
     * Post excerpt
     *
     * @var string
     */
    private $excerpt;

    /**
     * Post featured image as Image instance
     *
     * @var Image
     */
    private $image;

    /**
     * Constructor
     *
     * Finds the original post object and sets the values of the post properties
     * in this instance to their final, filtered versions.
     *
     * @param mixed $post
     * @return void
     */
    public function __construct($post)
    {
        // Check that the parameter provided is a valid post object or post ID
        // and update the corresponding properties.
        $this->setPost($post);

        // Set the filtered content and excerpt properties, based on the raw
        // content from the original post object.
        $this->setPostContent();
        $this->setPostExcerpt();

        // Set the post title and URL using the default WordPress functions,
        // which provide the filtered values.
        $this->title = get_the_title($this->post);
        $this->url = get_permalink($this->post);
    }

    /**
     * Set the post object and ID
     *
     * Given a post object or valid post ID, this will set the appropriate post
     * object and ID properties. If the post does not exist, an error will be
     * triggered.
     *
     * @param mixed $post
     * @return void
     */
    private function setPost($post)
    {
        $post_object = get_post($post);

        if (!$post_object) {
            return trigger_error('Post ' . strval($post) . ' not found');
        }

        $this->post = $post_object;
        $this->id = $post_object->ID;
    }

    /**
     * Set post content
     *
     * Applies filters so that the HTML content is exactly the same as the
     * content output by the_content() in the loop.
     *
     * @return void
     */
    private function setPostContent()
    {
        $this->content = apply_filters(
            'the_content',
            $this->post->post_content
        );
    }

    /**
     * Set post excerpt
     *
     * Attempts to use the manual excerpt for the post before falling back to an
     * excerpt generated from the main content. Unlike the_excerpt(), this will
     * work outside the loop. Unlike get_the_excerpt(), this will not fall back
     * to the excerpt of the current post object if the target post excerpt is
     * not found.
     *
     * This function uses the same process and the same default values as
     * WordPress to generate the excerpt. The same filters are applied to edit
     * the length of the excerpt and the "more" string.
     *
     * @return void
     */
    private function setPostExcerpt()
    {
        $excerpt = $this->post->post_excerpt;
        $length = apply_filters('excerpt_length', 55);
        $more = apply_filters('excerpt_more', ' [&hellip;]');

        if (!$excerpt) {
            $excerpt = wp_trim_words($this->content, $length, $more);
        }

        $this->excerpt = apply_filters('the_excerpt', $excerpt);
    }

    /**
     * Normalize headings
     *
     * Promote or demote headings within the HTML content to fit the surrounding
     * document outline.
     *
     * @param int $limit
     * @return self
     */
    public function normalizeHeadings($limit = 2)
    {
        $levels = range(1, 6);
        $diff = 0;

        // Identify the difference between the current maximum heading level and
        // the desired maximum heading level.
        foreach ($levels as $level) {
            if (strpos($this->content, '<h' . $level) !== false) {
                $diff = $limit - $level;
                break;
            }
        }

        // If there is no difference between the current and the desired maximum
        // heading levels, return the content unmodified.
        if ($diff == 0 || !in_array($limit, $levels)) {
            return $this;
        }

        // Promote or demote the headings using a callback that can calculate
        // the necessary find and replace parameters on the fly.
        $this->content = preg_replace_callback(
            '/(<\/?)h(\d)/',
            function ($matches) use ($levels, $diff) {
                $level = intval($matches[2]) + $diff;
                $tag = in_array($level, $levels) ? 'h' . $level : 'p';

                return $matches[1] . $tag;
            },
            $this->content
        );

        return $this;
    }

    /**
     * Reset headings
     *
     * Undo the effects the normalize headings method, restoring the content to
     * its original filtered state.
     *
     * @return self
     */
    public function resetHeadings()
    {
        $this->setPostContent();

        return $this;
    }

    /**
     * Return post object
     *
     * @return WP_Post
     */
    public function post()
    {
        return $this->post;
    }

    /**
     * Return post ID
     *
     * @return int
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * Return post title
     *
     * @return string
     */
    public function title()
    {
        return $this->title;
    }

    /**
     * Return post url
     *
     * @return string
     */
    public function url()
    {
        return $this->url;
    }

    /**
     * Return post content
     *
     * @return string
     */
    public function content()
    {
        return $this->content;
    }

    /**
     * Return post excerpt
     *
     * @return string
     */
    public function excerpt()
    {
        return $this->excerpt;
    }

    /**
     * Return post date
     *
     * If a PHP date format string is provided, the date will be returned in
     * that format. Otherwise, the date will be formatted according to the
     * WordPress date_format option.
     *
     * @param string $format
     * @return string
     */
    public function date($format = '')
    {
        return get_the_date($format, $this->post);
    }

    /**
     * Return ACF field value
     *
     * @param string $field
     * @return mixed
     */
    public function field($field)
    {
        if (!function_exists('get_field')) {
            return;
        }

        return get_field($field, $this->id);
    }

    /**
     * Return featured image as Image instance
     *
     * @return Image
     */
    public function image()
    {
        if (is_null($this->image)) {
            $this->image = new Image($this->id);
        }

        return $this->image;
    }
}
