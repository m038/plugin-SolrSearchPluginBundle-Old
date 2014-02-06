<?php
/**
 * @package Newscoop
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Command;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console;

/**
 * Index clear command
 */
class ClearSolrIndexCommand
{
    /**
     * @see Console\Command\Command
     */
    protected function configure()
    {
        $this
        ->setName('index:clearsolr')
        ->setDescription('Clear solr index.')
        ->setHelp("");
    }

    /**
     * @see Console\Command\Command
     */
    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $container = $this->getApplication()->getKernel()->getContainer();
        $solrIndexClient = $container->get('index_client.solr');

        $output->writeln('Clearing solr indexes.');
        $solrIndexClient->deleteAll();
        $output->writeln('Search index cleared.');
    }
}
