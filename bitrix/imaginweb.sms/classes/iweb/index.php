<?php

include_once("smsClient.php");

echo "<h1>SMS PHP Client Test</h1>";

$client = new SMSClient("Test_MC","1q2w3e4r");
$sessionID = $client->getSessionID();
echo "<h2>SessionID:</h2>";
print($client->getSessionID());
echo "<hr>";
echo "<h2>Balance:</h2>";
print($client->getBalance());
echo "<hr>";
echo "<h2>Send SMS:</h2>";
try {
	print_r($client->send("TestMC","79161234567","Hi!"));
} catch( SMSError_Exception $e ) {
	print_r($e);
}
echo "<hr>";
echo "<h2>Send SMS By Time Zone:</h2>";
try {
	print_r($client->sendByTimeZone("TestMC","79161234567","Hi!","2012-07-11T18:50:15"));
} catch( SMSError_Exception $e ) {
	print_r($e);
}
echo "<hr>";
echo "<h2>Send SMS Bulk:</h2>";
try {
	print_r($client->sendBulk("TestMC",array("79161234567","79101234567"),"Hi2!","2012-07-11T18:50:15"));
} catch( SMSError_Exception $e ) {
	print_r($e);
}

echo "<HR>";
echo "<h2>GetMessageState:</h2>";
print_r($client->getSMSState("GW0261BA732A"));
echo "<HR>";
echo "<h2>GetMessageIn:</h2>";
print_r($client->getInbox("2010-01-01T00:00:00","2010-01-11T00:00:00"));
echo "<HR>";
echo "<h2>GetStatistics:</h2>";
print_r($client->getStatistics("2012-01-18T00:00:00","2012-01-18T23:59:59"));
