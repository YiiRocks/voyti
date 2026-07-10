<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use Yiisoft\Security\PasswordHasher;

final class PasswordCommand extends Command
{
    use UserLookupTrait;

    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasher $passwordHasher,
        private PasswordGeneratorInterface $passwordGenerator,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:password')
            ->setDescription('Reset a user password');
        $this->configureUserOptions();
    }

    /**
     * @return int
     *
     * @psalm-return 0|1
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->findUserFromInput($input, $output, 'voyti:password');
        if ($user === null) {
            return Command::FAILURE;
        }

        $password = $this->passwordGenerator->generate(16);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setPasswordChangedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $output->writeln('<info>Password reset.</info>');
        $output->writeln("<comment>New password: {$password}</comment>");
        return Command::SUCCESS;
    }
}
