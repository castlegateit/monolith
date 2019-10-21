# Monolith

Monolith is a collection of utility functions and classes that make PHP and WordPress development a little bit easier.

## Core

The Core module can be used with any PHP project and uses the `\Cgit\Monolith\Core` namespace.

### Functions

*   `contains($haystack, $needle)` Does `$haystack` contain `$needle`? Works with strings and arrays.

*   `startsWith($haystack, $needle)` Does `$haystack` start with `$needle`? Works with strings and arrays.

*   `endsWith($haystack, $needle)` Does `$haystack` end with `$needle`? Works with strings and arrays.

*   `fileSize($file, $decimals = 2)` Return a human-readable file size with units and to a particular number of decimal places.

*   `dataUrl($file, $type = null)` Return a base64-encoded data URL from a file path.

*   `formatUrl($url, $human = false)` Provided with something that looks like a URL, return a predictable URL with or without its scheme.

*   `formatLink($url, $content = null, $attributes = [])` Provided with something that looks like a URL, return a valid HTML link with optional content.

*   `formatTel($tel, $human = false, $code = null)` Return a formatted telephone number.

*   `formatTelLink($tel, $content = null, $attributes = [], $code = null)` Return a telephone number link.

*   `ordinal($number)` Return a number with its appropriate ordinal suffix, e.g. "1st", "2nd", or "3rd".

*   `truncate($text, $max, $ellipsis = ' &hellip;')` Truncates text to within a particular number of characters, avoiding breaking words.

*   `truncateWords($text, $max, $ellipsis = ' &hellip;')` Truncates text to within a particular number of words, avoiding breaking words.

*   `formatAttributes($attributes)` Converts an associative array into a string containing HTML attributes. Nested arrays are converted into space-separated lists.

*   `embedSvg($file, $title = false, $nofill = false)` Return the contents of an SVG file stripped of anything that might cause problems when it is embedded in an HTML file. This function uses the `ScalableVectorGraphic` class described below.

*   `twitterName($url)` Extract and return a Twitter handle from a valid Twitter URL.

*   `splitLines($text)` Split comma- and newline-delimited text (e.g. an address) into array items.

*   `rejoinLines($lines, $sep = ', ')` Rejoin lines, either as array or string parsed by `splitLines`, with new delimiter.

### Classes

#### ScalableVectorGraphic

The `ScalableVectorGraphic` class sanitizes SVG code for embedding directly in HTML documents. By default, it removes the XML declaration and attempts to add a `viewBox` attribute if one is not already present.

~~~ php
$svg = new \Cgit\Monolith\Core\ScalableVectorGraphic;
$svg->parse($code); // import SVG code from string
$svg->load($file); // import SVG code from file

echo $svg->embed(); // return sanitized SVG code
~~~

You can also use it to remove attributes from the root element and to remove styles from the entire SVG. This may be useful for SVG icons where the fill colour should be set by the document CSS and not the CSS embedded in the SVG code.

~~~ php
$svg->removeAttributes('viewBox');
$svg->removeAttributes(['width', 'height']);
$svg->removeStyles('fill');
$svg->removeStyles(['fill', 'stroke']);
~~~

You can reset the SVG to its original condition using the `reset()` method. You can also return the original source code and the non-sanitized, parsed SVG code using the `embedSourceCode()` and `embedSourceDom()` methods respectively.

You can use the `fill($color)` method to set a fill attribute on the root SVG element. You can also use the `title($title)` method to set a title element for better accessibility.

#### TimeSpanner

The `TimeSpanner` class provides a convenient way of calculating and displaying consistently formatted ranges of dates or times. Its constructor sets the start and end dates, performing some sanitization of the input (integers are assumed to be Unix time; everything else gets fed through `strtotime()`).

~~~ php
$foo = new \Cgit\Monolith\Core\TimeSpanner($start, $end);

$foo->getStartTime($format); // e.g. "1 January 2010"
$foo->getEndTime($format);
$foo->getRange($formats, $tolerance); // e.g. "1-10 January 2010"
$foo->getInterval($formats, $tolerance); // e.g. "4 seconds" or "10 years"
~~~

You can set the tolerance in seconds for displaying ranges of times. If the difference between the start and end times is within the tolerance value, they are considered to be the same time.

~~~ php
$foo->setDefaultRangeTolerance($seconds);
~~~

Time formats can be specified when returning a value or as default values for this instance. Formats are specified in the standard PHP date format.

~~~ php
$foo->setDefaultTimeFormat('j F Y');
$foo->setDefaultRangeFormats([
    'time' => ['H:i', '&ndash;', 'H:i d F Y'],
    'day' => ['d', '&ndash;', 'd F Y'],
    'month' => ['d F', '&ndash;', 'd F Y'],
    'year' => ['d F Y', '&ndash;', 'd F Y'],
]);
~~~

#### Video

The `Video` class takes any approximately valid YouTube or Vimeo URL or embed code and provides access to URLs, embed codes, images, and links.

~~~ php
$foo = new \Cgit\Monolith\Core\Video($code);

$foo->url(); // video URL
$foo->image(); // video placeholder image
$foo->embed(); // video iframe embed code
$foo->link(); // HTML image with link to video
$foo->responsiveEmbed(); // iframe embed with responsive wrapper
~~~

Note that `responsiveEmbed` needs an iframe with width and height attributes to calculate the aspect ratio of the video. Otherwise, videos are assumed to be in 16:9 format.

## WordPress

The WordPress module uses the `\Cgit\Monolith\WordPress` namespace.

### Functions

*   `enqueue($file, $deps = [], $script = null, $parent = null)` Enqueues a CSS or JavaScript file with cache-busting, dependencies (based on handles), and automatic style or script detection. This function returns the automatically generated resource handle so you can use it in other dependency lists. Files can be specified by handle, relative path, absolute path, or URL.

*   `labels($single, $plural = null)` Generates a complete set of labels for a [custom post type](https://developer.wordpress.org/reference/functions/register_post_type/#parameters) or [custom taxonomy](https://developer.wordpress.org/reference/functions/register_taxonomy/#parameters).

*   `pagination($args = [])` Wrapper for `paginate_links()` with sensible default values.

*   `embedSvg($file, $title, $nofill)` Similar to the function of the same name in `Core` described above, but relative file paths are assumed to be in the active theme directory.

*   `send404()` Send an immediate 404 response and display the 404 template, overriding all other responses and output.

*   `pageFromTemplate($template, $multiple = false)` Identify and return the page(s) that use a particular template.

### Classes

#### Post

Based on the original Terminus Post class, this provides convenient access to the final, filtered content of posts:

~~~ php
$foo = new \Cgit\Monolith\WordPress\Post(16);

echo $foo->id();
echo $foo->title();
echo $foo->url();
echo $foo->content();
echo $foo->excerpt();
~~~

It also provides the `field($field)` method, which retrieves an [Advanced Custom Fields](https://www.advancedcustomfields.com/) value from the post, and the `image()` method, which returns an `Image` instance for the post's featured image.

#### Image

Based on the original Terminus Image class, this provides a consistent interface for getting URLs. Its constructor accepts an image attachment object, an image ID, a post object, a post ID, or an ACF custom field name.

~~~ php
use \Cgit\Monolith\WordPress\Image;

$foo = new Image($image_id); // image attachment
$foo = new Image($post_id); // featured image for post
$foo = new Image($field); // ACF image for current post
$foo = new Image($field, $post_id); // ACF image by post object or ID
~~~

You can then obtain various information about the image or generate an HTML `<img>` element:

~~~ php
$foo->url(); // URL of original image
$foo->url('medium'); // URL of image at a particular size
$foo->meta(); // get all meta information as an array
$foo->meta('alt'); // get particular meta field
$foo->element(); // get image element with fill size image
$foo->element('medium'); // get image element at a particular size
$foo->data('medium'); // get data URI
~~~

The `element()` method can also take an associative array of attribute keys and values to be added to the HTML element. If the `element()` method is provided with an associative array of sizes, it generates a responsive `<picture>` element:

~~~ php
$foo->element([
    'medium' => '(max-width: 480px)',
    'large' => '(max-width: 960px)',
]);
~~~

#### PostType

The `PostType` class is an abstract class that can be extended to define custom post types with taxonomies, custom fields, and custom query parameters quickly and easily:

~~~ php
class Book extends PostType
{
    $this->type = 'book';

    $this->args = [
        'label' => 'Books',
        'label_single' => 'Book',
    ];

    $this->taxons = [
        'book-category' => [],
        'book-tag' => [],
    ];

    $this->queryArgs = [
        'posts_per_page' => 20,
    ];

    $this->fields = [
        [
            // ACF field group parameters
        ],
    ];
}
~~~

##### Properties

The `args` property is an array of `register_post_type` parameters, plus an optional `label_single` parameter that is used to generate a full set of labels automatically.

The `taxons` property is a nested array of taxonomy parameters. The keys are the taxonomy names and the values are the arrays of parameters passed to the `register_taxonomy` function.

The `queryArgs` property is an array of `WP_Query` parameters set via the `pre_get_posts` action.

The `fields` property is a nested array of ACF field groups, allowing you to add multiple to the post type. The `location` parameter can be omitted and will be set the current post type automatically.

##### Methods

You do not need to use any methods to define a post type. However, the class does call some methods can be extended in your child class, perhaps to set the post type parameters:

*   `init` is run immediately on instantiation.
*   `preInit` is run on the `init` action before any of the plugin methods have run.
*   `postInit` is run on the `init` action after all the plugin methods have run.

#### Resource

The `Resource` class does the heavy lifting for the `enqueue` function and shouldn't need to be used directly.

~~~ php
$foo = new \Cgit\Monolith\WordPress\Resource($source, $deps);

$foo->enqueue(); // enqueue resource
$foo->setScript(true); // resource should be enqueued as a script
$foo->setParent(true); // resource path is relative to parent theme
$foo->getHandle(); // return generated resource handle
~~~

## License

Copyright (c) 2019 Castlegate IT. All rights reserved.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
