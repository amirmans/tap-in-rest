<?php
require_once('../Library/stripe/lib/Stripe.php');

$stripe = array(
  "secret_key"      => "",
  "publishable_key" => ""
);

Stripe::setApiKey($stripe['secret_key']);
?>