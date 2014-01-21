<?php

namespace Newscoop\SolrSearchPluginBundle\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Newscoop\EventDispatcher\Events\PluginHooksEvent;

class HooksListener
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function sidebar(PluginHooksEvent $event)
    {
        $translator = $this->container->get('translator');

        $response = $this->container->get('templating')->renderResponse(
            'NewscoopSolrSearchPluginBundle:Hooks:sidebar.html.twig',
            array(
                'pluginName' => $translator->trans('plugin.sidebar.name'),
                'info' => $translator->trans('plugin.sidebar.info')
            )
        );

        $event->addHookResponse($response);
    }
}
