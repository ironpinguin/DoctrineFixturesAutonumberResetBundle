<?php

namespace Ironpinguin\Bundle\DoctrineFixturesAutonumberResetBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DoctrineCommand;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class LoadDataFixturesResetCommand extends DoctrineCommand
{
    /**
     * Configure Command
     */
    protected function configure()
    {
        $this
            ->setName("doctrine:fixtures:resetload")
            ->setDescription("Reset autonumbering with MySQL and then Load Data Fixtures")
            ->addOption('em', null, InputOption::VALUE_REQUIRED, 'The entity manager to use for this command.')
            ->addOption('shard', null, InputOption::VALUE_REQUIRED, 'The shard connection to use for this command.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('doctrine:fixtures:load');

        $em = $this->getEntityManager($input->getOption('em'), $input->getOption('shard'));
        $platform = $em->getConnection()->getDatabasePlatform();

        if ($input->isInteractive()) {
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelperSet()->get('question');
            $question = new ConfirmationQuestion(
                '<question>Careful, database will be purged. Do you want to continue y/N ?</question>',
                false
            );
            if (!$questionHelper->ask($input, $output, $question)) {
                return 1;
            }
        }

        $purger = new ORMPurger($em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
        $purger->purge();

        $allMetadata = $em->getMetadataFactory()->getAllMetadata();
        $qs = new DefaultQuoteStrategy();
        foreach ($allMetadata as $metadata) {
            if (!$metadata->isMappedSuperclass) {
                $em->getConnection()->executeUpdate("ALTER TABLE {$qs->getTableName($metadata, $platform)} AUTO_INCREMENT=1;");
            }
        }

        $arguments = [
            'command' => 'doctrine:fixtures:load',
            '--append'  => true,
        ];
        if ($input->getOption('em')) {
            $arguments['--em'] = $input->getOption('em');
        }
        if ($input->getOption('shard')) {
            $arguments['--shard'] = $input->getOption('shard');
        }
        $inputs = new ArrayInput($arguments);

        return $command->run($inputs, $output);
    }
}
