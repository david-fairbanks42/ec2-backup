<?php
/**
 * Machine Details for Logging
 *
 * @copyright (c) 2017, Fairbanks Publishing
 * @license Proprietary
 */

namespace App;

/**
 * Class MachineDetails
 *
 * @author David Fairbanks <david@makerdave.com>
 * @version 2.0
 */
class MachineDetails
{
    /**
     * @var MachineDetails
     */
    protected static $instance;

    /**
     * @var array
     */
    protected static $details = [
        'type'       => null,
        'id'         => null,
        'machineId'  => null,
        'instanceId' => null,
        'region'     => null,
        'publicIp'   => null,
        'privateIp'  => null,
    ];

    /**
     * PHP config setting for default_socket_timeout to reset to after doing file_get_contents()
     * @var int
     */
    protected $socketTimeout = null;

    /**
     * @return MachineDetails
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * @return array
     */
    public static function getDetails()
    {
        if(self::$instance === null)
            self::$instance = self::getInstance();

        return self::$details;
    }

    /**
     * @return string|null
     */
    public static function id()
    {
        if(self::$instance === null)
            self::$instance = self::getInstance();

        return self::$details['id'];
    }

    /**
     * @return string|null
     */
    public static function machineId()
    {
        if(self::$instance === null)
            self::$instance = self::getInstance();

        return self::$details['machineId'];
    }

    /**
     * @return string
     */
    public static function fullMachineId()
    {
        if(self::$instance === null)
            self::$instance = self::getInstance();

        $id = [
            config('MACHINE_TYPE'),
            self::$details['region'],
            self::$details['machineId']
        ];

        return implode('-', array_unique(array_filter($id)));
    }

    /**
     * @return string|null
     */
    public static function region()
    {
        if(self::$instance === null)
            self::$instance = self::getInstance();

        return self::$details['region'];
    }

    /**
     * @return string|null
     */
    public static function publicIp()
    {
        if(self::$instance === null)
            self::$instance = self::getInstance();

        if(self::$details['publicIp'] === null) {
            switch(self::$details['type']) {
                case 'ec2' :
                    self::$details['publicIp'] = self::getEc2PublicIp();
                    break;
                default :
                    self::$details['publicIp'] = self::getLocalPublicIp();
            }
        }

        return self::$details['privateIp'];
    }

    /**
     * @return string|null
     */
    public static function privateIp()
    {
        if(self::$instance === null)
            self::$instance = self::getInstance();

        return self::$details['privateIp'];
    }

    /* Private methods */

    private static function getLocalPublicIp()
    {
        return '127.0.0.1';
    }

    private static function getEc2PublicIp()
    {
        if(self::$instance === null)
            self::$instance = self::getInstance();

        self::$instance->shortenTimeout();
        $ip = @file_get_contents('http://169.254.169.254/latest/meta-data/public-ipv4');
        self::$instance->resetTimeout();

        return $ip;
    }

    private function __construct() {
        $type = config('MACHINE_TYPE');
        if($type === null || empty($type)) {
            $type = $this->determineMachineType();
        }

        switch($type) {
            case 'ec2' :
                self::$details['type'] = 'ec2';
                self::$details = array_merge(self::$details, $this->getEc2Data());
                break;
            default :
                self::$details['type'] = 'local';
                self::$details = array_merge(self::$details, $this->getLocalData());
        }
    }

    /**
     * Make clone magic method private, so nobody can clone instance.
     */
    private function __clone() {}

    private function determineMachineType()
    {
        $this->shortenTimeout();
        $hostname = @file_get_contents('http://169.254.169.254/latest/meta-data/hostname');
        $this->resetTimeout();

        if(strpos($hostname, 'ec2') !== false) {
            return 'ec2';
        } else {
            return 'local';
        }
    }

    private function getLocalData()
    {
        return [
            'id'        => 'dev',
            'machineId' => 'dev',
            'region'    => 'local',
            'publicIp'  => '127.0.0.1',
            'privateIp' => '127.0.0.1',
        ];
    }

    private function getEc2Data()
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

        $this->shortenTimeout();
        $json = @file_get_contents('http://169.254.169.254/latest/dynamic/instance-identity/document');
        $this->resetTimeout();

        $data = json_decode($json, true);

        if(!is_array($data) || empty($data)) {
            throw new \Exception('Invalid machine details from http://169.254.169.254. Assumption is this is not an EC2 instance.');
        }

        if(isset($data['instanceId']))
            $data['machineId'] = substr($data['instanceId'], 2);

        $out = [];
        foreach(self::$details as $key => $v) {
            $out[$key] = (isset($data[$key])) ? $data[$key] : $v;
        }

        return $out;
    }

    private function shortenTimeout()
    {
        if($this->socketTimeout === null) {
            $this->socketTimeout = ini_get('default_socket_timeout');
        }

        ini_set('default_socket_timeout', 2);
    }

    private function resetTimeout()
    {
        if($this->socketTimeout !== null) {
            ini_set('default_socket_timeout', $this->socketTimeout);
        }
    }
}
