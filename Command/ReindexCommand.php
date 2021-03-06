<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Command;

use Sulu\Component\Content\Document\WorkflowStage;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reindixes articles.
 */
class ReindexCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('sulu:article:index-rebuild')
            ->addArgument('locale', InputArgument::REQUIRED)
            ->addOption('live', 'l', InputOption::VALUE_NONE)
            ->addOption('clear', null, InputOption::VALUE_NONE);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sql2 = 'SELECT * FROM [nt:unstructured] AS a WHERE [jcr:mixinTypes] = "sulu:article"';

        $id = 'sulu_article.elastic_search.article_indexer';
        if ($input->getOption('live')) {
            $id = 'sulu_article.elastic_search.article_live_indexer';
            $sql2 = sprintf(
                '%s AND [i18n:%s-state] = %s',
                $sql2,
                $input->getArgument('locale'),
                WorkflowStage::PUBLISHED
            );
        }

        $indexer = $this->getContainer()->get($id);
        $documentManager = $this->getContainer()->get('sulu_document_manager.document_manager');
        $query = $documentManager->createQuery($sql2, $input->getArgument('locale'));

        if ($input->getOption('clear')) {
            $indexer->clear();
        }

        $result = $query->execute();

        $progessBar = new ProgressBar($output, count($result));
        $progessBar->setFormat('debug');
        $progessBar->start();

        foreach ($result as $document) {
            $indexer->index($document);
            $progessBar->advance();
        }

        $indexer->flush();
        $progessBar->finish();
    }
}
