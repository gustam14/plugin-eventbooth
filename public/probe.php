<?php
header('Content-Type: text/plain');
$keys = ['REQUEST_URI','SCRIPT_NAME','PHP_SELF','DOCUMENT_ROOT','SCRIPT_FILENAME','SERVER_SOFTWARE','REDIRECT_URL','REDIRECT_STATUS','PATH_INFO','ORIG_PATH_INFO','GATEWAY_INTERFACE'];
$out = [];
foreach ($keys as $k) { $out[$k] = $_SERVER[$k] ?? null; }
echo json_encode($out, JSON_PRETTY_PRINT);
