<?php

declare(strict_types = 1);

namespace Eziat\BaikalWrapperBundle\Command;

use Eziat\BaikalWrapperBundle\Service\BaikalAdapter;
use Symfony\Component\Console;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteBaikalUserCommand extends Command
{
    /**
     * @var BaikalAdapter
     */
    private $baikalAdapter;

    public function __construct(BaikalAdapter $baikalAdapter)
    {
        $this->baikalAdapter = $baikalAdapter;
        parent::__construct();
    }

    /**
     * @see Console\Command\Command
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('eziat:baikal:delete-user')
            ->setDescription('Delete the user identified by username with including all data.')
            ->setDefinition([
                new Console\Input\InputArgument('username', Console\Input\InputArgument::REQUIRED,
                    'The username of the baikal user.'
                ),
            ]);
    }

    /**
     * @see Console\Command\Command
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        if ($this->baikalAdapter->baikalUserExists($username)) {
            $this->baikalAdapter->deleteBaikalUser($username);
            $output->writeln('<info>Baikal User $username deleted!</info>');
        } else {
            $output->writeln('<error>ERROR: Baikal User $username NOT FOUND!</error>');
        }
    }
}
