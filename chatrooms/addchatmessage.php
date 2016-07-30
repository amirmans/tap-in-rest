<?php
header( 'Content-type: text/xml' );
include '../include/config_db.inc.php';

switch($_SERVER['REQUEST_METHOD'])
{
    case 'GET': $request_method = INPUT_GET; break;
    case 'POST': $request_method = INPUT_POST; break;
}

$user_name = filter_input($request_method, 'user_name', FILTER_SANITIZE_STRING);
$message = filter_input($request_method, 'message', FILTER_SANITIZE_STRING);
if ($message)
	$message = htmlspecialchars_decode ($message, ENT_QUOTES);
$table_name = filter_input($request_method, 'chatroom', FILTER_SANITIZE_STRING);
$user_id = filter_input($request_method, 'user_id', FILTER_SANITIZE_STRING);

$opt = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => true);
$dsn = "mysql:host=$db_host;dbname=$db_name";
$dbh = new PDO($dsn, $db_user, $db_pass, $opt);

if ($conn->connect_error) {
	trigger_error('Database connection failed: '  . $conn->connect_error, E_USER_ERROR);
}
try {
	$statementHandler = $dbh->prepare("INSERT INTO $table_name (sender, textChat, sender_id) VALUES (:sender,:message, :sender_id)");

	$statementHandler->bindParam(':sender', $user_name);
	$statementHandler->bindParam(':message', $message);
	$statementHandler->bindParam(':sender_id', $user_id);
	$statementHandler->execute();

} catch (PDOException $e) {
	echo $e->getMessage();
}
?>
<Successs/>