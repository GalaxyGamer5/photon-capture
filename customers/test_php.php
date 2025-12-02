<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
echo json_encode(["status" => "ok", "message" => "PHP works on gallery subdomain"]);
?>
