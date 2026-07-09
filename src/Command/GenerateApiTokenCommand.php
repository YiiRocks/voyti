<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\User\ApiTokenService;

final class GenerateApiTokenCommand extends Command
{
    use UserLookupTrait;

    public function __construct(
        private UserRepository $userRepository,
        private ApiTokenService $apiTokenService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:api-token:generate')
            ->setDescription('Generate an API access token for a user');
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
        $user = $this->findUserFromInput($input, $output, 'voyti:api-token:generate');
        if ($user === null) {
            return Command::FAILURE;
        }

        $token = $this->apiTokenService->generate($user);

        $output->writeln('<info>API token generated.</info>');
        $output->writeln("<comment>Token: {$token}</comment>");
        $output->writeln('<comment>This token will not be shown again — store it securely.</comment>');
        return Command::SUCCESS;
    }
}
