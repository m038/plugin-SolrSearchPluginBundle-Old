<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Services;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\Translator;
use Newscoop\SolrSearchPluginBundle\Exception\SolrException;

/**
 * Helper service for all Solr related actions
 */
class SolrHelperService
{
    const INDEX_ONLY = 'index_only';
    const INDEX_AND_DATA = 'index_and_data';
    const INDEXER_CRON = 'Indexer';

    /**
     * @var Symfony\Component\DependencyInjection\Container
     */
    private $container;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var Symfony\Component\Translation\Translator
     */
    private $translator;

    /**
     * Holds configuration values for Solr
     *
     * @var array
     */
    private $config;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->em = $this->container->get('em');
        $this->translator = $this->container->get('translator');

        $this->config = $this->getConfigValues(true);
    }

    /**
     * Get Solr base url
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return sprintf('http://%s:%s/solr', $this->getConfigValue('host'), $this->getConfigValue('port'));
    }

    /**
     * Get Solr base url with core included
     *
     * @param  string $core Core name, when null, default_core value from configuration is used
     *
     * @return string
     */
    public function getBaseCoreUrl($core = null)
    {
        if ($core === null) {
            $core = $this->getConfigValue('default_core');
        }
        return sprintf('%s/%s', $this->getBaseUrl(), $core);
    }

    /**
     * Get Solr cores status url
     *
     * @return string
     */
    public function getCoresUrl()
    {
        return sprintf('%s/admin/cores?action=STATUS&wt=json&indent=on', $this->getBaseUrl());
    }

    /**
     * Get Solr query url
     *
     * @return string
     */
    public function getQueryUrl($core = null)
    {
        return sprintf('%s/%s', $this->getBaseCoreUrl($core), $this->getConfigValue('query_uri'));
    }

    /**
     * Get Solr update url
     *
     * @return string
     */
    public function getUpdateUrl($core = null)
    {
        return sprintf('%s/%s', $this->getBaseCoreUrl($core), $this->getConfigValue('update_uri'));
    }

    /**
     * Get services registered with the indexer prefix
     *
     * @return array List of services
     */
    public function getIndexables($initService = false)
    {
        $servicIds = $this->container->getServiceIds();
        $indexingServices = array();

        foreach ($servicIds AS $serviceId) {

            if (strpos($serviceId, 'indexer.') === false) {
                continue;
            }

            if ($initService) {
                $indexer = $this->container->get($serviceId);
                $indexingServices[$serviceId] = $indexer;
            } else {
                $indexingServices[] = $serviceId;
            }

        }

        return $indexingServices;
    }

    /**
     * Gets all core names from Solr
     *
     * @return array List of core names
     */
    public function getCoresFromSolr()
    {
        $client = $this->initClient();

        try {
            $response = $client->get($this->getCoresUrl());
        } catch (\Exception $e) {
            throw new SolrException($e->getMessage());
        }

        $body = $response->getContent();
        $bodyAsJson = json_decode($body, true);

        if (count($bodyAsJson['status']) === 0) {
            throw new SolrException($this->translator->trans('plugin.error.solr_core_read'));
        }

        $cores = array_keys($bodyAsJson['status']);

        return $cores;
    }

    /**
     * Get config values
     *
     * @param  boolean $fromDatabase Perform an update from the DB to the config array
     *
     * @return array
     */
    public function getConfigValues($fromDatabase = false)
    {
        if ($fromDatabase) {
            $configValuesDb = $this->em
                ->getRepository('Newscoop\SolrSearchPluginBundle\Entity\SolrConfig')
                ->createQueryBuilder('s')
                ->getQuery()
                ->getArrayResult();

            $configObj = array();
            array_walk($configValuesDb, function($configDb) use (&$configObj) {
                $configObj[$configDb['key']] = unserialize($configDb['value']);
            });

            $this->config = $configObj;
        }

        return $this->config;
    }

    /**
     * Set config value
     *
     * @param string $key   Key of the value
     * @param mixed  $value Actual value
     *
     * @return $this
     */
    public function setConfigValue($key, $value)
    {
        $this->config[$key] = $value;

        return $this;
    }

    /**
     * Get single configuration value from config object
     *
     * @param  string $key     Key of the config value
     * @param  mixed  $default A default value to return, when the key isn't found
     *
     * @return mixed
     */
    public function getConfigValue($key, $default = null)
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return $default;
    }

    public function getTypes()
    {
        $indexables = $this->getIndexables();
        $subTypes = array();
        $removeIndexables = array();

        array_walk($indexables, function ($value, $key) use (&$subTypes, &$removeIndexables) {
            $type = $this->getConfigValue(str_replace('.', '_', $value).'_types', null);
            if (is_array($type)) {
                $subTypes = array_merge($subTypes, $type);
                $removeIndexables[] = $value;
            }
        });

        $indexables = array_filter($indexables, function($value) use ($removeIndexables) {
            return !in_array($value, $removeIndexables);
        });

        array_walk($indexables, function(&$value) {
            $value = str_replace('indexer.', '', $value);
        });

        return array_merge($indexables, $subTypes);
    }

    /**
     * Creates a new Buzz\Browser client
     *
     * @param  integer $timeout Timeout of the request
     *
     * @return Buzz\Browser
     */
    public function initClient($timeout = 60)
    {
        $clientCurl = new \Buzz\Client\Curl();
        $clientCurl->setTimeout($timeout);
        return new \Buzz\Browser($clientCurl);
    }

    /**
     * Get the generic indexer cron job
     *
     * @return Newscoop\Entity\CronJob
     */
    public function getIndexerJob()
    {
        return $this->em->getRepository('Newscoop\Entity\CronJob')->findOneByName(self::INDEXER_CRON);
    }
}
