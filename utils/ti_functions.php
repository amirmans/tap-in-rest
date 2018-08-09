<?php

include_once(dirname(dirname(__FILE__)) . '/include/consts_server.inc');
include_once(dirname(dirname(__FILE__)) . '/include/error_logging/error.php');

function askServerToPerformATask($functionFile, $params) {
    $url = MerchantsBaseURL . "$functionFile";

    $post_params = array();
    foreach ($params as $key => &$val) {
        if (is_array($val))
            $val = implode(',', $val);
        $post_params[] = $key . '=' . urlencode($val);
    }
    $post_string = implode('&', $post_params);
    $parts = parse_url($url);
    $fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 30);

    $out = "POST " . $parts['path'] . " HTTP/1.1\r\n";
    $out.= "Host: " . $parts['host'] . "\r\n";
    $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
    $out.= "Content-Length: " . strlen($post_string) . "\r\n";
    $out.= "Connection: Close\r\n\r\n";
    if (isset($post_string))
        $out.= $post_string;

    fwrite($fp, $out);
    fclose($fp);
}

function removeslashes($string)
{
    $string = implode("", explode("\\", $string));
    return stripslashes(trim($string));
}

function stripslashes_deep($value)
{
    $value = is_array($value) ?
        array_map('stripslashes_deep', $value) :
        stripslashes($value);

    return $value;
}

?>