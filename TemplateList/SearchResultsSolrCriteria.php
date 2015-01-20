<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\TemplateList;

use Newscoop\Criteria;

/**
 * Criteria object for Solr search resulsts
 */
class SearchResultsSolrCriteria extends Criteria
{
    // These properties are dupl;icates, method will convert them
    // public $sort; > $orderBy
    // public $start = 0; > $firstResult
    // public $rows = 10; > $maxResults

    /**
     * @var string
     */
    public $core;

    /**
     * @var string
     */
    public $q;

    /**
     * @var string
     */
    public $fq;

    /**
     * @var string
     */
    public $fl;

    /**
     * @var string
     */
    public $df;

    /**
     * @var string
     */
    public $wt;

    /**
     * @var string
     */
    public $defType;

    /**
     * @var string
     */
    public $qf;
}
