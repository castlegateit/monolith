<?php

namespace Cgit\Monolith\Core;

/**
 * Does X contain Y?
 *
 * Does an array contain a particular item? Does a string contain a particular
 * string? If the variable to search is not an array or a string, an error will
 * be triggered.
 *
 * @param mixed $haystack
 * @param mixed $needle
 * @return boolean
 */
function contains($haystack, $needle)
{
    if (is_array($haystack)) {
        return in_array($needle, $haystack);
    }

    if (is_string($haystack)) {
        return strpos($haystack, $needle) !== false;
    }

    trigger_error(gettype($haystack) . ' cannot contain ' . gettype($needle));
}

/**
 * Does X start with Y?
 *
 * Does an array start with a particular item? Does a string start with a
 * particular string?
 *
 * @param mixed $haystack
 * @param mixed $needle
 * @return boolean
 */
function startsWith($haystack, $needle)
{
    if (is_array($haystack)) {
        return reset($haystack) == $needle;
    }

    if (is_string($haystack)) {
        return strpos($haystack, $needle) === 0;
    }

    trigger_error(gettype($haystack) . ' cannot contain ' . gettype($needle));
}

/**
 * Does X end with Y?
 *
 * Does an array end with a particular item? Does a string end with a particular
 * string?
 *
 * @param mixed $haystack
 * @param mixed $needle
 * @return boolean
 */
function endsWith($haystack, $needle)
{
    if (is_array($haystack)) {
        return end($haystack) == $needle;
    }

    if (is_string($haystack)) {
        return substr($haystack, -strlen($needle)) == $needle;
    }

    trigger_error(gettype($haystack) . ' cannot contain ' . gettype($needle));
}

/**
 * Humane file size
 *
 * Return a friendly, human-readable file size to a particular number of decimal
 * places and with an appropriate unit suffix.
 *
 * @param string $file
 * @param integer $decimals
 * @return string
 */
function fileSize($file, $decimals = 2)
{
    if (!file_exists($file)) {
        trigger_error('File not found ' . $file);

        return;
    }

    $bytes = filesize($file);
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    $size = $bytes / pow(1024, $factor);

    return number_format($size, $decimals) . '&nbsp;' . $units[$factor];
}

/**
 * Data URL
 *
 * Provided with the file system path to a file, return a base64 data URI
 * suitable for embedding in HTML. If the type is not specified, attempt to
 * determine the type automatically.
 *
 * @param string $file
 * @param string $type
 * @return string
 */
function dataUrl($file, $type = null)
{
    if (!file_exists($file)) {
        trigger_error('File not found ' . $file);

        return;
    }

    $contents = file_get_contents($file);

    if (is_null($type)) {
        $type = mime_content_type($file);
    }

    return 'data:' . $type . ';base64,' . base64_encode($contents);
}

/**
 * Format URL
 *
 * Converts a string to a consistently formatted URL, with or without the scheme
 * and URL protocol separator.
 *
 * @param string $url
 * @param boolean $human
 * @return void
 */
function formatUrl($url, $human = false)
{
    // No separator? Assume it needs one.
    if (strpos($url, '//') === false) {
        $url = '//' . $url;
    }

    // Valid URL?
    if (parse_url($url) === false) {
        return;
    }

    // Return full URL
    if (!$human) {
        return $url;
    }

    // Return friendly, human-readable URL
    $url = preg_replace('~^[^/]*//~', '', $url);

    if (substr_count($url, '/') == 1 && endsWith($url, '/')) {
        $url = substr($url, 0, -1);
    }

    return $url;
}

/**
 * Format link
 *
 * Provided with a string that looks like a URL, return an HTML link. If no
 * content is specified, the human-readable version of the URL will be used
 * instead.
 *
 * @param string $url
 * @param string $content
 * @param array $attributes
 * @return string
 */
function formatLink($url, $content = null, $attributes = [])
{
    $url = formatUrl($url);

    if (is_null($content)) {
        $content = formatUrl($url, true);
    }

    $attributes['href'] = $url;

    return '<a ' . formatAttributes($attributes) . '>' . $content . '</a>';
}

/**
 * Format telephone number
 *
 * Converts a telephone number to a machine- or human-readable format. Allows
 * you to set the country code on machine-readable telephone numbers.
 *
 * @param string $tel
 * @param boolean $human
 * @param string $code
 * @return string
 */
function formatTel($tel, $human = false, $code = null)
{
    if ($human) {
        return str_replace(' ', '&nbsp;', $tel);
    }

    $tel = preg_replace('/\D/', '', html_entity_decode($tel));

    if (substr($tel, 0, 1) === '0') {
        if (is_null($code)) {
            $code = '+44';
        }

        $tel = $code . substr($tel, 1);
    }

    return $tel;
}

/**
 * Format telephone link
 *
 * Creates a valid HTML telephone link with optional content, attributes, and
 * country code. The default content is the human-readable version of the
 * telephone number.
 *
 * @param string $tel
 * @param string $content
 * @param array $attributes
 * @param string $code
 * @return string
 */
function formatTelLink($tel, $content = null, $attributes = [], $code = null)
{
    if (is_null($content)) {
        $content = formatTel($tel, true);
    }

    $attributes['href'] = 'tel:' . formatTel($tel, false, $code);

    return '<a ' . formatAttributes($attributes) . '>' . $content . '</a>';
}

/**
 * Ordinals
 *
 * Return a number with its appropriate English language ordinal suffix, e.g.
 * "1st", "2nd", "3rd", etc.
 *
 * @param integer $number
 * @return string
 */
function ordinal($number)
{
    if (!in_array($number % 100, [11, 12, 13])) {
        switch ($number % 10) {
            case 1:
                return $number . 'st';
            case 2:
                return $number . 'nd';
            case 3:
                return $number . 'rd';
        }
    }

    return $number . 'th';
}

/**
 * Safely truncate string
 *
 * Remove HTML tags and truncate a string to within a particular number of
 * characters without splitting words.
 *
 * @param string $text
 * @param integer $max
 * @param string $ellipsis
 * @return string
 */
function truncate($text, $max, $ellipsis = ' &hellip;')
{
    $text = strip_tags($text);

    if (strlen($text) <= $max) {
        return $text;
    }

    $truncated = substr($text, 0, $max);
    $next = substr($text, $max, 1);

    if ($next != ' ' && strpos($truncated, ' ') !== false) {
        $truncated = substr($truncated, 0, strrpos($truncated, ' '));
    }

    return $truncated . $ellipsis;
}

/**
 * Safely truncate string to number of words
 *
 * Remove HTML tags and truncate a string to within a particular number of words
 * without splitting words.
 *
 * @param string $text
 * @param integer $max
 * @param string $ellipsis
 * @return string
 */
function truncateWords($text, $max, $ellipsis = ' &hellip;')
{
    $text = strip_tags($text);
    $words = str_word_count($text, 2);

    if (count($words) <= $max) {
        return $text;
    }

    return substr($text, 0, array_keys($words)[$max]) . $ellipsis;
}

/**
 * Format array as HTML attributes
 *
 * Provided with an associative array, returns a string containing valid HTML
 * attributes constructed from the key-value pairs. Values that are arrays will
 * be converted to space-separated lists.
 *
 * @param array $attributes
 * @return string
 */
function formatAttributes($attributes)
{
    $parts = [];

    if (!is_array($attributes)) {
        return;
    }

    foreach ($attributes as $key => $value) {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        $parts[] = $key . '="' . $value . '"';
    }

    return implode(' ', $parts);
}

/**
 * Return SVG file content as safe HTML element
 *
 * Provided with the path to an SVG, this loads its content and attempts to
 * remove any attributes or features that would cause naming collisions or
 * invalid HTML. The result is returned as an SVG element that can be embedded
 * in an HTML document.
 *
 * @param string $file
 * @param string $title
 * @param boolean $nofill
 * @return string
 */
function embedSvg($file, $title = false, $nofill = false)
{
    $svg = new ScalableVectorGraphic;
    $svg->load($file);

    if ($title) {
        $svg->title($title);
    }

    if ($nofill) {
        $svg->removeStyles('fill');
    }

    return $svg->embed();
}

/**
 * Extract handle from Twitter URL
 *
 * Provided with some form of valid Twitter account URL, return the
 * corresponding user name.
 *
 * @param string $url
 * @return string
 */
function twitterName($url)
{
    return preg_replace('~^(?:https?:)?//(?:www.)?twitter.com/'
        . '(?:#!/)?(.+?)(?:/)?$~i', '$1', $url);
}

/**
 * Split lines
 *
 * Convert a string into an array using commas and line breaks as delimiters and
 * removing leading and trailing space. Useful for parsing address data.
 *
 * @param string $text
 * @return array
 */
function splitLines($text)
{
    return preg_split('/ *[,\n\r]+ */', trim($text));
}

/**
 * Rejoin lines with new delimiter
 *
 * @param array|string $lines
 * @param string $sep
 * @return string
 */
function rejoinLines($lines, $sep = ', ')
{
    if (!is_array($lines)) {
        $lines = splitLines($lines);
    }

    return implode($sep, $lines);
}
