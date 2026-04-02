<?php

namespace App\Tests\Unit;

use App\Security\EmailNormalizerService;
use PHPUnit\Framework\TestCase;

final class EmailNormalizerServiceTest extends TestCase
{
    public function testNormalizeTrimsAndLowercasesEmail(): void
    {
        $normalizer = new EmailNormalizerService();

        self::assertSame('user@example.com', $normalizer->normalize('  User@Example.COM  '));
    }
}
