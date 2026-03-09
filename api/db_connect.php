<?php
// api/db_connect.php

$serverName = "172.16.0.64";

$connectionOptions = array(
    "Database" => "AsefaChatAI",
    "Uid" => "innovation",
    "PWD" => "InAe@09nosf2i!@#",
    "CharacterSet" => "UTF-8",
    "MultipleActiveResultSets"    =>    true,
    "ReturnDatesAsStrings"        =>    true,
    "TrustServerCertificate" => "true"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    header('Content-Type: application/json');
    echo json_encode(["status" => false, "message" => "Database connection failed."]);
    exit();
}
