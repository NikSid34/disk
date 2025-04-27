<?php

declare(strict_types=1);

namespace app\test\enum;

use app\enum\FileType;
use app\test\support\BaseTestCase;

class FileTypeTest extends BaseTestCase {
    public function testMapsFileExtensionsToEnumCases(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $image = FileType::fromFileName('photo.JPG');
        $pdf = FileType::fromFileName('report.pdf');
        $word = FileType::fromFileName('spec.docx');
        $excel = FileType::fromFileName('sheet.xlsx');
        $powerPoint = FileType::fromFileName('deck.ppt');
        $audio = FileType::fromFileName('sound.mp3');
        $archive = FileType::fromFileName('backup.zip');
        $text = FileType::fromFileName('notes.md');
        $other = FileType::fromFileName('binary.bin');
        // endregion.

        // region Assert.
        $this->assertSame(FileType::Image, $image);
        $this->assertSame(FileType::Pdf, $pdf);
        $this->assertSame(FileType::Word, $word);
        $this->assertSame(FileType::Excel, $excel);
        $this->assertSame(FileType::PowerPoint, $powerPoint);
        $this->assertSame(FileType::Audio, $audio);
        $this->assertSame(FileType::Archive, $archive);
        $this->assertSame(FileType::Text, $text);
        $this->assertSame(FileType::Other, $other);
        // endregion.
    }
}
