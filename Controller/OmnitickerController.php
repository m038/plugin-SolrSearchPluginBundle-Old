<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class OmnitickerController extends Controller
{
    /**
     * @var array
     */
    private $sources = array(
        'tageswoche' => array('news', 'dossier', 'blog'),
        'twitter' => 'tweet',
        'agentur' => 'newswire',
        'link' => 'link',
        'en' => array('newswire'),
    );

    /**
     * @Route("/omniticker/")
     */
    public function omnitickerAction(Request $request)
    {
        if ($this->container->get('webcode')->findArticleByWebcode($request->query->get('q')) !== null) {

            $this->_helper->redirector->setCode(302);
            $this->_helper->redirector->gotoRoute(array(
                'webcode' => $request->get('q'),
            ), 'webcode', false, false);
        }

        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');
        $parameters = $request->query->all();

        if (array_key_exists('format', $parameters)) {

            $solrParameters = $this->encodeParameters($parameters);
            $response = $queryService->find($solrParameters);
        } else {

            $templatesService = $this->container->get('newscoop.templates.service');

            $response = new Response();
            $smarty = $templatesService->getSmarty();
            $response->setContent($templatesService->fetchTemplate("_views/omniticker_index.tpl"));
        }

        return $response;
    }

    /**
     * Build solr params array
     *
     * @return array
     */
    protected function encodeParameters(array $parameters)
    {
        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');

        return array_merge($queryService->encodeParameters($parameters), array(
            'q' => '*:*',
            'fq' => implode(' AND ', array_filter(array(
                $this->buildSolrSectionParam($parameters),
                $this->buildSolrSourceParam($parameters),
                $queryService->buildSolrDateParam($parameters),
                (array_key_exists('source', $parameters) && $parameters['source'] === 'en') ? 'section:swissinfo' : null,
                '-switches:print',
            ))),
            'sort' => 'published desc',
            'spellcheck' => 'false',
        ));
    }

    /**
     * Build solr source filter
     *
     * @return string
     */
    private function buildSolrSourceParam($parameters)
    {
        $source = (array_key_exists('source', $parameters)) ? $parameters['source'] : null;

        if (!empty($source) && array_key_exists($source, $this->sources)) {
            $sources = (array) $this->sources[$source];
        } else {
            $sources = array();
            foreach ($this->sources as $types) {
                $sources = array_merge($sources, (array) $types);
            }
        }

        return sprintf('type:(%s)', implode(' OR ', array_unique($sources)));
    }

    /**
     * Build solr section filter
     *
     * @return string
     */
    private function buildSolrSectionParam($parameters)
    {
        $section = (array_key_exists('section', $parameters)) ? $parameters['section'] : null;
        if ($section !== null) {
            return sprintf('section:("%s")', json_encode($section));
        }
    }
}
