<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Yii\Console\ExitCode;

/**
 * Trait shared by user-targeting console commands: adds `--id`/`--email`/`--username` options and
 * resolves them to a {@see User}, writing a usage or not-found error to the command output on failure.
 */
trait UserLookupTrait
{
    private bool $hasIdentifyingOption = false;

    private function configureUserOptions(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'Username')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'ID');
    }

    private function findUserFromInput(InputInterface $input, OutputInterface $output, string $commandName): ?User
    {
        /** @var mixed $rawId */
        $rawId = $input->getOption('id');
        /** @var mixed $rawEmail */
        $rawEmail = $input->getOption('email');
        /** @var mixed $rawUsername */
        $rawUsername = $input->getOption('username');

        $this->hasIdentifyingOption = $rawId !== null || $rawEmail !== null || $rawUsername !== null;

        $user = null;
        if (is_string($rawId) && $rawId !== '') {
            $user = User::findById((int) $rawId);
        } elseif (is_string($rawEmail) && $rawEmail !== '') {
            $user = User::findByEmail($rawEmail);
        } elseif (is_string($rawUsername) && $rawUsername !== '') {
            $user = User::findByUsername($rawUsername);
        }

        if ($user === null) {
            if (!$this->hasIdentifyingOption) {
                $output->writeln('<error>No identifying option provided.</error>');
                $output->writeln('');
                $output->writeln("Usage: {$commandName} [options]");
                $output->writeln('');
                $output->writeln('Options:');
                $output->writeln('  --email=<email>        Email');
                $output->writeln('  --username=<username>  Username');
                $output->writeln('  --id=<id>              ID');
            } else {
                $output->writeln('<error>User not found.</error>');
            }
        }

        return $user;
    }

    /**
     * Exit code for a {@see findUserFromInput} lookup that returned `null`: distinguishes a usage
     * error (no identifying option given) from a genuine not-found (option given, no matching user).
     *
     * @psalm-return 64|67
     */
    private function getLookupFailureExitCode(): int
    {
        return $this->hasIdentifyingOption ? ExitCode::NOUSER : ExitCode::USAGE;
    }
}
