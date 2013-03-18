<?php

$uri = $_SERVER['REQUEST_URI'];
$uriClean = str_replace('/index.php/', '/', $uri);
if ($uri != $uriClean) {
	header('Location: ' . $uriClean, true, 301);
	exit;
}

require '/home/autowp/autowp.ru/application/bs11.php';
exit;