<?php
require_once("SEPASDD.php");
$config = array("name" => "Test",
                "IBAN" => "NL50BANK1234567890",
                "BIC" => "BANKNL2A",
                "batch" => "true",
                "creditor_id" => "00000",
                "currency" => "EUR"
                );
                
$payment = array("name" => "Test von Testenstein",
                 "IBAN" => "NL50BANK1234567890",
                 "BIC" => "BANKNL2A",
                 "amount" => "1000",
                 "type" => "FRST",
                 "collection_date" => date("Y-m-d"),
                 "mandate_id" => "1234",
                 "mandate_date" => date("Y-m-d"),
                 "description" => "Test transaction"
                );                
try{
    $SEPASDD = new SEPASDD($config);
    $SEPASDD->addPayment($payment);
    $SEPASDD->addPayment($payment);
    print_r($SEPASDD->save());
}catch(Exception $e){
    echo $e->getMessage();
    exit;
}

?>