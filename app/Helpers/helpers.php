<?php

if (!function_exists('array_first')) {
    /**
     * Get the first element of an array. Useful for older Laravel versions.
     *
     * @param  array  $array
     * @return mixed
     */
    function array_first(array $array) {
        return reset($array);
    }
}
