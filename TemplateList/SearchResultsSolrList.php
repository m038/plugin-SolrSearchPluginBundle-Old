<?php
/**
 * @package Newscoop\SolrSearchPluginBundle
 * @author Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\TemplateList;

use Newscoop\ListResult;
use Newscoop\TemplateList\PaginatedBaseList;
use Newscoop\SolrSearchPluginBundle\Search\SolrQuery;
use Newscoop\SolrSearchPluginBundle\TemplateList\SearchResultsSolrCriteria;

/**
 * SolrSearchPluginBundle List
 */
class SearchResultsSolrList extends PaginatedBaseList
{
    protected function prepareList($criteria, $parameters)
    {
        $service = \Zend_Registry::get('container')->get('newscoop_solrsearch_plugin.query_service');
        $em = \Zend_Registry::get('container')->get('em');

        $language = $em->getRepository('Newscoop\Entity\Language')
            ->findOneByCode($parameters['language']);
        if ($language instanceof \Newscoop\Entity\Language) {
            $criteria->core = $language->getRFC3066bis();
        }

        // TODO: Optimize this criteria stuff
        try {
            $result = $service->find($this->convertCriteriaToQuery($criteria));
        } catch (\Exception $e) {
            // TODO: Make this work nicely for templates
            throw new \Exception($e->getMessage());
        }

        $docs = array();
        $list = false;
        if (is_array($result) && array_key_exists('response', $result) && array_key_exists('docs', $result['response'])) {

            $languageId = $language->getId();
            $docs = array_map(function ($doc) use ($languageId) {
                // TODO: Implement way to handle other indexable types
                switch ($doc['type']) {
                    case 'comment':
                        break;
                    case 'user':
                        break;
                    case 'article':
                    default:
                        return new \MetaArticle($doc['language_id'], $doc['number']);
                        break;
                }
            }, $result['response']['docs']);

            $list = $this->paginateList($docs, null, $criteria->maxResults);
        }

        return $list;
    }

    /**
     * Convert parameters array to Criteria
     *
     * @param integer $firstResult
     * @param array   $parameters
     *
     * @return Criteria
     */
    protected function convertParameters($firstResult, $parameters)
    {
        parent::convertParameters($firstResult, $parameters);

        foreach ($this->criteria as $key => $value) {
            if (array_key_exists($key, $parameters)) {
                $this->criteria->$key = $parameters[$key];
            }
        }
    }

    /**
     * Converts a SearchResultsSolrCriteria object to a SolrQuer object
     *
     * @param  SearchResultsSolrCriteria $criteria
     *
     * @return SolrQuery
     */
    protected function convertCriteriaToQuery(SearchResultsSolrCriteria $criteria)
    {
        $parameters = (array) $criteria;

        unset($parameters['orderBy']);
        unset($parameters['firstResult']);
        unset($parameters['maxResults']);

        $parameters['sort'] = $criteria->orderBy;
        $parameters['start'] = $criteria->firstResult;
        $parameters['rows'] = $criteria->maxResults;

        return new SolrQuery($parameters);
    }
}
