<?php

namespace Cgit\Monolith\Core;

/**
 * Date and time span formatter
 *
 * Provides an easy and consistent way of calculating and returning ranges of
 * dates and times.
 */
class TimeSpanner
{
    /**
     * Start date in Unix time format
     *
     * @var int
     */
    private $start;

    /**
     * End date in Unix time format
     *
     * @var int
     */
    private $end;

    /**
     * Default time format
     *
     * @var string
     */
    private $defaultTimeFormat = 'H:i j F Y';

    /**
     * Default range formats
     *
     * @var array
     */
    private $defaultRangeFormats = [
        'time' => ['H:i', '&ndash;', 'H:i d F Y'],
        'day' => ['d', '&ndash;', 'd F Y'],
        'month' => ['d F', '&ndash;', 'd F Y'],
        'year' => ['d F Y', '&ndash;', 'd F Y'],
    ];

    /**
     * Default range tolerance
     *
     * If the start and end times overlap by this number of seconds, they are
     * considered to be the same time.
     *
     * @var int
     */
    private $defaultRangeTolerance = 0;

    /**
     * Constructor
     *
     * @param mixed $start
     * @param mixed $end
     */
    public function __construct($start = null, $end = null)
    {
        $this->setStartTime($start);
        $this->setEndTime($end);
    }

    /**
     * Set start time
     *
     * Anything that looks like a number is treated as Unix time; strings are
     * fed through strtotime. If the start time is not specified, the current
     * time is used instead.
     *
     * @param mixed $time
     * @return self
     */
    public function setStartTime($time = null)
    {
        if (is_null($time)) {
            $time = time();
        }

        $this->start = self::sanitizeTimeInput($time);

        return $this;
    }

    /**
     * Set end time
     *
     * Anything that looks like a number is treated as Unix time; strings are
     * fed through strtotime. If the end time is not specified, it is assumed to
     * be the same as the start time.
     *
     * @param mixed $time
     * @return self
     */
    public function setEndTime($time = null)
    {
        if (is_null($time)) {
            $time = $this->start;
        }

        $time = self::sanitizeTimeInput($time);

        if ($time < $this->start) {
            return trigger_error('End time cannot be before the start time');
        }

        $this->end = $time;

        return $this;
    }

    /**
     * Return start time in specific format
     *
     * @param string $format
     * @return string
     */
    public function getStartTime($format = null)
    {
        if (is_null($format)) {
            $format = $this->defaultTimeFormat;
        }

        return date($format, $this->start);
    }

    /**
     * Return end time in specific format
     *
     * @param string $format
     * @return string
     */
    public function getEndTime($format = null)
    {
        if (is_null($format)) {
            $format = $this->defaultTimeFormat;
        }

        return date($format, $this->end);
    }

    /**
     * Return range of times in specific format
     *
     * If no formats are specified, the default range formats will be used. If
     * the start and end times are the same (give or take the range tolerance),
     * the method outputs getStartTime() with its default format instead.
     *
     * @param array $formats
     * @param int $tolerance
     * @return string
     */
    public function getRange($formats = null, $tolerance = null)
    {
        if (!is_int($tolerance)) {
            $tolerance = $this->defaultRangeTolerance;
        }

        if (!$this->isRange($tolerance)) {
            return $this->getStartTime();
        }

        $formats = $this->sanitizeRangeFormats($formats);
        $format = 'time';
        $tests = ['year' => 'Y', 'month' => 'Ym', 'day' => 'Ymd'];

        foreach ($tests as $key => $str) {
            if ($this->getStartTime($str) != $this->getEndTime($str)) {
                $format = $key;
                break;
            }
        }

        return date($formats[$format][0], $this->start) . $formats[$format][1]
            . date($formats[$format][2], $this->end);
    }

    /**
     * Return time interval as number of seconds or string
     *
     * @param bool $seconds
     * @return mixed
     */
    public function getInterval($seconds = false)
    {
        $difference = $this->end - $this->start;

        if ($seconds) {
            return $difference;
        }

        $periods = [
            'year'   => 60 * 60 * 24 * 365,
            'month'  => 60 * 60 * 24 * 30,
            'week'   => 60 * 60 * 24 * 7,
            'day'    => 60 * 60 * 24,
            'hour'   => 60 * 60,
            'minute' => 60,
            'second' => 1,
        ];

        foreach ($periods as $period => $seconds) {
            if ($seconds <= $difference) {
                $n = floor($difference / $seconds);
                $s = $n > 1 ? 's' : '';

                return "$n $period$s";
            }
        }

        return 0;
    }

    /**
     * Set default time format
     *
     * In addition to the default PHP date formats, this accepts the string
     * 'MySQL' as an alias for a MySQL compatible date and time format.
     *
     * @param string $format
     * @return void
     */
    public function setDefaultTimeFormat($format)
    {
        if (strtolower($format) == 'mysql') {
            $format = 'Y-m-d H:i:s';
        }

        $this->defaultTimeFormat = $format;
    }

    /**
     * Set default range formats
     *
     * @param array $formats
     * @return void
     */
    public function setDefaultRangeFormats($formats)
    {
        $this->defaultRangeFormats = $this->sanitizeRangeFormats($formats);
    }

    /**
     * Set the default range tolerance
     *
     * If the start and end times overlap by this number of seconds, they are
     * considered to be the same time.
     *
     * @param int $seconds
     * @return void
     */
    public function setDefaultRangeTolerance($seconds)
    {
        if (!is_int($seconds)) {
            return trigger_error('Range tolerance must be an integer');
        }

        $this->defaultRangeTolerance = $seconds;
    }

    /**
     * Is this a range of times?
     *
     * If the start time and end time (give or take the range tolerance) are
     * different, this instance represents a range of times.
     *
     * @return bool
     */
    public function isRange($tolerance = null)
    {
        if (!is_int($tolerance)) {
            $tolerance = $this->defaultRangeTolerance;
        }

        $max = $this->end + $tolerance;
        $min = $this->end - $tolerance;

        if ($this->start > $min && $this->start < $max) {
            return false;
        }

        return true;
    }

    /**
     * Return complete set of valid range formats
     *
     * Given an array of formats, this checks that each format key and array is
     * valid and returns an array of formats with the gaps filled by the default
     * values.
     *
     * @param array $formats
     * @return array
     */
    private function sanitizeRangeFormats($formats)
    {
        $defaults = $this->defaultRangeFormats;

        if (!is_array($formats)) {
            return $defaults;
        }

        foreach ($formats as $key => $format) {
            if (!array_key_exists($key, $defaults)) {
                continue;
            }

            if (!is_array($format) || count($format) != 3) {
                return trigger_error('Each range format must be an array '
                    . 'containing three strings');
            }

            $defaults[$key] = $format;
        }

        return $defaults;
    }

    /**
     * Attempt to convert integer or string input into Unix time
     *
     * @param mixed $time
     * @return integer
     */
    private static function sanitizeTimeInput($time)
    {
        if (is_int($time) || ctype_digit($time)) {
            return intval($time);
        }

        $time = strtotime($time);

        if (is_int($time)) {
            return $time;
        }

        return trigger_error('Invalid time format: ' . $time);
    }
}
