<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Tests\Unit;

use GlpiPlugin\Secret\Service\PasswordGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordGeneratorTest extends TestCase
{
    public function testStrongDefaults(): void
    {
        $password = (new PasswordGenerator())->generate();
        self::assertSame(20, strlen($password));
        self::assertMatchesRegularExpression('/[a-z]/', $password);
        self::assertMatchesRegularExpression('/[A-Z]/', $password);
        self::assertMatchesRegularExpression('/[0-9]/', $password);
        self::assertMatchesRegularExpression('/[^a-zA-Z0-9]/', $password);
        self::assertDoesNotMatchRegularExpression('/[Il1O0o]/', $password);
    }

    public function testOneEnabledSetIsEnough(): void
    {
        $password = (new PasswordGenerator())->generate(32, true, false, false, false);
        self::assertMatchesRegularExpression('/^[a-z]{32}$/', $password);
    }

    public function testAmbiguousCharactersRespectEnabledCategories(): void
    {
        $generator = new PasswordGenerator();
        foreach ([true, false] as $exclude) {
            for ($i = 0; $i < 10; ++$i) {
                self::assertMatchesRegularExpression('/^[0-9]{256}$/', $generator->generate(256, false, false, true, false, $exclude));
                self::assertMatchesRegularExpression('/^[a-z]{256}$/', $generator->generate(256, true, false, false, false, $exclude));
                self::assertMatchesRegularExpression('/^[A-Z]{256}$/', $generator->generate(256, false, true, false, false, $exclude));
                self::assertDoesNotMatchRegularExpression('/[a-zA-Z0-9]/', $generator->generate(256, false, false, false, true, $exclude));
            }
        }
    }

    public function testRejectsUnsafeLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PasswordGenerator())->generate(7);
    }

    public function testRejectsEmptyCharacterPool(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PasswordGenerator())->generate(20, false, false, false, false);
    }
}
