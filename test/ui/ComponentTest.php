<?php

declare(strict_types=1);

namespace app\test\ui;

use app\test\support\BaseTestCase;
use app\ui\Component;
use DateTimeImmutable;
use Exception;

class ComponentTest extends BaseTestCase {
    private string $componentsDir;

    protected function setUp(): void {
        parent::setUp();

        $this->componentsDir = $this->createTemporaryDirectory('simpledisk-components-');
        putenv('SIMPLEDISK_COMPONENTS_DIR=' . $this->componentsDir);
    }

    protected function tearDown(): void {
        putenv('SIMPLEDISK_COMPONENTS_DIR');

        parent::tearDown();
    }

    /**
     * @throws Exception
     */
    public function testRenderIncludeAndNestedComponentMethodsWork(): void {
        // region Arrange.
        $componentName = '__test_component_simple';
        $this->createComponent(
                $componentName,
                'TestSimpleComponentFixture',
                <<<'PHP'
class %s extends Component {
    public function execute(): string {
        $this->data = [
                'message' => (string) ($this->params['message'] ?? ''),
        ];

        return $this->includeTemplate();
    }
}
PHP,
                '<?= $component->e($data[\'message\']) ?>'
        );

        $instance = new class extends Component {};
        // endregion.

        // region Act.
        $rendered = Component::render('simpledisk:' . $componentName, ['message' => '<b>Hello</b>']);

        ob_start();
        Component::include('simpledisk:' . $componentName, ['message' => 'included']);
        $included = (string) ob_get_clean();

        ob_start();
        $instance->includeComponent('simpledisk:' . $componentName, ['message' => 'child']);
        $includedChild = (string) ob_get_clean();

        $renderedChild = $instance->renderComponent('simpledisk:' . $componentName, ['message' => 'rendered']);
        // endregion.

        // region Assert.
        $this->assertSame('&lt;b&gt;Hello&lt;/b&gt;', $rendered);
        $this->assertSame('included', $included);
        $this->assertSame('child', $includedChild);
        $this->assertSame('rendered', $renderedChild);
        // endregion.
    }

    /**
     * @throws Exception
     */
    public function testExecuteAndIncludeTemplateUseAssignedTemplateDirectory(): void {
        // region Arrange.
        $templateDirectory = $this->createTemporaryDirectory('simpledisk-component-template-');
        file_put_contents($templateDirectory . '/template.php', 'Hello, <?= $arResult[\'name\'] ?>!');
        file_put_contents($templateDirectory . '/custom.php', 'Custom <?= $arResult[\'name\'] ?>');

        $component = new class extends Component {};
        $this->setPrivateProperty($component, 'templateDir', $templateDirectory);
        $this->setPrivateProperty($component, 'params', ['name' => 'Nikita']);
        $this->setPrivateProperty($component, 'data', ['name' => 'Codex']);
        // endregion.

        // region Act.
        $executed = $component->execute();
        $this->setPrivateProperty($component, 'data', ['name' => 'Codex']);
        $included = $component->includeTemplate('custom.php');
        // endregion.

        // region Assert.
        $this->assertSame('Hello, Nikita!', $executed);
        $this->assertSame('Custom Codex', $included);
        // endregion.
    }

    /**
     * @throws Exception
     */
    public function testRenderAutomaticallyIncludesTemplateAssets(): void {
        // region Arrange.
        $componentName = '__asset_component_fixture';
        $this->createComponent(
                $componentName,
                'AssetComponentFixture',
                <<<'PHP'
class %s extends Component {}
PHP,
                '<section>Asset body</section>'
        );
        $templatePath = $this->componentsDir . '/' . $componentName . '/templates/.default';
        file_put_contents($templatePath . '/style.css', '.asset-body { color: red; }');
        file_put_contents($templatePath . '/script.js', 'window.assetLoaded = true;');
        // endregion.

        // region Act.
        $rendered = Component::render('simpledisk:' . $componentName);
        // endregion.

        // region Assert.
        $this->assertSame(
                '<link rel="stylesheet" href="/components/__asset_component_fixture/templates/.default/style.css">' . "\n"
                . '<section>Asset body</section>'
                . "\n" . '<script src="/components/__asset_component_fixture/templates/.default/script.js"></script>',
                $rendered
        );
        // endregion.
    }

    /**
     * @throws Exception
     */
    public function testRenderResolvesNamedIndexedAndTypedDependencies(): void {
        // region Arrange.
        $namedComponent = '__named_component_fixture';
        $this->createComponent(
                $namedComponent,
                'NamedDependencyComponentFixture',
                <<<'PHP'
class %s extends Component {
    public function __construct(
            private readonly string $prefix,
            private readonly string $suffix = 'tail'
    ) {
    }

    public function execute(): string {
        $this->data = ['value' => $this->prefix . '-' . $this->suffix];

        return $this->includeTemplate();
    }
}
PHP,
                '<?= $data[\'value\'] ?>'
        );

        $indexedComponent = '__indexed_component_fixture';
        $this->createComponent(
                $indexedComponent,
                'IndexedDependencyComponentFixture',
                <<<'PHP'
class %s extends Component {
    public function __construct(
            private readonly string $prefix,
            private readonly string $suffix = 'default'
    ) {
    }

    public function execute(): string {
        $this->data = ['value' => $this->prefix . '-' . $this->suffix];

        return $this->includeTemplate();
    }
}
PHP,
                '<?= $data[\'value\'] ?>'
        );

        $typedComponent = '__typed_component_fixture';
        $this->createComponent(
                $typedComponent,
                'TypedDependencyComponentFixture',
                <<<'PHP'
class %s extends Component {
    public function __construct(
            private readonly \DateTimeImmutable $clock
    ) {
    }

    public function execute(): string {
        $this->data = ['value' => $this->clock->format('Y-m-d H:i')];

        return $this->includeTemplate();
    }
}
PHP,
                '<?= $data[\'value\'] ?>'
        );
        // endregion.

        // region Act.
        $named = Component::render('simpledisk:' . $namedComponent, [], ['prefix' => 'head']);
        $indexed = Component::render('simpledisk:' . $indexedComponent, [], ['body']);
        $typed = Component::render('simpledisk:' . $typedComponent, [], [DateTimeImmutable::class => new DateTimeImmutable('2025-01-01 12:30:00')]);
        // endregion.

        // region Assert.
        $this->assertSame('head-tail', $named);
        $this->assertSame('body-default', $indexed);
        $this->assertSame('2025-01-01 12:30', $typed);
        // endregion.
    }

    public function testHelperMethodsDelegateToComponentHelper(): void {
        // region Arrange.
        $component = new class extends Component {};
        // endregion.

        // region Act.
        $escaped = $component->e('<hello>');
        $formattedSize = $component->formatFileSize(1024);
        $icon = $component->fileIcon('slides.pptx');
        $formattedDate = $component->formatDate('2025-01-01 10:00:00', 'Y-m-d');
        $url = $component->buildUrl('/disk', ['folder' => 'abc', 'search' => '']);
        // endregion.

        // region Assert.
        $this->assertSame('&lt;hello&gt;', $escaped);
        $this->assertSame('1.0 KB', $formattedSize);
        $this->assertSame('bi-file-earmark-ppt-fill text-warning', $icon);
        $this->assertSame('2025-01-01', $formattedDate);
        $this->assertSame('/disk?folder=abc', $url);
        // endregion.
    }

    public function testIncludeTemplateThrowsWhenTemplateIsMissing(): void {
        // region Arrange.
        $component = new class extends Component {};
        $this->setPrivateProperty($component, 'templateDir', $this->createTemporaryDirectory('simpledisk-missing-template-'));
        // endregion.

        // region Act.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template file');
        $component->includeTemplate('missing.php');
        // endregion.
    }

    private function createComponent(string $componentName, string $className, string $classBodyTemplate, string $templateContent): void {
        $componentPath = $this->componentsDir . '/' . $componentName;
        $templatePath = $componentPath . '/templates/.default';
        if (!is_dir($templatePath)) {
            mkdir($templatePath, 0777, true);
        }

        if (!is_file($componentPath . '/class.php')) {
            $classBody = sprintf($classBodyTemplate, $className);
            file_put_contents(
                    $componentPath . '/class.php',
                    "<?php\n\nnamespace app\\component;\n\nuse app\\ui\\Component;\n\n" . $classBody . "\n"
            );
        }

        if (!is_file($templatePath . '/template.php')) {
            file_put_contents($templatePath . '/template.php', $templateContent);
        }
    }
}
