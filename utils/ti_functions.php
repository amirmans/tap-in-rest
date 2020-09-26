<?php

include_once(dirname(dirname(__FILE__)) . '/include/consts_server.inc');
include_once(dirname(dirname(__FILE__)) . '/include/error_logging/error.php');

function askServerToPerformATask($functionFile, $params) {
	$url = getenv('APP_HOSTNAME'). "$functionFile";

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

function calc_pickup_cutoff_date(&$dateArr, $int_weekday, $no_days) {
    $weekday ="";
    switch ($int_weekday) {
        case 1:
            $weekday = "monday";
            break;
        case 2:
            $weekday = "tuesday";
            break;
        case 3:
            $weekday = "wednesday";
            break;
        case 4:
            $weekday = "thursday";
            break;
        case 5:
            $weekday = "friday";
            break;
        case 6:
            $weekday = "saturday";
            break;
        case 0:
            $weekday = "sunday";
            break;
    }

    // $date  = date("Y M D", mktime(0, 0, 0, date("m"), 0, 2020));
    $pickup_date = date_create();
    $cutoff_date = date_create();

    if ($no_days <= 0) {
        $sign = "";
    }
    else {
        $sign = "+";
    }
    $i = 0;
    While ($i < 2 ) {
        if ($i == 1) {
            $weekday = $weekday . " + 1 week";
        }
        $pickup_date = date('m/d/y', strtotime($weekday));

        $strDate = "$pickup_date " . $sign . ($no_days) . " day";
        $cutoff_date = date("m/d/y", strtotime($strDate));

        $today = date('m/d/Y');
        if ($today == $cutoff_date) {
            break;
        }
        else if ($today > $cutoff_date) {
            $i++;
        }
        else {
            break;
        }
    }

    $dateArr['pickup_date'] = $pickup_date;
    $dateArr['cutoff_date'] = $cutoff_date;

    $start_date = $dateArr['cutoff_date'];
    $strDate = "$start_date " . "-7 day";
    $dateArr['order_start_date'] = date("m/d/y", strtotime($strDate));
    $dateArr['order_end_date'] = $dateArr['cutoff_date'];

} //function
