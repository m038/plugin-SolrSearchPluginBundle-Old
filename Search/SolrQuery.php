<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Search;

use Newscoop\ValueObject;

/**
 * Solr query object contains all properties needed for a solr request.
 * The defaults here will always overwrite null values from the
 * SearchResultsSolrCriteria object.
 */
class SolrQuery extends ValueObject
{
    /**
     * Solr core, if null default_core option from configuration will be used
     *
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
    public $sort;

    /**
     * @var int
     */
    public $start = 0;

    /**
     * @var int
     */
    public $rows = 10;

    /**
     * Returned fields by Solr
     *
     * @var string
     */
    public $fl = 'id, number, type, language_id';

    /**
     * Default search field
     *
     * @var string
     */
    public $df = 'title';

    /**
     * Return type
     *
     * @var string
     */
    public $wt = 'json';

    /**
     * @var string
     */
    public $defType = 'edismax';

    /**
     * @var string
     */
    public $qf;
}
