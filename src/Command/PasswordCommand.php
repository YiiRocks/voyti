<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;

final class PasswordCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SecurityHelper $securityHelper,
        private readonly ModuleConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('voyti:password')
            ->setDescription('Reset a user password')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'Username')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = null;
        if ($id = $input->getOption('id')) {
            $user = $this->userRepository->findById((int)$id);
        } elseif ($email = $input->getOption('email')) {
            $user = $this->userRepository->findByEmail($email);
        } elseif ($username = $input->getOption('username')) {
            $user = $this->userRepository->findByUsername($username);
        }

        if ($user === null) {
            $output->writeln('<error>User not found</error>');
            return Command::FAILURE;
        }

        $password = bin2hex(random_bytes(8));
        $user->setPasswordHash($this->securityHelper->hashPassword($password, $this->config->blowfishCost));
        $user->setPasswordChangedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $output->writeln('<info>Password reset</info>');
        $output->writeln("<comment>New password: {$password}</comment>");
        return Command::SUCCESS;
    }
}
