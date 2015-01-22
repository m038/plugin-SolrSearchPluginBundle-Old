<?php
/**
 * @package Newscoop\SolrSearchPluginBundle
 * @author Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\TemplateList;

/**
 * Wrapper class for different types or search results
 */
class SolrResult
{
    protected $type;

    protected $object;

    public function __construct($type = null, $object = null)
    {
        $this->type = $type;
        $this->object = $object;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getObject()
    {
        return $this->object;
    }
}
