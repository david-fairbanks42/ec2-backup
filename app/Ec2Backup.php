<?php
/**
 * Ec2Backup.php
 *
 * @copyright 2025 Fairbanks Publishing LLC
 */

namespace App;

use Aws\Ec2\Ec2Client;

/**
 * Class Ec2Backup
 *
 * @author David Fairbanks <david@makerdave.com>
 * @version 3.0
 */
class Ec2Backup
{
    private Ec2Client $ec2Client;
    private array $options = [
        'noPrune' => false,
        'force' => false,
        'dryRun' => false,
    ];
    private bool $enable = true;

    /**
     * Number of completed backups to keep
     * This is the number BEFORE a backup is started
     * @var int
     */
    private int $maxBackupCount = 4;

    public function __construct(Ec2Client $ec2Client, array $options = [])
    {
        $this->ec2Client = $ec2Client;
        foreach ($this->options as $key => $default) {
            if (array_key_exists($key, $options) && is_bool($options[$key])) {
                $this->options[$key] = $options[$key];
            }
        }

        $this->enable = boolean(config('ENABLE', true));

        $this->maxBackupCount = (int) config('MAX_SNAPSHOT_COUNT', 4);
        if ($this->maxBackupCount <= 0) {
            $this->maxBackupCount = 4;
        }
    }

    public function create(): void
    {
        if (! $this->enable && ! $this->options['force']) {
            app_echo('EC2 Backup is disabled in environment');
            return;
        }

        /*
         * is this an ec2 instance
         * get volume ID
         * get list of snapshots
         * prune old snapshots
         * create new snapshot
         */

        try {
            $machine = MachineDetails::getDetails();
        } catch (\Exception $e) {
            app_echo("Error getting machine details: {$e->getMessage()}");
            return;
        }

        if ($machine->type != 'ec2') {
            app_echo('Instance type is wrong to do an EC2 backup', (array) $machine);
            return;
        }

        $volumes = $this->getVolumes($machine->instanceId);
        $tags = $this->getTags($machine->instanceId);

        foreach ($volumes as $volume) {
            if ($this->options['noPrune']) {
                $pruneWording = '(pruning disabled)';
            } else {
                $pruneCount = $this->pruneBackups($volume['volumeId']);
                $pruneWording = "and pruned {$pruneCount} old snapshots";
            }

            $name = (isset($tags['Name'])) ? $tags['Name'] : $machine->instanceId;
            if (count($volumes) > 1) {
                $name .= " ({$volume['device']})";
            }

            if ($this->backup(['volumeId' => $volume['volumeId'], 'name' => $name])) {
                app_echo("Successfully started snapshot for {$volume['volumeId']} {$pruneWording}");
            } else {
                app_echo("Error starting snapshot for {$volume['volumeId']} {$pruneWording}");
            }
        }
    }

    public function getVolumes($instanceId): array
    {
        try {
            $result = $this->ec2Client->describeVolumes(
                [
                    'DryRun' => false,
                    'Filters' => [
                        [
                            'Name' => 'attachment.instance-id',
                            'Values' => [$instanceId]
                        ]
                    ]
                ]
            );
        } catch (\Exception $e) {
            app_echo('Error getting volumes: ' . $e->getMessage());
            return [];
        }

        $volumes = [];
        foreach ($result['Volumes'] as $volume) {
            $volumes[$volume['VolumeId']] = [
                'device' => $volume['Attachments'][0]['Device'],
                'volumeId' => $volume['VolumeId']
            ];
        }

        return $volumes;
    }

    public function getTags($instanceId): array
    {
        try {
            $result = $this->ec2Client->describeTags(
                [
                    'DryRun' => false,
                    'Filters' => [
                        [
                            'Name' => 'resource-id',
                            'Values' => [$instanceId]
                        ]
                    ]
                ]
            );
        } catch (\Exception $e) {
            app_echo('Error getting instance tags: ' . $e->getMessage());
            return [];
        }

        $tags = [];
        foreach ($result['Tags'] as $tag) {
            $tags[$tag['Key']] = $tag['Value'];
        }

        return $tags;
    }

    public function getBackups($volumeId): array
    {
        try {
            $result = $this->ec2Client->describeSnapshots(
                [
                    'DryRun' => false,
                    'Filters' => [
                        [
                            'Name' => 'volume-id',
                            'Values' => [$volumeId],
                        ]
                    ]
                ]
            );
        } catch (\Exception $e) {
            app_echo('Error getting current snapshots: ' . $e->getMessage());
            return [];
        }

        $snapshots = [];
        foreach ($result['Snapshots'] as $snapshot) {
            if ($snapshot['State'] != 'completed') {
                continue;
            }

            $snapshots[$snapshot['SnapshotId']] = [
                'started' => Dates::makeCarbon($snapshot['StartTime']),
                'description' => $snapshot['Description'],
                'snapshotId' => $snapshot['SnapshotId'],
            ];
        }

        uasort($snapshots, function ($a, $b) {
            if ($a['started'] == $b['started']) {
                return 0;
            } else {
                return $a['started'] > $b['started'] ? 1 : 0;
            }
        });

        return $snapshots;
    }

    public function pruneBackups($volumeId): int
    {
        $backups = $this->getBackups($volumeId);

        if (count($backups) <= $this->maxBackupCount) {
            return 0;
        }

        $prune = array_slice($backups, 0, count($backups) - $this->maxBackupCount);
        if (empty($prune)) {
            return 0;
        }

        $count = 0;
        foreach ($prune as $snapshotId => $snapshot) {
            try {
                $this->ec2Client->deleteSnapshot(
                    [
                        'DryRun' => $this->options['dryRun'],
                        'SnapshotId' => $snapshotId
                    ]
                );
            } catch (\Exception $e) {
                app_echo('Error pruning snapshot: ' . $e->getMessage());
                continue;
            }

            $count++;
        }

        return $count;
    }

    public function backup(array $params): bool
    {
        if (! isset($params['volumeId'])) {
            app_echo('Volume ID is not set in backup parameters');
            return false;
        }

        $name = (isset($params['name'])) ? $params['name'] : $params['volumeId'];

        $tags = [['Key' => 'Name', 'Value' => $name]];
        $snapTags = json_decode(config('TAGS', '[]'), true);
        if (is_array($snapTags) && ! empty($snapTags)) {
            foreach ($snapTags as $key => $value) {
                $tags[] = ['Key' => $key, 'Value' => $value];
            }
        }

        try {
            $this->ec2Client->createSnapshot(
                [
                    'Description' => sprintf('%s Backup %s', $name, date('Y-m-d')),
                    'DryRun' => $this->options['dryRun'],
                    'TagSpecifications' => [
                        [
                            'ResourceType' => 'snapshot',
                            'Tags' => $tags
                        ]
                    ],
                    'VolumeId' => $params['volumeId']
                ]
            );
        } catch (\Exception $e) {
            app_echo($e->getMessage());
            return false;
        }

        return true;
    }
}
