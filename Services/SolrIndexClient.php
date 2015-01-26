<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Services;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Translation\Translator;
use Buzz\Exception\RequestException;
use Newscoop\SolrSearchPluginBundle\Exception\SolrException;
use Newscoop\SolrSearchPluginBundle\Services\SolrHelperService;
use Newscoop\Search\IndexClientInterface;
use Newscoop\Search\ServiceInterface;
use Newscoop\Search\DocumentInterface;

/**
 * Index
 */
class SolrIndexClient implements IndexClientInterface
{
    const APPLICATION_JSON = 'application/json';

    /**
     * @var Newscoop\SolrSearchPluginBundle\Service\SolrHelperService
     */
    private $helper;

    /**
     * @var Symfony\Component\Translation\Translator
     */
    private $translator;

    /**
     * Indexable item
     *
     * @var Newscoop\Search\DocumentInterface
     */
    private $item;

    /**
     * Newscoop service interface
     *
     * @var Newscoop\Search\ServiceInterface
     */
    private $service;

    /**
     * @var array
     */
    private $cores;

    /**
     * @var Buzz\Browser
     */
    private $client;

    /**
     * @var array
     */
    private $add = array();

    /**
     * @var array
     */
    private $delete = array();

    public function __construct(Container $container)
    {
        $this->helper = $container->get('newscoop_solrsearch_plugin.helper');
        $this->translator = $container->get('translator');

        $this->cores = $this->helper->getCoresFromSolr();
        $this->client = $this->helper->initClient();

        $this->initCommands();
    }

    /**
     * Add document
     *
     * @param array $doc
     * @return void
     */
    public function add(array $doc)
    {
        $cores = $this->getCoreFromItem();

        if (is_array($cores)) {
            foreach ($cores AS $core) {
                $this->add[$core][] = $doc;
            }
        } else {
            $this->add[$cores][] = $doc;
        }

        return true;
    }

    /**
     * Update document, not different then add for Solr
     *
     * @param  array  $doc
     * @return void
     */
    public function update(array $doc)
    {
        $this->add($doc);
    }

    /**
     * Delete document from index
     *
     * @param string $documentId
     * @return void
     */
    public function delete($documentId)
    {
        $cores = $this->getCoreFromItem();
        if (is_array($cores)) {
            foreach ($cores AS $core) {
                $this->delete[$core][] = $documentId;
            }
        } else {
            $this->delete[$cores][] = $documentId;
        }

        return true;
    }

    /**
     * Delete all docs
     *
     * @return void
     */
    public function deleteAll()
    {
        foreach ($this->cores as $core) {

            $url = $this->helper->getUpdateUrl($core);

            try {
                $response = $this->client->post(
                    $url,
                    array('Content-Type: '.self::APPLICATION_JSON),
                    '{"delete": { "query":"*:*" }}'
                );
            } catch(RequestException $e) {
                throw new SolrException($this->translator->trans('plugin.error.curl') .' ('. $e->getMessage() .')');
            }

            if (!$response->isSuccessful()) {
                throw new SolrException($this->translator->trans('plugin.error.response_false'));
            }
        }

        return true;
    }

    /**
     * Flush changes
     *
     * @return void
     */
    public function flush()
    {
        foreach ($this->cores as $core) {

            $commands = array_merge($this->buildAddCommands($core), $this->buildDeleteCommands($core));

            if (empty($commands)) {
                continue;
            }

            $url = $this->helper->getUpdateUrl($core);

            try {
                $response = $this->client->post(
                    $url,
                    array('Content-Type: '.self::APPLICATION_JSON),
                    '{'.implode(',', $commands).'}'
                );
            } catch(RequestException $e) {
                throw new SolrException($this->translator->trans('plugin.error.curl') .' ('. $e->getMessage() .')');
            }

            if (!$response->isSuccessful()) {
                throw new SolrException($this->translator->trans('plugin.error.response_false') . ' ('.$response->getStatusCode().')');
            }
        }

        $this->initCommands();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled($serviceName)
    {
        return in_array($serviceName, $this->helper->getConfigValue('indexables'));
    }

    /**
     * {@inheritdoc}
     */
    public function isTypeIndexable($serviceName, $subType)
    {
        $subTypes = $this->helper->getConfigValue(str_replace('.', '_', $serviceName).'_types', true);
        if (is_array($subTypes)) {
            return in_array($subType, $this->helper->getConfigValue(str_replace('.', '_', $serviceName).'_types'));
        }
        return true;
    }

    /**
     * Set service for
     *
     * @param ServiceInterface $service
     */
    public function setService(ServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Set item. This method gives the possibility for the indexing client
     * to access extra data in regards to the default indexable content;
     *
     * @param DocumentInterface $item
     */
    public function setItem(DocumentInterface $item)
    {
        $this->item = $item;
    }

    /**
     * Tries to get codename (language code) based on the item language if
     * not possible to identify language, falls back to all cores.
     *
     * @return mixed Can be string with one core name or array with multiple.
     */
    private function getCoreFromItem()
    {
        if (method_exists($this->item, 'getLanguage')) {
            $core =  $this->item->getLanguage()->getRFC3066bis();
            if (in_array($core, $this->cores)) {
                return $core;
            } else {
                throw new SolrException($this->translator->trans('plugin.error.solr_core_item'));
            }
        } else {
            // Return all cores
            return $this->cores;
        }
    }

    /**
     * Build add commands
     *
     * @return array
     */
    private function buildAddCommands($core)
    {
        if (count($this->add[$core]) === 0) {
            return array();
        }

        return array(sprintf('"add":%s', json_encode($this->add[$core])));
    }

    /**
     * Build delete commands
     *
     * @return array
     */
    private function buildDeleteCommands($core)
    {
        if (count($this->delete[$core]) === 0) {
            return array();
        }

        $commands = array();
        foreach ($this->delete[$core] as $id) {
            $commands[] = sprintf('"delete":%s', json_encode(array('id' => $id)));
        }

        return $commands;
    }

    /**
     * Initialize variables holding commands,
     */
    private function initCommands()
    {
        foreach ($this->cores AS $core) {
            $this->add[$core] = array();
            $this->delete[$core] = array();
        }
    }
}
