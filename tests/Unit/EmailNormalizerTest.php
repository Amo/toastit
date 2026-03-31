<?php

namespace App\Tests\Unit;

use App\Security\EmailNormalizer;
use PHPUnit\Framework\TestCase;

final class EmailNormalizerTest extends TestCase
{
    public function testNormalizeTrimsAndLowercasesEmail(): void
    {
        $normalizer = new EmailNormalizer();

        self::assertSame('user@example.com', $normalizer->normalize('  User@Example.COM  '));
    }
}
