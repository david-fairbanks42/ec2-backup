<?php
/**
 * Date Helper
 *
 * @package Fairbanks\Kizarian\Utilities
 * @copyright (c) 2018, Fairbanks Publishing
 * @license Proprietary
 */

namespace App;

use Carbon\Carbon;

/**
 * Class Dates
 *
 * @author David Fairbanks <david@makerdave.com>
 * @package Fairbanks\Kizarian\Utilities
 * @version 2.0
 */
class Dates {

    /**
     * @param mixed $date
     * @param null|Carbon $default
     *
     * @return Carbon
     */
    public static function makeCarbon($date, Carbon $default=null)
    {
        if(is_object($date) && $date instanceof Carbon) {
            return $date;
        } elseif(is_object($date) && $date instanceof \DateTime) {
            return Carbon::instance($date);
        } elseif(is_object($date)) {
            return Carbon::parse($date->date, $date->timezone);
        } elseif(is_array($date)) {
            return Carbon::parse($date['date'], $date['timezone']);
        } elseif(is_numeric($date)) {
            return Carbon::createFromTimestamp($date);
        } elseif(is_string($date)) {
            return Carbon::parse($date);
        }

        if($default !== null)
            return $default;

        return Carbon::now();
    }
}
