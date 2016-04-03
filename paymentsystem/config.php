<?php
require_once('../Library/stripe/lib/Stripe.php');

$stripe = array(
  "secret_key"      => "sk_test_mBTVbAuKGx5FDk8dXXSQCay4",
  "publishable_key" => "pk_test_zrEfGQzrGZAQ4iUqpTilP6Bi"
);

Stripe::setApiKey($stripe['secret_key']);
?>