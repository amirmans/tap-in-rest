<?php
require_once('db_manager.php');

header('Content-type: application/json');
$errorArr= array();

$counter = 0;
$done = FALSE;
while (($counter++ <= 2) and ($done == FALSE)) {
    switch ($counter) {
        case 1:
        $passedArg = $_GET["businessID"];
            $consumer_id = $_GET["consumer_id"];

        if ($passedArg != "") {
            $done = TRUE;
            // 0 is a special number - no businesses can have an ID of 0 - 0 means all of the businesses
            if ($passedArg == 0) {
                $appMode =  $_GET["tableName"]; // different TapFor apps deal with different subsets of businesses
                if (!sendListOfAllBusinesses($consumer_id, $appMode)) {
					$error['message'] = 'Something went wrong.  Please try again.';
                    $error["status"] =-1;
					$errorOutput = json_encode($error);
					echo $errorOutput;
				}
            }
            else  {
                // if (!getBusinessProducts($passedArg)) {
                if (!products_for_business($passedArg, $consumer_id)) {
                    $error['message'] = $passedArg + ' is NOT a customer';
                    $error["status"] =-1;
					$errorOutput = json_encode($error);
					echo $errorOutput;
                }
            }
        }
        break;

                case 2:
                header('Content-type: application/json');
                $passedArg = json_decode(file_get_contents('php://input'), TRUE);
                if ($passedArg != "") {
                    $done = TRUE;
                    if ($passedArg == 0) {
                        if (!sendListOfAllBusinesses($consumer_id, $appMode)) {
                            $error['message'] = 'Something went wrong.  Please try again.';
                            $error["status"] =-1;
                            $errorOutput = json_encode($error);
                            echo $errorOutput;
                        }
                    }
                    else  {
                        if (!products_for_business($passedArg, $consumer_id)) {
                            $error['message'] = $passedArg + ' is NOT a customer';
                            $error["status"] =-1;
                            $errorOutput = json_encode($error);
                            echo $errorOutput;
                        }
                    }
                }
                break;




        case 3:
        $passedArg = $_GET["businessName"];
        if ($passedArg != "")
        {
            $done = true;
//        $arr = array('a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'couponcode' => $test);
//        echo json_encode($arr);
            // When this is called - Client doesn't have the IDs yet - it is getting the names from Google map api
            if (isThisBusinessCustomer($passedArg)) {
            } else {
				$error['message'] = $passedArg + ' is NOT a customer';
				$error["status"] =-1;
				$errorOutput = json_encode($error);
				echo $errorOutput;
            }
        }
    }
}

if ( ($counter > 2) and ($done == FALSE)) {
    $errorArr['message'] = 'Idiot pass the code!!';
    $errorArr["status"] =-10;
	echo json_encode($errorArr);
}

?>