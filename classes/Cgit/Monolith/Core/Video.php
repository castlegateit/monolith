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
        $data = json_decode(file_get_contents($data_url));

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
     * @return string
     */
    public function url()
    {
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
}
