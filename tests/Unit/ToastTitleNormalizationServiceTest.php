<?php

namespace App\Tests\Unit;

use App\Entity\Toast;
use App\Workspace\ToastTitleNormalizationService;
use PHPUnit\Framework\TestCase;

final class ToastTitleNormalizationServiceTest extends TestCase
{
    public function testKeepsTitleAndDescriptionWhenTitleFitsLimit(): void
    {
        $service = new ToastTitleNormalizationService();

        $result = $service->normalize('Short title', 'Existing description');

        self::assertSame('Short title', $result['title']);
        self::assertSame('Existing description', $result['description']);
        self::assertFalse($result['titleWasTruncated']);
    }

    public function testTruncatesLongTitleAndPrependsOriginalTitleToDescription(): void
    {
        $service = new ToastTitleNormalizationService();
        $longTitle = str_repeat('A', Toast::TITLE_MAX_LENGTH + 20);

        $result = $service->normalize($longTitle, 'Business context');

        self::assertSame(Toast::TITLE_MAX_LENGTH, strlen($result['title']));
        self::assertStringStartsWith(str_repeat('A', Toast::TITLE_MAX_LENGTH), $result['title']);
        self::assertSame("Original title: {$longTitle}\n\nBusiness context", $result['description']);
        self::assertTrue($result['titleWasTruncated']);
    }

    public function testReplacesExistingOriginalTitlePrefixWhenTruncatingAgain(): void
    {
        $service = new ToastTitleNormalizationService();
        $previousLongTitle = str_repeat('B', Toast::TITLE_MAX_LENGTH + 15);
        $newLongTitle = str_repeat('C', Toast::TITLE_MAX_LENGTH + 25);
        $existingDescription = "Original title: {$previousLongTitle}\n\nActual notes";

        $result = $service->normalize($newLongTitle, $existingDescription);

        self::assertSame("Original title: {$newLongTitle}\n\nActual notes", $result['description']);
    }
}
