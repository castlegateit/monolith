<?php

namespace Cgit\Monolith\Core;

/**
 * Video URL and embed code sanitizer
 *
 * This class takes an uncertain input, which can be any valid YouTube or Vimeo
 * URL or embed code, and provides predictable access to valid URLs, images,
 * links, and embed codes.
 */
class Video
{
    /**
     * Video ID
     *
     * @var integer
     */
    private $id = 0;

    /**
     * Video URL
     *
     * @var string
     */
    private $url;

    /**
     * Video embed code URL
     *
     * @var string
     */
    private $embed;

    /**
     * Image URL
     *
     * @var string
     */
    private $image;

    /**
     * Aspect ratio (default 16:9)
     *
     * @var float
     */
    private $ratio = 0.5625;

    /**
     * Cache directory
     *
     * @var string
     */
    private static $cache;

    /**
     * Constructor
     *
     * @param string $code
     * @return void
     */
    public function __construct($code)
    {
        $this->import($code);
    }

    /**
     * Import service and ID from URL or embed code
     *
     * @param string $code
     * @return void
     */
    private function import($code)
    {
        $url = preg_replace('/.*?<iframe .*?src=([\'"])(.*?)\1.*/is', '$2',
            $code);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        // Attempt to find the width and height of the video so that we can
        // calculate its aspect ratio.
        $width = (int) preg_replace('/.*?<iframe .*?width=([\'"])(.*?)\1.*/is', '$2', $code);
        $height = (int) preg_replace('/.*?<iframe .*?height=([\'"])(.*?)\1.*/is', '$2', $code);

        if ($width && $height) {
            $this->ratio = $height / $width;
        }

        if (stripos($url, 'vimeo.com') !== false) {
            return $this->importVimeoVideo($url);
        }

        if (stripos($url, 'youtube.com') !== false ||
            stripos($url, 'youtu.be') !== false) {
            return $this->importYouTubeVideo($url);
        }
    }

    /**
     * Import service and ID from a Vimeo URL
     *
     * @param string $url
     * @return void
     */
    private function importVimeoVideo($url)
    {
        $id = preg_replace('/.*\/(\w+)/', '$1', $url);
        $data_url = "http://vimeo.com/api/v2/video/$id.json";
        $data = json_decode(self::download($data_url));

        $this->id = $id;
        $this->url = "//player.vimeo.com/video/$id";
        $this->embed = $this->url;

        if ($data) {
            $this->image = $data[0]->thumbnail_large;
        }
    }

    /**
     * Import service and ID from a YouTube URL
     *
     * @param string $url
     * @return void
     */
    private function importYouTubeVideo($url)
    {
        $parts = parse_url($url);
        $path = trim($parts['path'], '/');
        $segments = explode('/', $path);

        if ($parts['host'] == 'youtu.be') {
            $this->id = $path;
        }

        elseif ($path == 'watch') {
            parse_str($parts['query'], $args);
            $this->id = $args['v'];
        }

        elseif (isset($segments[0]) && in_array($segments[0], ['embed', 'v'])) {
            $this->id = $segments[1];
        }

        if (!$this->id) {
            return;
        }

        $this->url = '//www.youtube.com/watch?v=' . $this->id;
        $this->embed = '//www.youtube.com/embed/' . $this->id;
        $this->image = '//i.ytimg.com/vi/' . $this->id . '/hqdefault.jpg';
    }

    /**
     * Return video URL
     *
     * Return the URL of the video web page or the URL used to embed the video
     * as an iframe.
     *
     * @param boolean $embed
     * @return string
     */
    public function url($embed = false)
    {
        if ($embed) {
            return $this->embed;
        }

        return $this->url;
    }

    /**
     * Return video image URL
     *
     * @return string
     */
    public function image()
    {
        return $this->image;
    }

    /**
     * Return video embed code
     *
     * @return string
     */
    public function embed()
    {
        return '<iframe src="' . $this->embed
            . '" frameborder="0" allowfullscreen></iframe>';
    }

    /**
     * Return video link
     *
     * @return string
     */
    public function link($title = '', $alt = '')
    {
        return '<a href="' . $this->url . '" title="' . $title . '">'
            . '<img src="' . $this->image . '" alt="' . $alt . '"></a>';
    }

    /**
     * Return responsive video embed code
     *
     * @return string
     */
    public function responsiveEmbed()
    {
        $padding = rtrim(number_format($this->ratio * 100, 4), '0');

        return '<div class="monolith-responsive-video" style="height: 0; padding-bottom: ' . $padding . '%; position: relative">
            <iframe src="' . $this->embed . '" style="height: 100%; left: 0; position: absolute; top: 0; width: 100%" frameborder="0" allowfullscreen></iframe>
        </div>';
    }

    /**
     * Set cache directory
     *
     * @param string $directory
     * @return void
     */
    public static function cache($directory)
    {
        self::$cache = rtrim($directory, '/');
    }

    /**
     * Download and maybe cache file
     *
     * @param string $url
     * @param integer $limit
     * @return string
     */
    private static function download($url, $limit = 3600)
    {
        if (is_null(self::$cache) || !is_dir(self::$cache)) {
            return file_get_contents($url);
        }

        $file = self::$cache . '/' . preg_replace('/[^0-9a-z]+/i', '_', $url);
        $expires = time() - $limit;

        if (file_exists($file) && filemtime($file) > $expires) {
            return file_get_contents($file);
        }

        $content = file_get_contents($url);
        file_put_contents($file, $content);

        return $content;
    }
}
