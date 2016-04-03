<?php
//header( 'Content-type: text/xml' );
include '../include/tapforall_dsn.inc';

// open database
$opt = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => true);
$dsn = "mysql:host=$host;dbname=$DBName";
$dbh = new PDO($dsn, $DBUser, $DBPass, $opt);

if ($conn->connect_error) {
	trigger_error('Database connection failed: '  . $conn->connect_error, E_USER_ERROR);
}

// get input passed from the client
$table_name = filter_input(INPUT_GET, 'chatroom', FILTER_SANITIZE_STRING);
$limit = filter_input(INPUT_GET, 'max_rows', FILTER_SANITIZE_STRING);
if (is_null($limit))
	$limit = 0;
$past = filter_input(INPUT_GET, 'past', FILTER_SANITIZE_STRING);
if (is_null($past))
	$past = 0;

$result = array();

// construct sql statement 
try {
    $statementHandler = $dbh->prepare("SELECT * FROM $table_name where (dateAdded >= CURRENT_TIMESTAMP - INTERVAL :hoursago HOUR)");
    $statementHandler->bindParam(':hoursago', $past);
    $statementHandler->execute();
    $result = $statementHandler->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	echo $e->getMessage();
} 

// construct output for the client
//$reverse_time_array = array_reverse($result); 
print json_encode($result);
//mysql_free_result($result);
?>