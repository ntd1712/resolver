<?php

namespace Chaos;

use Carbon\Carbon;
use Closure;
use voku\helper\AntiXSS;

/**
 * Sanitizes data so that Cross Site Scripting hacks can be prevented.
 *
 * @param   array|mixed $str Input data e.g. string or array of strings.
 *
 * @return  mixed
 */
function antiXss($str)
{
    static $xss = null;

    if (null === $xss) {
        $xss = new AntiXSS();
    }

    return $xss->xss_clean($str);
}

/**
 * Returns $str, sanitizing data so that its format is normalized.
 *
 * @param   mixed $str Input data.
 * @param   Closure $closure Optional.
 *
 * @return  mixed
 */
function escape($str, Closure $closure = null)
{
    if (is_string($str)) {
        $str = trim($str);

        if (empty($str) || is_numeric($str)) {
            return $str;
        }

        return isset($closure) ? $closure($str) : antiXss($str);
    }

    return is_scalar($str) ? $str : '';
}

/**
 * Returns $str that might be a date, sanitizing data so that its format is normalized.
 *
 * @param   mixed $str Input data.
 * @param   int $timespan Optional.
 *
 * @return  mixed
 */
function escapeDate($str, $timespan = null)
{
    return escape(
        $str,
        function ($str) use ($timespan) {
            if (false !== ($timestamp = strtotime($str)) && $timestamp != time()) {
                $carbon = Carbon::createFromTimestamp($timestamp, date_default_timezone_get());

                if (isset($timespan)) {
                    $carbon->addSeconds($timespan);
                }

                return "'" . $carbon->toDateTimeString() . "'";
            }

            return "'" . strtr(antiXss($str), ["'" => "''", "%" => "%%"]) . "'";
        }
    );
}

/**
 * Returns $str surrounded by single quotation marks, sanitizing data so that its format is normalized.
 *
 * @param   mixed $str Input data.
 *
 * @return  mixed
 */
function escapeQuotes($str)
{
    return escape(
        $str,
        function ($str) {
            return "'" . strtr(antiXss($str), ["'" => "''", "%" => "%%"]) . "'";
        }
    );
}
