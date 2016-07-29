<?php
$result_arr = array();

try {

    // Are we running in development or production mode? You can easily switch
    // between these two in the Apache VirtualHost configuration.
    if (!defined('APPLICATION_ENV')) define('APPLICATION_ENV', getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production');

    // In development mode, we show all errors because we obviously want to
    // know about them. We don't do this in production mode because that might
    // expose critical details of our app or our database. Critical PHP errors
    // will still be logged in the PHP and Apache error logs, so it's always
    // a good idea to keep an eye on them.
    if (APPLICATION_ENV == 'development') {
        error_reporting(E_ALL | E_STRICT);
        ini_set('display_errors', 'on');
    } else {
        error_reporting(0);
        ini_set('display_errors', 'off');
    }

    // Load the config file. I prefer to keep all configuration settings in a
    // separate file so you don't have to mess around in the main code if you
    // just want to change some settings.
    require_once '../include/api_config.php';
    $config = $config[APPLICATION_ENV];

    // In development mode, we fake a delay that makes testing more realistic.
    // You're probably running this on a fast local server but in production
    // mode people will be using it on a mobile device over a slow connection.
    if (APPLICATION_ENV == 'development') sleep(2);

    // To keep the code clean, I put the API into its own class. Create an
    // instance of that class and let it handle the request.
    $api = new API($config);
    $api->handleCommand();

    header('Content-type: application/json');
    $result_arr['status_code'] = "0";
    $result_json = json_encode($result_arr);
    echo $result_json;

    //echo "OK" . PHP_EOL;

}
catch(Exception $e) {

    // The code throws an exception when something goes horribly wrong; e.g.
    // no connection to the database could be made. In development mode, we
    // show these exception messages. In production mode, we simply return a
    // "500 Server Error" message.

    if (APPLICATION_ENV == 'development') var_dump($e);
    else exitWithHttpError(500);
}

////////////////////////////////////////////////////////////////////////////////

function exitWithHttpError($error_code, $message = '') {
    switch ($error_code) {
        case ($error_code <= 410):
            header("HTTP/1.0 400 Bad post values");
            break;

        case 400:
            header("HTTP/1.0 400 Bad Request");
            break;

        case 403:
            header("HTTP/1.0 403 Forbidden");
            break;

        case 404:
            header("HTTP/1.0 404 Not Found");
            break;

        case 500:
            header("HTTP/1.0 500 Server Error");
            break;
    }

    //  header('Content-Type: text/plain');
    header('Content-type: application/json');

    if ($message != '') header('X-Error-Description: ' . $message);

    echo ("Exiting with error: $error_code");
    error_log("Exiting with error: [$error_code]", 1);

    exit;
}

function isValidUtf8String($string, $maxLength, $allowNewlines = false) {
    if (empty($string) || strlen($string) > $maxLength) return false;

    if (mb_check_encoding($string, 'UTF-8') === false) return false;

    // Don't allow control characters, except possibly newlines
    for ($t = 0; $t < strlen($string); $t++) {
        $ord = ord($string{$t});

        if ($allowNewlines && ($ord == 10 || $ord == 13)) continue;

        if ($ord < 32) return false;
    }

    return true;
}

function truncateUtf8($string, $maxLength) {
    $origString = $string;
    $origLength = $maxLength;

    while (strlen($string) > $origLength) {
        $string = mb_substr($origString, 0, $maxLength, 'utf-8');
        $maxLength--;
    }

    return $string;
}

////////////////////////////////////////////////////////////////////////////////

class API
{

    // Because the payload only allows for 256 bytes and there is some overhead
    // we limit the message text to 190 characters.
    const MAX_MESSAGE_LENGTH = 190;

    private $pdo;

    function __construct($config) {

        // Create a connection to the database.
        $this->pdo = new PDO('mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['dbname'], $config['db']['username'], $config['db']['password']);

        // If there is an error executing database queries, we want PDO to
        // throw an exception. Our exception handler will then exit the script
        // with a "500 Server Error" message.
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // We want the database to handle all strings as UTF-8.
        $this->pdo->query('SET NAMES utf8');
    }

    function handleCommand() {

        // Figure out which command the client sent and let the corresponding
        // method handle it. If the command is unknown, then exit with an error
        // message.
        //TODO: change back to _POST
//        if (isset($_REQUEST['cmd'])) {
          if (1) {
            switch (trim($_REQUEST['cmd'])) {
                case 'join':
                    $this->handleJoin();
                    return;
                case 'join_with_devicetoken':
                    // $this->handleJoinWithDeviceToken();
                    $this->handleJoin();
                    return;
                case 'update':
                    $this->handleJoin();
                    return;
                case 'updateDeviceToken':
                case 'device_token':
                    $this->handleUpdateDeviceToken();
                    return;
                case 'getQRImage':
                    $this->handleGetQRImage();
                    return;
              default:
                $this->handleJoin();
                return;

            }
        }

        exitWithHttpError(401, 'Unknown command');
    }

    function handleGetQRImage() {
        $table_name = "consumer_profile";
        $userID = $this->getUID();
        $sql_statement = "Select qrcode_file from $table_name where uid = ?";
        $stmt = $this->pdo->prepare($sql_statement);
        $stmt->execute(array($userID));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result !== false) {
            global $result_arr;
            $result_arr['qrcode_file'] = $result[0]['qrcode_file'];
             //echo $userID;

        } else {
        }
    }

    // The "join" API command registers a user to receive notifications that
    // are sent in a specific "chat room". Each chat room is identified by a
    // secret code. All the users who register with the same secret code can
    // see each other's messages.
    //
    // This command takes the following POST parameters:
    //
    // - name:  The nickname of the user. Must be a UTF-8 string of maximum 255
    //          bytes. Only the first 20 bytes are actually shown in the push
    //          notifications.
    // - password:
    //
    function handleJoin() {
        $executeArray = array();
        $Update_executeArray = array();

        $nickname = $this->getString('nickname', 30, true);
        $password = $this->getString('password', 20, true);
        $device_token = $this->getString('device_token', 64, true);
        $uuid = $this->getString('uuid', 64, true);
        $email = $this->getString('email', 30, true);
        $zipcode = $this->getString('zipcode', 12, true);
        $age_group = $this->getInt("age_group", true);

        $table_name = 'consumer_profile';
        $updateStatement = "";

        $executeArray[] = $uuid;
        $sqlStatement = "INSERT INTO $table_name (uuid";
        $valuesStatement = " VALUES(?";
        if (!empty($password)) {
            $executeArray[] = $password;
            $sqlStatement = $sqlStatement . " ,password";
            if (strlen($updateStatement) > 1) {
                $updateStatement = $updateStatement . ", ";
            }
            $updateStatement = $updateStatement . "password = ?";
            $Update_executeArray[] = $password;
            $valuesStatement = $valuesStatement . ", ?";
        }

        if (!empty($device_token)) {
            $executeArray[] = $device_token;
            $sqlStatement = $sqlStatement . " ,device_token";
            if (strlen($updateStatement) > 1) {
                $updateStatement = $updateStatement . ", ";
            }
            $updateStatement = $updateStatement . "device_token = ?";
            $Update_executeArray[] = $device_token;
            $valuesStatement = $valuesStatement . ", ?";
        }

        if (!empty($nickname)) {
            $executeArray[] = $nickname;
            $sqlStatement = $sqlStatement . " ,nickname";
            if (strlen($updateStatement) > 1) {
                $updateStatement = $updateStatement . ", ";
            }
            $updateStatement = $updateStatement . "nickname = ?";
            $Update_executeArray[] = $nickname;
            $valuesStatement = $valuesStatement . ", ?";
        }
        if (!empty($zipcode)) {
            $executeArray[] = $zipcode;
            $sqlStatement = $sqlStatement . ",zipcode";
            if (strlen($updateStatement) > 1) {
                $updateStatement = $updateStatement . ", ";
            }
            $updateStatement = $updateStatement . "zipcode = ?";
            $Update_executeArray[] = $zipcode;
            $valuesStatement = $valuesStatement . ", ?";
        }
      if (!empty($email)) {
        $executeArray[] = $email;
        $sqlStatement = $sqlStatement . ",email1";
        if (strlen($updateStatement) > 1) {
          $updateStatement = $updateStatement . ", ";
        }
        $updateStatement = $updateStatement . "email1 = ?";
        $Update_executeArray[] = $email;
        $valuesStatement = $valuesStatement . ", ?";
      }
        if ($age_group > - 1) {
            $executeArray[] = $age_group;
            $sqlStatement = $sqlStatement . ",age_group";
            if (strlen($updateStatement) > 1) {
                $updateStatement = $updateStatement . ", ";
            }
            $updateStatement = $updateStatement . "age_group = ?";
            $Update_executeArray[] = $age_group;
            $valuesStatement = $valuesStatement . ", ?";
        }

        $sqlStatement = $sqlStatement . ")";
        $valuesStatement = $valuesStatement . ")";

        $this->pdo->beginTransaction();

        // $sql_statement = "INSERT INTO $table_name (nickname, password, age_group) "
        // . "VALUES (?,?,?) ON DUPLICATE KEY UPDATE password=?, age_group=?";

        if (strlen($updateStatement) > 1) {
            $updateStatement = " ON DUPLICATE KEY UPDATE " . $updateStatement;
            $executeArray = array_merge($executeArray, $Update_executeArray);
        }
        $finalSqlStatement = $sqlStatement . $valuesStatement . $updateStatement;

        $stmt = $this->pdo->prepare($finalSqlStatement);
        $stmt->execute($executeArray);
        $userID = $this->pdo->lastInsertId();
        $this->pdo->commit();

        global $result_arr;
        $result_arr['userID'] = $userID;

        $sql_statement = "Select qrcode_file from $table_name where uid = ?";
        $stmt = $this->pdo->prepare($sql_statement);
        $stmt->execute(array($userID));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result !== false) {
            $result_arr['qrcode_file'] = $result[0]['qrcode_file'];

            //echo $userID;

        }
    }

    function handleJoinWithDeviceToken() {
        $nickname = $this->getString('nickname', 30);
        $email = $this->getString('email', 30);
        $zipcode = $this->getString('zipcode', 12);
        $password = $this->getString('password', 30);
        $age_group = $this->getInt("age_group");
        $device_token = $this->getString('device_token', 64);

        // When the client sends a "join" command, we add a new record to the
        // active_users table. We identify the client by the UDID that it
        // provides. When the client sends a "leave" command, we delete its
        // record from the active_users table.

        // It is theoretically possible that a client sends a "join" command
        // while its UDID is still present in active_users (because it did not
        // send a "leave" command). In that case, we simply remove the old
        // record first and then insert the new one.

        $this->pdo->beginTransaction();
        $table_name = 'consumer_profile';

        $sql_statement = "INSERT INTO $table_name (nickname, password, email1, zipcode, age_group, device_token) " .
            "VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE password=?, age_group=?, email1=?, zipcode =?";

        //      $stmt = $this->pdo->prepare('INSERT INTO consumer_profile (nickname, password) VALUES (?, ?)');
        $stmt = $this->pdo->prepare($sql_statement);
        $stmt->execute(array($nickname, $password, $email, $zipcode, $age_group, $device_token, $password, $age_group, $email, $zipcode));

        $userID = $this->pdo->lastInsertId();

        $this->pdo->commit();

        global $result_arr;
        $result_arr['userID'] = $userID;

        //echo $userID;

    }

    function handleUpdateDeviceToken() {
        $device_token = $this->getString('device_token', 64);
        $uid = $this->getUID();

        $table_name = 'consumer_profile';
        $this->pdo->beginTransaction();
        $sql_statement = "INSERT INTO $table_name (uid, device_token)
      VALUES (?,?) ON DUPLICATE KEY UPDATE device_token=?";

        //      $stmt = $this->pdo->prepare('INSERT INTO consumer_profile (nickname, password) VALUES (?, ?)');
        $stmt = $this->pdo->prepare($sql_statement);
        $result = $stmt->execute(array($uid, $device_token, $device_token));
        $userID = $this->pdo->lastInsertId();
        $this->pdo->commit();

        global $result_arr;
        $result_arr['userID'] = $userID;

        //echo $userID;

    }

    // The "update" API command gives a user a new device token.
    //
    // This command takes the following POST parameters:
    //
    // - udid:  The device's UDID. Must be a string of 40 hexadecimal characters.
    // - token: The device's device token. Must be a string of 64 hexadecimal
    //          characters.
    //
    // This is not being used now
    function handleUpdate() {
        $executeArray = array();

        $uid = $this->getUID();
        $nickname = $this->getString('nickname', 30);
        $password = $this->getString('password', 30, true);
        $email = $this->getString('email', 30, true);
        $zipcode = $this->getString('zipcode', 12, true);
        $age_group = $this->getInt("age_group", true);

        $table_name = "consumer_profile";

        // uid is always mandatory as it is a key
        $executeArray[] = $nickname;
        $sqlStatement = "UPDATE $table_name SET nickname = ?";
        if (!empty($password)) {
            $executeArray[] = $password;
            $sqlStatement = $sqlStatement . ",password = ?";
        }
        if (!empty($email)) {
            $executeArray[] = $email;
            $sqlStatement = $sqlStatement . ",email1 = ?";
        }
        if (!empty($zipcode)) {
            $executeArray[] = $zipcode;
            $sqlStatement = $sqlStatement . ",zipcode = ?";
        }
        if ($age_group > - 1) {
            $executeArray[] = $age_group;
            $sqlStatement = $sqlStatement . ",age_group = ?";
        }
        $sqlStatement = $sqlStatement . " where uid = ?";
        $executeArray[] = $uid;

        if (!isset($_REQUEST['device_token'])) {

            // no device token, update everything but
            //       $statementString = "UPDATE $table_name SET nickname = ?, password = ?, age_group = ?  WHERE uid = ?";
            $stmt = $this->pdo->prepare($sqlStatement);

            // $stmt->execute(array($nickname, $password, $age_group,$uid));
            $stmt->execute($executeArray);
        } else {
            $device_token = $this->getString('device_token', 64);
            $statementString = "UPDATE $table_name SET nickname = ?, password = ?, email1 = ?, zipcode = ?, age_group = ?, device_token = ?  WHERE uid = ?";
            $stmt = $this->pdo->prepare($statementString);
            $stmt->execute(array($nickname, $password, $email, $zipcode, $age_group, $device_token, $uid));
        }
    }

    function getUID() {
        if (!isset($_REQUEST["uid"])) exitWithHttpError(408, "No uid was passed");
        $uid = trim($_REQUEST["uid"]);

        settype($uid, "int");
        return $uid;
    }

    function getInt($fieldName, $optional = false) {
        $returnVal = trim($_REQUEST[$fieldName]);
        if (optional && empty($returnVal)) {
            return -1;
        } else if (empty($returnVal)) {
            exitWithHttpError(408, "No $fieldName was passed");
        } else {
            settype($returnVal, "int");
            return $returnVal;
        }
    }

    // Looks in the POST data for a field with the given name. If the field
    // is not a valid UTF-8 string, or it is too long, the script exits with
    // an error message.
    function getString($name, $maxLength, $optional = false) {
        $string = trim($_REQUEST[$name]);
        if ($optional && empty($string)) {
            return $string;
        }
        if (!isset($_REQUEST[$name])) exitWithHttpError(406, "Missing $name");

        if (!isValidUtf8String($string, $maxLength, false)) exitWithHttpError(407, "Invalid $name");

        return $string;
    }

    // Creates the JSON payload for the push notification message. The "alert"
    // text has the following format: "sender_name: message_text". Recipients
    // can obtain the name of the sender by parsing the alert text up to the
    // first colon followed by a space.
    function makePayload($senderName, $text) {

        // Convert the nickname of the sender to JSON and truncate to a maximum
        // length of 20 bytes (which may be less than 20 characters).
        $nameJson = $this->jsonEncode($senderName);
        $nameJson = truncateUtf8($nameJson, 20);

        // Convert and truncate the message text
        $textJson = $this->jsonEncode($text);
        $textJson = truncateUtf8($textJson, self::MAX_MESSAGE_LENGTH);

        // Combine everything into a JSON string
        $payload = '{"aps":{"alert":"' . $nameJson . ': ' . $textJson . '","sound":"default"}}';
        return $payload;
    }

    // We don't use PHP's built-in json_encode() function because it converts
    // UTF-8 characters to \uxxxx. That eats up 6 characters in the payload for
    // no good reason, as JSON already supports UTF-8 just fine.
    function jsonEncode($text) {
        static $from = array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"');
        static $to = array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"');
        return str_replace($from, $to, $text);
    }
}
