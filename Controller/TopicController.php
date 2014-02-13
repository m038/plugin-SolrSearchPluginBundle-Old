<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\Query\ResultSetMapping;
use Exception;

class TopicController extends Controller
{
    /**
     * @var array
     */
    protected $sources = array(
        'news', 'newswire', 'blog',
    );

    /**
     * @Route("/themen/{theme_name}/", name="topic")
     * @Route("/{language}/themen/{theme_name}/", name="topic_lang")
     */
    public function topicAction(Request $request, $theme_name, $language = null)
    {
        $em = $this->container->get('em');

        $language = $em->getRepository('Newscoop\Entity\Language')
            ->findOneByCode($language);

        if ($language === null) {
            $language = $this->container->get('em')
                ->getRepository('Newscoop\Entity\Language')
                ->findByRFC3066bis('de-DE', true);
            if ($language == null) {
                throw new NewscoopException('Could not find default language.');
            }
        }

        try {
            $topic = $em->getRepository('Newscoop\Entity\Topic')->findOneBy(array(
                'name' => $theme_name,
            ));
            if ($topic === null) {
                return $this->redirect($this->generateUrl('topic_error'));
            }
        } catch (Exception $e) {
            throw new NewscoopException("Could not find topic.");
        }

        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');
        $parameters = $request->query->all();
        if (!array_key_exists('topic', $parameters)) {
            $parameters['topic'] = $theme_name;
        }

        $solrParameters = $this->encodeParameters($parameters);
        $solrParameters['core-language'] = $language->getRFC3066bis();

        $solrResponseBody = $queryService->find($solrParameters);

        if (!array_key_exists('format', $parameters)) {

            $topicData = (object) array(
                'id' => $topic->getTopicId(),
                'name' => $topic->getName(),
            );

            $templatesService = $this->container->get('newscoop.templates.service');
            $smarty = $templatesService->getSmarty();
            $smarty->assign('result', json_encode($solrResponseBody));
            $smarty->assign('topic', $topicData);

            $response = new Response();
            $response->setContent($templatesService->fetchTemplate("_views/topic_index.tpl"));
        } elseif ($parameters['format'] === 'xml') {

            try {
                foreach ($solrResponseBody['response']['docs'] AS &$doc) {
                    $doc['link_url'] = $doc['link'];
                }
            } catch (Exception $e) {
                // Array is it as expected
            }

            $templatesService = $this->container->get('newscoop.templates.service');
            $smarty = $templatesService->getSmarty();
            $smarty->assign('result', $solrResponseBody);

            $response = new Response();
            $response->headers->set('Content-Type', 'application/rss+xml');
            $response->setContent($templatesService->fetchTemplate("_views/topic_xml.tpl"));
        } elseif ($parameters['format'] === 'json') {

            $response = new JsonResponse($solrResponseBody);
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
            'q' => $this->buildSolrTopicParam($parameters),
            'fq' => implode(' AND ', array_filter(array(
                $this->buildSolrSourceParam($parameters),
                $queryService->buildSolrDateParam($parameters),
            ))),
            'sort' => 'published desc',
            'spellcheck' => 'false',
            'rows' => (array_key_exists('xml', $parameters) && $parameters['xml'] === 'xml')
                 ? 20 : 12,
        ));
    }

    /**
     * Build solr source filter
     *
     * @return string
     */
    private function buildSolrSourceParam(array $parameters)
    {
        if (array_key_exists('sources', $parameters)) {
            $sources = (is_array($parameters['sources'])) ? $parameters['sources'] : array($parameters['sources']);
            return sprintf('type:(%s)', implode(' OR ', $sources));
        }
        return;
    }

    /**
     * Build solr topic filter
     *
     * @return string
     */
    private function buildSolrTopicParam(array $parameters)
    {
        if (array_key_exists('topic', $parameters)) {
            return sprintf('topic:(%s)', trim($parameters['topic']));
        }
        return;
    }
}
