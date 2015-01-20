<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\SolrSearchPluginBundle\Entity\SolrConfig;
use Newscoop\SolrSearchPluginBundle\Form\Type\SolrConfigurationType;

class AdminController extends Controller
{
    /**
     * @Route("/admin/solr/status")
     * @Template()
     */
    public function statusAction(Request $request)
    {
        $status = true;
        $statusMessage = '';
        $cores = array();
        $rawResponseBody = '';

        $helper = $this->get('newscoop_solrsearch_plugin.helper');
        $client = $helper->initClient();

        try {
            $response = $client->get($helper->getCoresUrl());
            $rawResponseBody = $response->getContent();
            $responseJson = json_decode($rawResponseBody, true);

            array_walk($responseJson['status'], function($value) use (&$cores) {
                $value = array_merge($value, $value['index']);
                $valueKeys = array_keys($value);
                $valueKeys = array_filter($valueKeys, function($key) {
                    return in_array($key, array('name', 'startTime', 'uptime', 'numDocs', 'maxDoc', 'version', 'lastModified', 'size'));
                });
                $cores[] = array_intersect_key($value, array_flip($valueKeys));
            });
        } catch (\Exception $e) {
            $status = false;
            $statusMessage = $e->getMessage();
        }

        return array(
            'status' => $status,
            'message' => $statusMessage,
            'cores' => $cores,
            'rawBody' => $rawResponseBody
        );
    }

    /**
     * @Route("/admin/solr/settings")
     * @Template()
     */
    public function settingsAction(Request $request)
    {
        $em = $this->get('em');
        $translator = $this->get('translator');
        $helper = $this->get('newscoop_solrsearch_plugin.helper');

        $indexables = $helper->getIndexables();
        $indexables = array_keys($indexables);
        $indexables = array_combine($indexables, $indexables);
        array_walk($indexables, function(&$value) use ($translator) {
            $value = $translator->trans(sprintf('plugin.solr.admin.form.label.indexable_item.%s', str_replace('.', '_', $value)));
        });

        $form = $this->createForm(new SolrConfigurationType(), null, array(
            'em' => $em,
            'helper' => $helper,
            'indexables' => $indexables,
        ));

        // Handle updates in form
        if ($request->isXmlHttpRequest()) {
            $form->handleRequest($request);

            return new JsonResponse(array(
                'html' => htmlentities($this->renderView('NewscoopSolrSearchPluginBundle:Form:ajaxform.html.twig', array(
                    'form'   => $form->createView(),
                ))),
            ));
        } elseif ($request->getMethod() == 'GET') {
            $form->setData($helper->getConfigValues(true));
        }

        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $data = $form->getData();

                $configRepo = $em->getRepository('Newscoop\SolrSearchPluginBundle\Entity\SolrConfig');
                foreach ($data AS $key => $value) {

                    $config = $configRepo->findOneByKey($key);
                    if ($config == null) {
                        $config = new SolrConfig();
                        $config->setKey($key);
                    }
                    $config->setValue($value);

                    $em->persist($config);
                }

                // Add cornjob stuff
                if (array_key_exists('cron_custom', $data) && $data['cron_custom']) {
                    $cronString = $data['cron_custom'];
                } else {
                    $cronString = $data['cron_interval'];
                }

                try {
                    $cronExpression = \Cron\CronExpression::factory($cronString);
                    $indexerCron = $helper->getIndexerJob();
                    if ($indexerCron instanceof \Newscoop\Entity\CronJob) {
                        $indexerCron->setSchedule($cronString);
                        $em->persist($indexerCron);
                    }
                } catch (\Exception $e) {
                    $form->get('cron_custom')->addError(new FormError($e->getMessage()));
                }

                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'notice',
                    $translator->trans('plugin.solr.admin.form.status.success')
                );
            }
        }

        return array(
            'form' => $form
        );
    }
}
