<?php
/**
 * ScandiPWA_PersistedQuery
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery;


use Magento\Framework\App\DeploymentConfig;

/**
 * Class RedisClient
 *
 * @package ScandiPWA\PersistentQuery
 */
class RedisClient
{

    protected const PERSISTENT_QUERY_CONFIG = 'cache/persisted-query';

    protected const TTL_QUERY_PREFIX = '_ttl';

    protected const DEFAULT_HOST = 'redis';
    protected const DEFAULT_PORT = '6379';
    protected const DEFAULT_SCHEME = 'tcp';
    protected const DEFAULT_DB = '5';

    /**
     * @var mixed|null
     */
    private $cacheConfig;

    /**
     * @var \Credis_Client
     */
    private $client;

    /**
     * @var string
     */
    private $redisClientClass;
    /**
     * @var DeploymentConfig
     */
    private $config;

    /**
     * RedisClient constructor.
     *
     * @param DeploymentConfig $config
     * @param string $redisClientClass
     * @throws \Exception
     */
    public function __construct(
        DeploymentConfig $config,
        string $redisClientClass = \Credis_Client::class
    ) {
        $this->cacheConfig = $config->get(self::PERSISTENT_QUERY_CONFIG);
        // Setting up default redis cache configs, if they arent set in app/etc/env.php
        if (!$this->cacheConfig['redis']) {
            $this->cacheConfig['redis'] = [
                'host' => self::DEFAULT_HOST,
                'port' => self::DEFAULT_PORT,
                'scheme' => self::DEFAULT_SCHEME,
                'database' => self::DEFAULT_DB
            ];
        }
        $this->redisClientClass = $redisClientClass;
        $this->client = $this->redisClientFactory($this->cacheConfig['redis']);
        $this->config = $config;
        $this->checkPersistentQueriesConfig();
    }

    /**
     * Checks if persistent config is configured
     * But only when Magento is operational
     *
     * @throws \Exception
     */
    protected function checkPersistentQueriesConfig()
    {
        if (!$this->configExists() && $this->config->isAvailable()) {
            throw new \Exception('Redis is not configured for persistent queries');
        }
    }


    /**
     * @param array $redisConfig
     * @return \Credis_Client
     */
    private function redisClientFactory(array $redisConfig): \Credis_Client
    {
        return new $this->redisClientClass(
            $redisConfig['host'],
            $redisConfig['port'],
            10,
            '',
            $redisConfig['database']
        );
    }

    /**
     * @param string $hash
     * @param string $query
     * @return bool
     */
    public function updatePersistentQuery(string $hash, string $query): bool
    {
        return $this->client->set($hash, $query);
    }

    /**
     * @return bool
     */
    private function configExists()
    {
        return (($this->cacheConfig !== null) && array_key_exists('redis', $this->cacheConfig));
    }

    /**
     * @param string $hash
     * @return mixed
     */
    public function getPersistentQuery(string $hash)
    {
        return $this->client->get($hash);
    }

    /**
     * @param string $hash
     * @return int
     */
    public function queryExists(string $hash)
    {
        return $this->client->exists($hash);
    }

    /**
     * @param string $hash
     * @param int    $ttl
     * @return bool
     */
    public function setQueryTTL(string $hash, int $ttl): bool
    {
        $hash .= self::TTL_QUERY_PREFIX;
        return $this->client->set($hash, $ttl);
    }

    /**
     * @param string $hash
     * @return string
     */
    public function getQueryTTL(string $hash)
    {
        $hash .= self::TTL_QUERY_PREFIX;
        return $this->client->get($hash);
    }

    public function flushDb()
    {
        return $this->client->flushdb();
    }
}
