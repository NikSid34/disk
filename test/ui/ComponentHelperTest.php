<?php

declare(strict_types=1);

namespace app\test\ui;

use app\test\support\BaseTestCase;
use app\ui\ComponentHelper;
use DateTime;

class ComponentHelperTest extends BaseTestCase {
    public function testEscapesHtml(): void {
        // region Arrange.
        $helper = new ComponentHelper();
        // endregion.

        // region Act.
        $result = $helper->e('<tag "test">');
        // endregion.

        // region Assert.
        $this->assertSame('&lt;tag &quot;test&quot;&gt;', $result);
        // endregion.
    }

    public function testFormatsFileSize(): void {
        // region Arrange.
        $helper = new ComponentHelper();
        // endregion.

        // region Act.
        $invalid = $helper->formatFileSize('abc');
        $zero = $helper->formatFileSize(0);
        $megabytes = $helper->formatFileSize(2 * 1024 * 1024, 2);
        // endregion.

        // region Assert.
        $this->assertSame('0 B', $invalid);
        $this->assertSame('0 B', $zero);
        $this->assertSame('2.00 MB', $megabytes);
        // endregion.
    }

    public function testReturnsFileIconClass(): void {
        // region Arrange.
        $helper = new ComponentHelper();
        // endregion.

        // region Act.
        $pdfIcon = $helper->fileIcon('report.pdf');
        $defaultIcon = $helper->fileIcon('binary.bin');
        // endregion.

        // region Assert.
        $this->assertSame('bi-file-earmark-pdf-fill text-danger', $pdfIcon);
        $this->assertSame('bi-file-earmark-fill text-secondary', $defaultIcon);
        // endregion.
    }

    public function testFormatsDateValues(): void {
        // region Arrange.
        $helper = new ComponentHelper();
        $dateTime = new DateTime('2025-01-01 12:34:56');
        // endregion.

        // region Act.
        $dateObjectResult = $helper->formatDate($dateTime, 'Y-m-d');
        $stringResult = $helper->formatDate('2025-01-02 10:20:30', 'd.m.Y');
        $invalidResult = $helper->formatDate([]);
        // endregion.

        // region Assert.
        $this->assertSame('2025-01-01', $dateObjectResult);
        $this->assertSame('02.01.2025', $stringResult);
        $this->assertSame('', $invalidResult);
        // endregion.
    }

    public function testBuildsUrlWithFilteredQuery(): void {
        // region Arrange.
        $helper = new ComponentHelper();
        // endregion.

        // region Act.
        $withQuery = $helper->buildUrl('/disk', ['folder' => 'abc', 'search' => '', 'page' => 1]);
        $withoutQuery = $helper->buildUrl('/disk', ['folder' => null]);
        // endregion.

        // region Assert.
        $this->assertSame('/disk?folder=abc&page=1', $withQuery);
        $this->assertSame('/disk', $withoutQuery);
        // endregion.
    }
}
