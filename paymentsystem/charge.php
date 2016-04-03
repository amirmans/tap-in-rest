<?php
require_once(dirname(__FILE__) . '/config.php');

$token    = $_REQUEST['stripeToken'];
$amount   = $_REQUEST['amount'];
$currency = $_REQUEST['currency'];
$customerID = $_REQUEST['customerPaymentID'];
$customerPaymentProcessingEmail = $_REQUEST['customerPaymentProcessingEmail'];

// $amount = "15800";
// $currency ="usd";

if ((strlen($customerID) < 10) || ($customerID == null)) {
  $customer = Stripe_Customer::create(array(
       'email' => $customerPaymentProcessingEmail,
       'card'  => $token
   ));
  $customerID = $customer->id;
}

try {
	$charge = Stripe_Charge::create(array(
		'customer' => $customerID,
		'amount'   => $amount,
		'currency' => $currency
		));
} catch (Stripe_CardError $e) {
	echo "<h1>You're lucky - could not charge $amount!</h1>";
	exit;
}

echo "<h1>Successfully charged $amount!</h1>";
?>
