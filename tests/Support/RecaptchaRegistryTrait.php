<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use YiiRocks\Recaptcha\RecaptchaClient;
use YiiRocks\Recaptcha\RecaptchaConfig;
use YiiRocks\Recaptcha\RecaptchaRegistry;

trait RecaptchaRegistryTrait
{
    private function configureRecaptchaRegistry(): void
    {
        $config = new RecaptchaConfig(
            siteKeyV2: 'v2-site-key',
            secretV2: 'v2-secret',
            siteKeyV3: 'v3-site-key',
            secretV3: 'v3-secret',
        );

        RecaptchaRegistry::configure(new RecaptchaClient(
            $config,
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            $this->createStub(StreamFactoryInterface::class),
        ));
    }

    private function configureRecaptchaRegistryWithoutSecret(): void
    {
        $config = new RecaptchaConfig(
            siteKeyV2: 'v2-site-key',
            siteKeyV3: 'v3-site-key',
        );

        RecaptchaRegistry::configure(new RecaptchaClient(
            $config,
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            $this->createStub(StreamFactoryInterface::class),
        ));
    }
}
