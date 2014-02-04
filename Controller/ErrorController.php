<?php
/**
 * @package Newscoop
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\OmnitickerPluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

class ErrorController extends Controller
{
    /**
     * @Route("/search/error")
     * @Route("/{language}/search/error")
     * @Route("/omniticker/error")
     * @Route("/{language}/omniticker/error")
     */
    public function errorAction(Request $request, $language = null)
    {
        $templatesService = $this->container->get('newscoop.templates.service');

        return new Response(
            $templatesService->fetchTemplate("_views/search_error.tpl"),
            503
        );
    }
}
