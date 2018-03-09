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
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->sourceDom = new DOMDocument;
        $this->dom = new DOMDocument;
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
     * Add a long and (probably) unique suffix to each ID and class to prevent
     * duplicates appearing in the parent HTML document.
     *
     * @return void
     */
    private function sanitizeAttributes()
    {
        $suffix = '_' . bin2hex(openssl_random_pseudo_bytes(32));

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
        $names = ['class', 'id', 'xlink:href'];

        foreach ($names as $name) {
            $value = $element->getAttribute($name);

            if (!$value) {
                continue;
            }

            $element->setAttribute($name, $value . $suffix);
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

        $element->nodeValue = preg_replace('/([\.#][^\s]+?)(\s*?[\{\,])/',
            '\1' . $suffix . '\2', $element->nodeValue);
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
}
