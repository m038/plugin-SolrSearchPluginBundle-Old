<?php
/**
 * @package Newscoop\TagesWocheMobilePluginBundle
 * @author Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\EventListener;

use Newscoop\SolrSearchPluginBundle\Services\SolrQueryService;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;

class SolrQueryRequestListener
{
    /**
     * Solr query service
     *
     * @var Newscoop\SolrSearchPluginBundle\Services\SolrQueryService
     */
    protected $queryService;

    /**
     * @param Newscoop\SolrSearchPluginBundle\Services\SolrQueryService $queryService
     */
    public function __construct(SolrQueryService $queryService)
    {
        $this->queryService = $queryService;
    }

    /**
     * Request event handler
     *
     * @param  Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     */
    public function onRequest(GetResponseEvent $event)
    {
        if (HttpKernel::MASTER_REQUEST != $event->getRequestType()) {
            // don't do anything if it's not the master request
            return;
        }

        $request = $event->getRequest();

        $this->queryService->setRequest($request);
    }
}
