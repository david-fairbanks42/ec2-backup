# Automatic EC2 Volume Back-Up (Snapshot)

Stand-alone script designed to run via cron or systemd to back-up EC2 volumes on a regular schedule.

All volumes associated with the EC2 instance are backed up.

This script does not freeze the volume or a database or wait for inactivity. It just creates snapshots. High traffic
servers should not use this as their only means of backup since there is a chance the database could be mid-write
during the snapshot start which will break the database.

## Usage
After the required configuration settings are set up in the `.env` file, simply executing `php backup.php` will
perform the backup. The script has additional options such as `--no-prune` to prevent the script from removing old
snapshots. If the configuration setting `ENABLE` is set to `false` the script will not perform any actions. To override
the `ENABLE` setting, `--force` can be used. The script will also respond to `--help` to outline the usage.

## Setup
Copy the repository (via `git clone` or upload files) to your server. My recommendation is to install in your home
directory (ec2-user or ubuntu).

1. From this directory, run `composer install`
2. Copy (or rename/move) the `.env.example` file to the same directory
3. Add the required AWS credentials (see below) to the `.env` file
4. Modify the `TAGS` value in `.env`

You will want to set up this script to run on a regular schedule. For instance, I run this every day. Examples using
Crontab and SystemD are outlined below.

### Requires IAM permission
It is **STRONGLY** recommended to create an IAM use specifically for programmatic use. The script requires
AWS credentials to be stored in a hidden file.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor0",
            "Effect": "Allow",
            "Action": [
                "ec2:DescribeVolumeStatus",
                "ec2:DeleteSnapshot",
                "ec2:DescribeTags",
                "ec2:DescribeSnapshotAttribute",
                "ec2:DescribeVolumes",
                "ec2:CreateSnapshot",
                "ec2:CreateTags",
                "ec2:DescribeSnapshots",
                "ec2:DescribeVolumeAttribute"
            ],
            "Resource": "*"
        }
    ]
}
```

### Schedule Execution via Cron
The following example will run the backup script every day at 3am UTC (server time) and send script outputs
to `backup.log` in the home directory.

Be sure to change the path to the script and your log file.

`0 3 * * * /usr/bin/env php /home/ubuntu/backup/backup.php >> /home/ubuntu/backup.log 2>&1`

### Schedule Execution via SystemD
The following example will run the backup script every day at 3am UTC (server time). Script outputs are handled
directly by SystemD and are available through `journalctl`.

Create the two following files (requires root permission). Be sure to change the path to the script.

/etc/systemd/system/ec2-backup.service
```unit file (systemd)
[Unit]
Description=Create and rotate EBS snapshots on AWS for EC2 instances

[Service]
Type=oneshot
ExecStart=/usr/bin/env php /home/ubuntu/backup/backup.php
User=ubuntu
```

/etc/systemd/system/ec2-backup.timer
```unit file (systemd)
[Unit]
Description=Run ec2-backup.service every day

[Timer]
OnCalendar=03:00
AccuracySec=1h

[Install]
WantedBy=timers.target
```

To activate the schedule, execute the command (noting the sudo use)

`sudo systemctl start ec2-backup.timer`
