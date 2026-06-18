<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\SecurityHelper;

final class SecurityHelperTest extends TestCase
{
    public function testHashPasswordReturnsString(): void
    {
        $helper = new SecurityHelper();
        $hash = $helper->hashPassword('myPassword');
        self::assertIsString($hash);
    }

    public function testHashPasswordDefaultCost(): void
    {
        $helper = new SecurityHelper();
        $hash = $helper->hashPassword('test');
        $info = password_get_info($hash);
        $this->assertSame(10, $info['options']['cost']);
    }

    public function testHashPasswordPassesCostToHasher(): void
    {
        $helper = new SecurityHelper();
        $hash = $helper->hashPassword('test', 4);
        $info = password_get_info($hash);
        $this->assertSame(4, $info['options']['cost']);
    }

    public function testHashPasswordReturnsNonEmptyString(): void
    {
        $helper = new SecurityHelper();
        $hash = $helper->hashPassword('myPassword');
        self::assertNotEmpty($hash);
    }

    public function testValidatePasswordReturnsTrueForMatchingPassword(): void
    {
        $helper = new SecurityHelper();
        $hash = $helper->hashPassword('myPassword');
        self::assertTrue($helper->validatePassword('myPassword', $hash));
    }

    public function testValidatePasswordReturnsFalseForWrongPassword(): void
    {
        $helper = new SecurityHelper();
        $hash = $helper->hashPassword('myPassword');
        self::assertFalse($helper->validatePassword('wrongPassword', $hash));
    }

    public function testGenerateRandomStringReturnsStringOfSpecifiedLength(): void
    {
        $helper = new SecurityHelper();
        $result = $helper->generateRandomString(16);
        self::assertSame(16, \strlen($result));
    }

    public function testGenerateRandomStringDefaultLengthIs32(): void
    {
        $helper = new SecurityHelper();
        $result = $helper->generateRandomString();
        self::assertSame(32, \strlen($result));
    }

    public function testGenerateRandomStringReturnsDifferentValuesOnSubsequentCalls(): void
    {
        $helper = new SecurityHelper();
        $first = $helper->generateRandomString();
        $second = $helper->generateRandomString();
        self::assertNotSame($first, $second);
    }

    public function testGenerateRandomStringReturnsDifferentValuesForDifferentLengths(): void
    {
        $helper = new SecurityHelper();
        $result1 = $helper->generateRandomString(16);
        $result2 = $helper->generateRandomString(32);
        self::assertNotSame($result1, $result2);
    }

    public function testIntegrationHashAndValidate(): void
    {
        $helper = new SecurityHelper();
        $password = 'correct horse battery staple';
        $hash = $helper->hashPassword($password);

        self::assertTrue($helper->validatePassword($password, $hash));
        self::assertFalse($helper->validatePassword($password . 'x', $hash));
    }

    public function testHashPasswordWithDifferentCosts(): void
    {
        $helper = new SecurityHelper();
        $hash4 = $helper->hashPassword('test', 4);
        $hash10 = $helper->hashPassword('test', 10);

        self::assertIsString($hash4);
        self::assertIsString($hash10);
        self::assertTrue($helper->validatePassword('test', $hash4));
        self::assertTrue($helper->validatePassword('test', $hash10));
    }
}
