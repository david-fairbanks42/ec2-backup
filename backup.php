<?php

$arguments = getopt('fph', ['force', 'no-prune', 'help']);
if(isset($arguments['h']) || isset($arguments['help'])) {
    echo <<<hereDoc
Create and rotate EBS snapshots on AWS for EC2 instances.

Usage:
    {$argv[0]} [options]
    
Options:
    -f, --force     Override the ENABLE environment variable
    -p, --no-prune  Prevent the removal of old snapshots

hereDoc;
    exit;
}

$options = [
    'force' => isset($arguments['f']) || isset($arguments['force']),
    'noPrune' => isset($arguments['p']) || isset($arguments['no-prune'])
];

require_once(__DIR__ . '/vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('AWS_KEY')->notEmpty();
$dotenv->required('AWS_SECRET')->notEmpty();
$dotenv->required('AWS_REGION')->notEmpty();
$dotenv->ifPresent('MAX_SNAPSHOT_COUNT')->isInteger();
$dotenv->ifPresent('ENABLE')->isBoolean();

$ec2Client = new \Aws\Ec2\Ec2Client([
    'credentials' => [
        'key' => config('AWS_KEY'),
        'secret' => config('AWS_SECRET')
    ],
    'region' => config('AWS_REGION'),
    'version' => config('AWS_VERSION', 'latest')
]);
$ec2Backup = new \App\Ec2Backup($ec2Client);

$ec2Backup->create();
