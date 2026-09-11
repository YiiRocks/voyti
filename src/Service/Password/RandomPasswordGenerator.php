<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use Override;
use Random\Randomizer;
use YiiRocks\Voyti\VoytiConfig;

use function count;
use function implode;
use function max;
use function min;
use function str_split;

/**
 * Default cryptographically secure {@see PasswordGeneratorInterface} implementation.
 */
final readonly class RandomPasswordGenerator implements PasswordGeneratorInterface
{
    private const string LOWERCASE = 'abcdefghijklmnopqrstuvwxyz';
    private const string UPPERCASE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const string DIGITS = '0123456789';
    private const string SYMBOLS = '!@#$%^&*()-_=+[]{}:,.?';

    public function __construct(
        private VoytiConfig $config,
        private Randomizer $randomizer = new Randomizer(),
    ) {}

    #[Override]
    public function generate(int $length): string
    {
        $policy = $this->config->passwordPolicy;
        $length = min(max($length, $policy->minLength), $policy->maxLength);

        $characters = [];
        $this->appendRandomCharacters($characters, self::UPPERCASE, $policy->minUppercase);
        $this->appendRandomCharacters($characters, self::LOWERCASE, $policy->minLowercase);
        $this->appendRandomCharacters($characters, self::DIGITS, $policy->minDigits);
        $this->appendRandomCharacters($characters, self::SYMBOLS, $policy->minSymbols);
        $length = max($length, count($characters));
        $this->appendRandomCharacters(
            $characters,
            /** @infection-ignore-all Reordering the concatenated alphabets produces exactly the same filler character set. */
            self::UPPERCASE . self::LOWERCASE . self::DIGITS . self::SYMBOLS,
            $length - count($characters),
        );

        return implode('', $this->randomizer->shuffleArray($characters));
    }

    /**
     * @param list<string> $characters
     */
    private function appendRandomCharacters(array &$characters, string $alphabet, int $count): void
    {
        if ($count === 0) {
            return;
        }

        $characters = [...$characters, ...str_split($this->randomizer->getBytesFromString($alphabet, $count))];
    }
}
