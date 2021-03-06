<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Protocol\Ldap;

use Icinga\Data\ConfigObject;
use Icinga\Protocol\Dns;

class Discovery {

    /**
     * @var Connection
     */
    private $connection;

    /**
     * If discovery was already performed
     *
     * @var bool
     */
    private $discovered = false;

    /**
     * @param Connection $conn  The ldap connection to use for the discovery
     */
    public function __construct(Connection $conn)
    {
        $this->connection = $conn;
    }

    /**
     * Execute the discovery on the underlying connection
     */
    private function execDiscovery()
    {
        if (! $this->discovered) {
            $this->connection->connect();
            $this->discovered = true;
        }
    }

    /**
     * Suggests a resource configuration of hostname, port and root_dn
     * based on the discovery
     *
     * @return array    The suggested configuration as an array
     */
    public function suggestResourceSettings()
    {
        if (! $this->discovered) {
            $this->execDiscovery();
        }

        return array(
            'hostname' => $this->connection->getHostname(),
            'port' => $this->connection->getPort(),
            'root_dn' => $this->connection->getDefaultNamingContext()
        );
    }

    /**
     * Suggests a backend configuration of base_dn, user_class and user_name_attribute
     * based on the discovery
     *
     * @return array    The suggested configuration as an array
     */
    public function suggestBackendSettings()
    {
        $this->execDiscovery();
        if ($this->isAd()) {
            return array(
                'base_dn' => $this->connection->getDefaultNamingContext(),
                'user_class' => 'user',
                'user_name_attribute' => 'sAMAccountName'
            );
        } else {
            return array(
                'base_dn' => $this->connection->getDefaultNamingContext(),
                'user_class' => 'getDefaultNamingContext',
                'user_name_attribute' => 'uid'
            );
        }
    }

    /**
     * Whether the suggested ldap server is an ActiveDirectory
     *
     * @return boolean
     */
    public function isAd()
    {
        $this->execDiscovery();
        $caps = $this->connection->getCapabilities();
        return isset($caps->msCapabilities->ActiveDirectoryOid) && $caps->msCapabilities->ActiveDirectoryOid;
    }

    /**
     * Whether the discovery was successful
     *
     * @return bool     False when the suggestions are guessed
     */
    public function isSuccess()
    {
        $this->execDiscovery();
        return $this->connection->discoverySuccessful();
    }

    /**
     * Discover LDAP servers on the given domain
     *
     * @param  string   $domain The object containing the form elements
     *
     * @return Discovery        True when the discovery was successful, false when the configuration was guessed
     */
    public static function discoverDomain($domain)
    {
        if (! isset($domain)) {
            return false;
        }

        // Attempt 1: Connect to the domain directly
        $disc = Discovery::discover($domain, 389);
        if ($disc->isSuccess()) {
            return $disc;
        }

        // Attempt 2: Discover all available ldap dns records and connect to the first one
        $records = array_merge(Dns::getSrvRecords($domain, 'ldap'), Dns::getSrvRecords($domain, 'ldaps'));
        if (isset($records[0])) {
            $record = $records[0];
            return Discovery::discover(
                isset($record['target']) ? $record['target'] : $domain,
                isset($record['port'])   ? $record['port'] : $domain
            );
        }

        // Return the first failed discovery, which will suggest properties based on guesses
        return $disc;
    }

    /**
     * Convenience method to instantiate a new Discovery
     *
     * @param $host         The host on which to execute the discovery
     * @param $port         The port on which to execute the discovery
     *
     * @return Discover     The resulting Discovery
     */
    public static function discover($host, $port)
    {
        $conn = new Connection(new ConfigObject(array(
            'hostname' => $host,
            'port'     => $port
        )));
        return new Discovery($conn);
    }
} 