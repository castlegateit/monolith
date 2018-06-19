<?php

namespace Cgit\Monolith\Core;

use DOMDocument;

/**
 * SVG image sanitizer
 *
 * This class parses SVG code and can be used to generate code that is (mostly)
 * safe to embed in an HTML document. Specifically, it removes invalid XML
 * declarations, makes ID and class attributes unique, and attempts to add
 * missing viewBox attributes based on the image width and height.
 */
class ScalableVectorGraphic
{
    /**
     * Source code
     *
     * @var string
     */
    private $source;

    /**
     * Initial DOM document based on source code
     *
     * @var DOMDocument
     */
    private $sourceDom;

    /**
     * Modified DOM document to embed in HTML
     *
     * @var DOMDocument
     */
    private $dom;

    /**
     * All elements in modified DOM
     *
     * @var array
     */
    private $elements;

    /**
     * Root SVG element in modified DOM
     *
     * @var DOMElement
     */
    private $root;

    /**
     * Number of SVG instances
     *
     * This counter is incremented with each instance of this class, providing a
     * unique suffix for class and ID attributes.
     *
     * @var integer
     */
    private static $instances = 0;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->sourceDom = new DOMDocument;
        $this->dom = new DOMDocument;

        self::$instances = self::$instances + 1;
    }

    /**
     * Load SVG code from string
     *
     * Parse the SVG code provided to generate a DOM document object. Sanitize
     * the DOM document to make it safe to embed in HTML.
     *
     * @param string $svg
     * @return self
     */
    public function parse($svg)
    {
        $this->source = $svg;
        $this->sourceDom->loadXML($svg);

        // Remove DOCTYPE, which might have been added by loadXML
        if (!is_null($this->sourceDom->doctype)) {
            $this->sourceDom->removeChild($this->sourceDom->doctype);
        }

        return $this->reset();
    }

    /**
     * Load SVG code from file
     *
     * @param string $file
     * @return self
     */
    public function load($file)
    {
        if (!file_exists($file)) {
            return trigger_error($file . ' not found');
        }

        return $this->parse(file_get_contents($file));
    }

    /**
     * Reset DOM document to original state and sanitize
     *
     * @return self
     */
    public function reset()
    {
        $this->dom = clone $this->sourceDom;
        $this->elements = $this->dom->getElementsByTagName('*');
        $this->root = $this->dom->getElementsByTagName('svg')->item(0);

        return $this->sanitize();
    }

    /**
     * Sanitize DOM document
     *
     * Remove features that may cause problems or invalid code when embedded in
     * HTML, such as the XML declaration. Make ID and class attributes unique to
     * avoid conflicts with other elements on the page. Attempt to add a viewBox
     * if it is missing.
     *
     * @return self
     */
    private function sanitize()
    {
        $this->sanitizeViewBox();
        $this->sanitizeAttributes();

        return $this;
    }

    /**
     * Add viewBox attribute (if necessary and possible)
     *
     * @return void
     */
    private function sanitizeViewBox()
    {
        // Already got a viewBox?
        if ($this->root->getAttribute('viewBox')) {
            return;
        }

        $width = $this->root->getAttribute('width');
        $height = $this->root->getAttribute('height');

        // No width? No height? Cannot create viewBox.
        if (!$width || !$height) {
            return;
        }

        // Set the viewBox based on the width and height attributes
        $this->root->setAttribute('viewBox', "0 0 $width $height");
    }

    /**
     * Make ID and class attributes unique
     *
     * Add a unique suffix to each ID and class to prevent duplicates appearing
     * in the parent HTML document.
     *
     * @return void
     */
    private function sanitizeAttributes()
    {
        $suffix = '_' . md5($this->source . self::$instances);

        foreach ($this->elements as $element) {
            $this->modifyAttributes($element, $suffix);
            $this->modifyHashes($element, $suffix);
            $this->modifyStyles($element, $suffix);
        }
    }

    /**
     * Modify DOM element attribute values to include suffix
     *
     * @param DOMElement $element
     * @param string $suffix
     * @return void
     */
    private function modifyAttributes($element, $suffix)
    {
        $id = $element->getAttribute('id');
        $class = $element->getAttribute('class');
        $href = $element->getAttribute('xlink:href');

        if ($id) {
            $element->setAttribute('id', $id . $suffix);
        }

        if ($class) {
            $names = explode(' ', $class);
            $sanitized_names = [];

            foreach ($names as $name) {
                $sanitized_names[] = $name . $suffix;
            }

            $element->setAttribute('class', implode(' ', $sanitized_names));
        }

        if ($href && strpos($href, '#') !== false) {
            $element->setAttribute('xlink:href', $href . $suffix);
        }
    }

    /**
     * Modify fragment identifiers to include suffix
     *
     * @param DOMElement $element
     * @param string $suffix
     * @return void
     */
    private function modifyHashes($element, $suffix)
    {
        $pattern = '/(url\((["\']?)#.*?)(\2\))/i';
        $attributes = $element->attributes;

        foreach ($attributes as $attribute) {
            $name = $attribute->nodeName;
            $value = $attribute->nodeValue;

            // Ignore ID attributes and values without URLs
            if ($name == 'id' || strpos($value, 'url(') === false) {
                continue;
            }

            $value = preg_replace($pattern, '\1' . $suffix . '\3', $value);
            $attribute->nodeValue = $value;
        }
    }

    /**
     * Modify embedded CSS selectors to include suffix
     *
     * @param DOMElement $element
     * @param string $suffix
     * @return void
     */
    private function modifyStyles($element, $suffix)
    {
        if ($element->nodeName != 'style') {
            return;
        }

        $value = $element->nodeValue;
        $replace = '\1' . $suffix . '\2';

        $patterns = [
            '/([\.#][^\s]+?)(\s*?[\{\,])/', // id (#) and class (.) selectors
            '/(\[[^\s]+?)([\]=~|^$*])/', // square bracket selectors
        ];

        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, $replace, $value);
        }

        $element->nodeValue = $value;
    }

    /**
     * Remove root element attributes
     *
     * @param mixed $attributes
     * @return self
     */
    public function removeAttributes($attributes)
    {
        if (!is_array($attributes)) {
            return $this->removeAttributes([$attributes]);
        }

        foreach ($attributes as $attribute) {
            $this->root->removeAttribute($attribute);
        }

        return $this;
    }

    /**
     * Remove styles
     *
     * It may be useful to remove styles, e.g. fill, from the SVG element and
     * instead set them with the CSS in the parent HTML document. Use at your
     * own risk.
     *
     * @param mixed $styles
     * @return self
     */
    public function removeStyles($styles)
    {
        if (!is_array($styles)) {
            return $this->removeStyles([$styles]);
        }

        foreach ($styles as $style) {
            $pattern = '/\b' . $style . '\s*:[^;}]*;?/i';
            $replace = '';

            foreach ($this->elements as $element) {
                // Remove attribute with matching name
                $element->removeAttribute($style);

                // Remove matching style rules from style attributes
                foreach ($element->attributes as $attribute) {
                    if ($attribute->nodeName != 'style') {
                        continue;
                    }

                    $attribute->nodeValue = trim(preg_replace($pattern,
                        $replace, $attribute->nodeValue));
                }

                // Remove matching style rules from style elements
                if ($element->nodeName == 'style') {
                    $element->nodeValue = preg_replace($pattern, $replace,
                        $element->nodeValue);
                }
            }
        }

        return $this;
    }

    /**
     * Set the SVG title
     *
     * @param string $text
     * @return self
     */
    public function title($text)
    {
        $root = $this->dom->documentElement;
        $nodes = $root->childNodes;
        $title = null;

        // Find an existing top-level title element if one exists
        foreach ($nodes as $node) {
            if (!property_exists($node, 'tagName') ||
                $node->tagName != 'title') {
                continue;
            }

            $title = $node;
            break;
        }

        // Add a top-level title element if it does not already exist
        if (is_null($title)) {
            $title = $this->dom->createElement('title');
            $root->insertBefore($title, $root->firstChild);
        }

        $title->nodeValue = $text;

        return $this;
    }

    /**
     * Set the primary fill colour
     *
     * @param string $fill
     * @return self
     */
    public function fill($fill)
    {
        $this->dom->documentElement->setAttribute('fill', $fill);

        return $this;
    }

    /**
     * Return unmodified source code
     *
     * @return string
     */
    public function embedSourceCode()
    {
        return $this->source;
    }

    /**
     * Return unmodified DOM document
     *
     * This may differ slightly from the unmodified source code because the
     * parsing process will ensure a valid DOM document structure.
     *
     * @return string
     */
    public function embedSourceDom()
    {
        return $this->sourceDom->saveHTML();
    }

    /**
     * Return sanitized DOM document
     *
     * @return string
     */
    public function embed()
    {
        return $this->dom->saveHTML();
    }

    /**
     * Send complete SVG with header
     *
     * This will attempt to send a complete, self-contained SVG file with the
     * correct HTTP headers. Therefore, no content should be sent before this
     * method is called.
     *
     * @return void
     */
    public function send()
    {
        header('Content-Type: image/svg+xml');
        echo $this->embed();
        exit;
    }
}
