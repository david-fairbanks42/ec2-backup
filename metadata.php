<?php

require_once(__DIR__ . '/vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('AWS_KEY')->notEmpty();
$dotenv->required('AWS_SECRET')->notEmpty();
$dotenv->required('AWS_REGION')->notEmpty();

$machine = App\MachineDetails::getDetails();
echo json_encode($machine, JSON_PRETTY_PRINT) . PHP_EOL;
