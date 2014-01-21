<?php

namespace Newscoop\SolrSearchPluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/solrtest")
     */
    public function indexAction(Request $request)
    {
        return $this->render('NewscoopSolrSearchPluginBundle:Default:index.html.smarty');
    }

    /**
     * @Route("/admin/solr")
     * @Template()
     */
    public function adminAction(Request $request)
    {
    	return array();
    }
}
