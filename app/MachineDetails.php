<?php
/**
 * MachineDetails.php
 *
 * @copyright 2025 Fairbanks Publishing LLC
 */

namespace App;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;

/**
 * Class MachineDetails
 *
 * @author David Fairbanks <david@makerdave.com>
 * @version 3.0
 */
class MachineDetails
{
    protected static MachineDetails $instance;

    protected string|null $type = null;
    protected string|null $machineId = null;
    protected string|null $instanceId = null;
    protected string|null $region = null;
    protected string|null $privateIp = null;
    protected string|null $publicIp = null;

    private Client|null $metadataServiceClient = null;
    private string|null $metadataServiceToken = null;
    private CarbonInterface|null $metadataServiceTokenDate = null;

    public static function getInstance(): MachineDetails
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public static function getDetails(): object
    {
        if(! isset(self::$instance))
            self::$instance = self::getInstance();

        return self::$instance->get();
    }

    public function get(): object
    {
        return (object) [
            'type' => $this->type,
            'machineId' => $this->machineId,
            'instanceId' => $this->instanceId,
            'region' => $this->region,
            'privateIp' => $this->privateIp,
            'publicIp' => $this->publicIp,
        ];
    }

    public function publicIp(): string|null
    {
        if (! empty($this->publicIp)) {
            return $this->publicIp;
        }

        $this->ensureMetadataService();
        if (empty($this->metadataServiceToken)) {
            return null;
        }

        try {
            $response = $this->metadataServiceClient->request(
                'GET',
                'meta-data/public-ipv4',
                [
                    'headers' => [
                        'X-aws-ec2-metadata-token' => $this->metadataServiceToken,
                    ],
                ]
            );

            $this->publicIp = $response->getBody()->getContents();
        } catch (GuzzleException|Exception $e) {
            app_echo(get_class($e) . ' getting IMDS V2 instance details: ' . $e->getMessage());
        }

        return $this->publicIp;
    }

    private function __construct() {
        if ($this->isEc2()) {
            $this->type = 'ec2';
            $this->instanceIdentity();
        } else {
            $this->type = 'unknown';
        }
    }

    private function isEc2(): bool
    {
        $uname = php_uname();

        return preg_match('/ip-\d{2,3}-\d{1,3}-\d{1,3}-\d{1,3}\s.*-aws/', $uname);
    }

    private function __clone() {}

    private function instanceIdentity(): void
    {
        /*
         * curl "http://169.254.169.254/latest/dynamic/instance-identity/document"
         * {
         *    "privateIp" : "172.31.59.103",
         *    "devpayProductCodes" : null,
         *    "availabilityZone" : "us-east-1b",
         *    "version" : "2010-08-31",
         *    "instanceId" : "i-0a1233...",
         *    "billingProducts" : null,
         *    "pendingTime" : "2017-05-12T15:21:57Z",
         *    "instanceType" : "t2.micro",
         *    "accountId" : "393...",
         *    "architecture" : "x86_64",
         *    "kernelId" : null,
         *    "ramdiskId" : null,
         *    "imageId" : "ami-9a...",
         *    "region" : "us-east-1"
         *  }
         */

        $this->ensureMetadataService();
        if (empty($this->metadataServiceToken)) {
            return;
        }

        try {
            $response = $this->metadataServiceClient->request(
                'GET',
                'dynamic/instance-identity/document',
                [
                    'headers' => [
                        'X-aws-ec2-metadata-token' => $this->metadataServiceToken,
                    ],
                ]
            );

            $json = $response->getBody()->getContents();
        } catch (GuzzleException|Exception $e) {
            app_echo(get_class($e) . ' getting IMDS V2 instance details: ' . $e->getMessage());

            return;
        }

        $data = json_decode($json);

        $this->instanceId = $data->instanceId;
        $this->region = $data->region;
        $this->privateIp = $data->privateIp;

        if (! empty($data->instanceId)) {
            $this->machineId = substr($data->instanceId, 2);
        }
    }

    private function ensureMetadataService(): void
    {
        if ($this->metadataServiceClient === null) {
            $this->metadataServiceClient = new Client(['base_uri' => 'http://169.254.169.254/latest/']);
        }

        if ($this->metadataServiceToken !== null && $this->metadataServiceTokenDate !== null
            && $this->metadataServiceTokenDate->isAfter(Carbon::now()->addSeconds(-60))
        ) {
            return;
        }

        $this->metadataServiceToken = null;
        $this->metadataServiceTokenDate = Carbon::now();

        // https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instancedata-dynamic-data-retrieval.html
        // https://stackoverflow.com/a/74334921/667613
        // TOKEN=`curl -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600"`
        // curl -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/dynamic/instance-identity/document

        try {
            $response = $this->metadataServiceClient->request(
                'PUT',
                'api/token',
                [
                    'headers' => [
                        'X-aws-ec2-metadata-token-ttl-seconds' => 60,
                    ],
                ]
            );

            $this->metadataServiceToken = $response->getBody()->getContents();
        } catch (GuzzleException|Exception $e) {
            app_echo(get_class($e) . ' getting IMDS V2 token: ' . $e->getMessage());
        }
    }
}
