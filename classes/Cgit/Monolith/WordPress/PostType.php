<?php

namespace Cgit\Monolith\WordPress;

/**
 * Define custom post type
 *
 * Quickly and consistently define a custom post type with optional taxonomies,
 * ACF custom fields, and pre_get_posts query parameters.
 */
abstract class PostType
{
    /**
     * Post type name
     *
     * The post type name cannot include uppercase characters, underscores, or
     * spaces and it cannot be more than 20 characters long.
     *
     * @var string|null
     */
    protected $type = null;

    /**
     * Post type parameters
     *
     * An array of post type parameters passed to register_post_type. The labels
     * parameter can be omitted to generate a full range of labels automatically
     * based on the label and label_single properties.
     *
     * @var array
     */
    protected $args = [];

    /**
     * Taxonomy names and parameters
     *
     * An array of taxonomies to be registered for this post type. Array keys
     * are the taxonomy names; array values are the taxonomy parameters.
     *
     * @var array
     */
    protected $taxons = [];

    /**
     * ACF custom fields
     *
     * An array of field group parameters, each of which can be used to create a
     * separate ACF field group. Note that this is an array of field groups, not
     * a single field group.
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Query parameters
     *
     * An array of query parameters set on pre_get_posts, where the array keys
     * are the parameters and the values are their corresponding values. You
     * might set posts_per_page, order, or orderby here.
     *
     * @var array
     */
    protected $queryArgs = [];

    /**
     * Construct
     *
     * Perform actions to register the post type, taxonomies, and fields and to
     * edit the main query for the this post type.
     *
     * @return void
     */
    final public function __construct()
    {
        $this->init();
        $this->sanitize();

        if (!is_string($this->type)) {
            return trigger_error('Cannot create post type without name');
        }

        // Initialization
        add_action('init', [$this, 'preInit'], 5);
        add_action('init', [$this, 'createType'], 10);
        add_action('init', [$this, 'createTaxons'], 10);
        add_action('init', [$this, 'postInit'], 15);

        // Set query parameters
        add_action('pre_get_posts', [$this, 'setQueryArgs']);

        // Register fields
        add_action('acf/init', [$this, 'createFields']);
    }

    /**
     * Additional instance methods
     *
     * Additional methods run on instantiation to allow customization before
     * WordPress is loaded.
     *
     * @return void
     */
    protected function init()
    {
        // extend me
    }

    /**
     * Additional methods run before registration
     *
     * Additional methods run on init before the post type is registered to
     * allow further customization.
     *
     * @return void
     */
    public function preInit()
    {
        // extend me
    }

    /**
     * Additional methods run after registration
     *
     * Additional methods run on init after the post type is registered to
     * allow further customization.
     *
     * @return void
     */
    public function postInit()
    {
        // extend me
    }

    /**
     * Create post type
     *
     * @return void
     */
    final public function createType()
    {
        register_post_type($this->type, $this->args);
    }

    /**
     * Create taxonomies
     *
     * @return void
     */
    final public function createTaxons()
    {
        if (!$this->taxons) {
            return;
        }

        foreach ($this->taxons as $taxon => $args) {
            register_taxonomy($taxon, $this->type, $args);
        }
    }

    /**
     * Set query parameters
     *
     * @param WP_Query $query
     * @return void
     */
    final public function setQueryArgs($query)
    {
        if (!$this->queryArgs || is_admin() || !$query->is_main_query() ||
            !is_post_type_archive($this->type)) {
            return;
        }

        foreach ($this->queryArgs as $key => $value) {
            $query->set($key, $value);
        }
    }

    /**
     * Register custom fields via ACF
     *
     * @return void
     */
    final public function createFields()
    {
        if (!$this->fields) {
            return;
        }

        foreach ($this->fields as $field) {
            acf_add_local_field_group($field);
        }
    }

    /**
     * Sanitize parameters
     *
     * @return void
     */
    final private function sanitize()
    {
        $this->args = $this->sanitizeLabels($this->args);

        foreach ($this->taxons as $key => $args) {
            $this->taxons[$key] = $this->sanitizeLabels($args);
        }

        foreach ($this->fields as $key => $args) {
            $this->fields[$key] = $this->sanitizeLocation($args);
        }
    }

    /**
     * Set type or taxonomy labels
     *
     * If the labels parameter is not set and the label_single parameter is set,
     * create a full set of labels for a post type or taxonomy and return the
     * modified array of parameters. If the label parameter is set, this will be
     * used as the plural form of the label, otherwise an "s" will be appended
     * to the value of label_single.
     *
     * @param array $args
     * @return array
     */
    final private function sanitizeLabels($args)
    {
        // Labels already set? No single label? Cannot create labels.
        if (isset($args['labels']) || !isset($args['label_single'])) {
            return $args;
        }

        $single = $args['label_single'];
        $plural = null;

        if (isset($args['label'])) {
            $plural = $args['label'];
        }

        $args['labels'] = \Cgit\Monolith\WordPress\labels($single, $plural);

        // Remove redundant parameter
        unset($args['label_single']);

        return $args;
    }

    /**
     * Set ACF group location
     *
     * If the field group location has not been set, assume that it should be
     * set to this post type.
     *
     * @param array $args
     * @return array
     */
    final private function sanitizeLocation($args)
    {
        if (isset($args['location'])) {
            return $args;
        }

        $args['location'] = [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => $this->type,
                ],
            ],
        ];

        return $args;
    }
}
