<?php
date_default_timezone_set('America/Los_Angeles');
//require("map_dbinfo.php");
require("../../include/config_db.inc.php");
require("google_geocoding_info.inc");

function pt_error_log($err_msg) {
    $myFile = "./logs/error.log";
    if (file_exists($myFile)) {
      $fh = fopen($myFile, 'a');
      fwrite($fh, $err_msg."\n");
    } else {
      $fh = fopen($myFile, 'w')  or die("Cannot open file \"$myFile\"...\n");
      fwrite($fh, $err_msg) ;
    }
    //error_log($err_msg, 3, "./logs/error.log");
    //error_log($err_msg, 3, "./error.log");
}

function parseToXML($htmlStr)
{
    $xmlStr=str_replace('<','&lt;',$htmlStr);
    $xmlStr=str_replace('>','&gt;',$xmlStr);
    $xmlStr=str_replace('"','&quot;',$xmlStr);
    $xmlStr=str_replace("'",'&#39;',$xmlStr);
    $xmlStr=str_replace("&",'&amp;',$xmlStr);

    return $xmlStr;
}

function prepareAddressForGoogle($address)
{
    $address= str_replace(" ","+", $address);
    return $address;
}

//get user's input
//$search_zipcode = filter_input(INPUT_POST, 'zipcode');
$search_zipcode = $_REQUEST['zipcode'];
$search_zipcode = urldecode($search_zipcode);
//$search_date = filter_input(INPUT_POST, 'date');
$search_date = $_REQUEST['date'];
//$search_home_phone = filter_input(INPUT_POST, 'home_phone');
//$search_date = date('Y-m-d');

//$date = new DateTime();
$date = date("Y-m-d H:i:s");
//$startDateTimeString = $date->format('Y-m-d-H_i_s');
$err_msg = "Info ---------- $startDateTimeString" . " Calulating geocodes for $search_zipcode on $search_date\n";
pt_error_log($err_msg);

// Opens a connection to a MySQL server
$connection = new mysqli("localhost", $db_user, $db_pass, $db_name);
//$connection=mysql_connect (localhost, $username, $password);
if (!$connection) {
  pt_error_log("Fetal - Database Error: ".$connection->connect_error. "\n");
  die('Not connected : ' . $connection->connect_errno . " :". $connection->connect_error);
}

// Select all the rows in the markers table
//$query = "SELECT * FROM markers WHERE 1";
$query = "SELECT a.name, a.address, a.lat, a.lng, a.businessID, a.zipcode
FROM businessCustomers a";

if ($search_zipcode != 0)
    $query = $query .  " and a.zip_code in ($search_zipcode)";
$query = $query . " ORDER BY name ASC";

$result = $connection->query($query);
//$result = mysql_query($query);
if (!$result) {
  pt_error_log("Fatal - invalid query in Map subsystem: ". $connection->connect_error. "\n");
  die('Invalid query: ' . $connection->connect_error);
}
$numberOfRowsFound =  $result->num_rows;
$rowCounter = 0;

header("Content-type: text/xml");
// Start XML file, echo parent node
echo '<markers>';

// Iterate through the rows, printing XML nodes for each
while ($row = $result->fetch_assoc()) {

    $lat = 0.0;
    $lng = 0.0;
    $rowCounter++;
//    if ( (strlen($row["address1"]) <= 0) && (strlen($row["address1"]) <= 0 ) )
//    {
//        pt_error_log("Warning - No address found for ".$row["home_phone"]."\n");
//        continue;
//    }
    $home_phone = $row["businessID"];

    $address = $row["address"];

    $address = rtrim($address);
    if ( strlen($address) <= 0 )
    {
        pt_error_log("Warning - No address found for ".$address."\n");
        continue;
    }

  // ADD TO XML DOCUMENT NODE
    echo '<marker ';
    $name = $row["name"];
    echo 'name="' . parseToXML($name) . '" ';

    $address = parseToXML($address);
    echo 'address="' . $address . '" ';

    if (strlen($row["lat"]) <= 3 or  strlen($row["lng"]) <= 3 or (float)$row["lat"] == 0.00 or (float)$row["lng"] == 0.00) {
  //Latitude/Longitude Lookup using goole geocoding api
      $addressForGoogle = prepareAddressForGoogle($address);
      $google_geocoding_url = $base_google_geocoding_url . '&address=' . $addressForGoogle;

      $response = file_get_contents("$google_geocoding_url");
//      pt_error_log("Response from Google Map Api is: ".$response."\n");
  //remember without passing true as the second argument, you will get a
  //stdClass instead of an associative array
      $jsonData = json_decode($response, true);
      $jsonDataResult = $jsonData["results"];

      // if the address is not totaly correct, google will give you an array of all the possible addresses - fuzzy logiz
      // we need to traverse thru this array and find out the correct zip code - zip code is the key
      $index_with_correct_result = -1; // any invlaid index
      $response_length = count($jsonDataResult);
      $responseIndex = 0;
      $locationFound = FALSE;
      $postalCodeFound = False;

      while ($responseIndex < $response_length && !$locationFound)
      {
          $address_components = $jsonDataResult[$responseIndex]["address_components"];
          $addressCount = count($address_components);
          for ($jsonAddressIndex =0; $jsonAddressIndex < $addressCount; $jsonAddressIndex++)
          {
            if ($address_components[$jsonAddressIndex]["types"][0]=="postal_code")
            {
                $postalCodeFound = True;
                if ($address_components[$jsonAddressIndex]["short_name"] == $row['zipcode'])
                {
                    // found it
                    $index_with_correct_result = $responseIndex;
                    $locationFound = true;
                    break; // break out for loop
                }
            }
          }

        $responseIndex++;
      }

//      if (!$postalCodeFound)
//          $index_with_correct_result = 0;

      if ($index_with_correct_result >=0) {
        $mapResults = $jsonData["results"][$index_with_correct_result];
        $geometry = $mapResults["geometry"];
        $location = $geometry["location"];
        $lat = $location["lat"];
        $lng = $location["lng"];

        //insert the values in the database, for the next time
          $businessID = $row["businessID"];
        $update_query = "Update businessCustomers  set lat = $lat, lng = $lng where businessID = $businessID";
        $update_result = $connection->query($update_query);
        if (!$update_result) {
             pt_error_log("Fetal - Database Error". $connection->connect_error);
             //die('Invalid query: ' . mysql_error());
         }
//        $update_result->free();
        pt_error_log("Info - Called $google_geocoding_url for geocodes for $home_phone\n");
      }
      else {
          pt_error_log("Warning - no accurate info from google for $home_phone\n");
          $errorMSg = $row["first_name"] . " " . $row["last_name"] . " Phone: ". $row["home_phone"] . " incorrect address: ". $address;
          error_log($errorMSg ."\n". $google_geocoding_url . "\n\n", 3, "./logs/bad_bad_addresses_$startDateTimeString.txt");
      }
  } else {
      pt_error_log("Info - Geocodes for $home_phone found in DB\n");
      $lat = $row["lat"];
      $lng = $row["lng"];
  }

  echo 'lat="' . $lat . '" ';
  echo 'lng="' . $lng . '" ';
  echo '/>';

}
// End XML file
echo '</markers>';
$endDateTime = new DateTime();
pt_error_log("Info ---------- " . $endDateTime->format('Y-m-d H:i:s') . " End of calulating geocodes for $search_zipcode on $search_date\n\n");
$result->free();
?>