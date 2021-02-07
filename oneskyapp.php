#!/usr/bin/env php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Onesky\Api\Client;
use Zend\Json\Json;

if (! isset($argv[1]) || ! $argv[1]) {
    throw new \Exception("Secret not provided");
}

$languages = ['en', 'zh', 'ru', 'pt-br', 'fr', 'be', 'uk', 'es'];
$projectID = 129670;

$client = new Client();

$client
    ->setApiKey("2a1C12oZU5VIK409AJd0xUfVntGyhLWa")
    ->setSecret($argv[1]);

$backendDir = realpath(__DIR__ . '/module/Application/language');

foreach ($languages as $language) {
    $response = $client->translations('export', [
        'project_id'       => $projectID,
        'locale'           => $language,
        'source_file_name' => 'frontend.json'
    ]);

    $data = Json::decode($response, Json::TYPE_ARRAY);

    $content = '<?php return ' . var_export($data, true) . ';';

    $filepath = $backendDir . '/' . $language . '.php';

    print $filepath . "\n";

    file_put_contents($filepath, $content);
}

return "done\n";
