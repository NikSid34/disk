<?php


namespace app\ui;


use app\Application;
use app\container\AppContainer;
use Exception;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;


class Component {
    /** @var array<string, string> */
    private static array $classesMap = [];

    private static ?ComponentHelper $helper = null;

    protected string $templateDir = '';

    /** @var array<string, mixed> */
    protected array $params = [];

    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param array<string, mixed> $params
     * @param array<int|string, mixed> $dependencies
     * @throws Exception
     */
    final public static function render(
            string $componentName,
            array $params = [],
            array $dependencies = [],
            string $template = '.default'
    ): string {
        $componentPath = self::resolveComponentPath($componentName);
        $componentClass = self::getClassFromPath($componentPath);

        try {
            $instance = self::createInstance($componentClass, $dependencies);
        } catch (Throwable $e) {
            throw new Exception("Failed to create component class instance: {$e->getMessage()}");
        }

        $instance->params = $params;
        $instance->templateDir = $componentPath . '/templates/' . $template;

        return $instance->execute();
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int|string, mixed> $dependencies
     * @throws Exception
     */
    final public static function include(
            string $componentName,
            array $params = [],
            array $dependencies = [],
            string $template = '.default'
    ): void {
        echo self::render($componentName, $params, $dependencies, $template);
    }

    /**
     * @throws Exception
     */
    public function execute(): string {
        $this->data = $this->params;

        return $this->includeTemplate();
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int|string, mixed> $dependencies
     * @throws Exception
     */
    final public function includeComponent(
            string $componentName,
            array $params = [],
            array $dependencies = [],
            string $template = '.default'
    ): void {
        echo self::render($componentName, $params, $dependencies, $template);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int|string, mixed> $dependencies
     * @throws Exception
     */
    final public function renderComponent(
            string $componentName,
            array $params = [],
            array $dependencies = [],
            string $template = '.default'
    ): string {
        return self::render($componentName, $params, $dependencies, $template);
    }

    /**
     * @throws Exception
     */
    final public function includeTemplate(string $templatePage = 'template.php'): string {
        $templateFile = \rtrim($this->templateDir, '/\\') . '/' . \ltrim($templatePage, '/\\');
        if (!\file_exists($templateFile) || !\is_file($templateFile)) {
            throw new Exception("Template file '{$templateFile}' not found");
        }

        \ob_start();
        (static function (string $__templateFile, array $__data, Component $__component): void {
            $data = $__data;
            $component = $__component;
            $arParams = $__component->params;
            $arResult = $__data;
            include $__templateFile;
        }) ($templateFile, $this->data, $this);

        $html = (string) \ob_get_clean();

        return $this->renderTemplateStyle() . $html . $this->renderTemplateScript();
    }

    public function e(mixed $value): string {
        return self::helper()->e($value);
    }

    public function formatFileSize(int|float|string|null $bytes, int $decimals = 1): string {
        return self::helper()->formatFileSize($bytes, $decimals);
    }

    public function fileIcon(string $filename): string {
        return self::helper()->fileIcon($filename);
    }

    public function formatDate(mixed $value, string $format = 'd.m.Y H:i'): string {
        return self::helper()->formatDate($value, $format);
    }

    public function buildUrl(string $path, array $query = []): string {
        return self::helper()->buildUrl($path, $query);
    }

    /**
     * @throws Exception
     */
    private static function getClassFromPath(string $componentPath): string {
        if (!isset(self::$classesMap[$componentPath])) {
            $componentClassPath = $componentPath . '/class.php';
            if (!file_exists($componentClassPath) || !is_file($componentClassPath)) {
                throw new Exception("Class for component '{$componentPath}' not found");
            }

            $beforeClasses = get_declared_classes();
            include_once $componentClassPath;
            $afterClasses = get_declared_classes();

            foreach (array_diff($afterClasses, $beforeClasses) as $className) {
                if (is_subclass_of($className, self::class)) {
                    self::$classesMap[$componentPath] = $className;
                    break;
                }
            }

            if (!isset(self::$classesMap[$componentPath])) {
                $classFilePath = realpath($componentClassPath);
                foreach ($afterClasses as $className) {
                    $reflection = new ReflectionClass($className);
                    if (
                            $reflection->isSubclassOf(self::class)
                            && $reflection->getFileName() === $classFilePath
                    ) {
                        self::$classesMap[$componentPath] = $className;
                        break;
                    }
                }
            }
        }

        if (
                !isset(self::$classesMap[$componentPath])
                || !class_exists(self::$classesMap[$componentPath])
                || !is_subclass_of(self::$classesMap[$componentPath], self::class)
        ) {
            throw new Exception('Class not found or does not inherit base component class');
        }

        return self::$classesMap[$componentPath];
    }

    /**
     * @throws Exception
     */
    private static function resolveComponentPath(string $componentName): string {
        $componentName = trim($componentName);
        if ($componentName === '') {
            throw new Exception('Empty component name');
        }

        $componentNameParts = explode(':', $componentName, 2);
        $relativeComponentName = $componentNameParts[count($componentNameParts) - 1];
        if ($relativeComponentName === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $relativeComponentName)) {
            throw new Exception("Invalid component name '{$componentName}'");
        }

        $componentPath = Application::getComponentsFolder() . '/' . $relativeComponentName;
        if (!is_dir($componentPath)) {
            throw new Exception("Component '{$componentName}' not found");
        }

        return $componentPath;
    }

    /**
     * @param array<int|string, mixed> $dependencies
     * @throws Exception
     */
    private static function createInstance(string $componentClass, array $dependencies): self {
        $reflection = new ReflectionClass($componentClass);
        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            /** @var self $instance */
            $instance = $reflection->newInstance();

            return $instance;
        }

        $namedDependencies = array_filter($dependencies, static fn(mixed $key): bool => !is_int($key), ARRAY_FILTER_USE_KEY);
        $indexedDependencies = array_values(array_filter($dependencies, static fn(mixed $key): bool => is_int($key), ARRAY_FILTER_USE_KEY));
        $indexedPosition = 0;
        $resolvedDependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            if (array_key_exists($parameterName, $namedDependencies)) {
                $resolvedDependencies[] = $namedDependencies[$parameterName];
                continue;
            }

            if (array_key_exists($indexedPosition, $indexedDependencies)) {
                $resolvedDependencies[] = $indexedDependencies[$indexedPosition];
                $indexedPosition++;
                continue;
            }

            $parameterType = $parameter->getType();
            if ($parameterType instanceof ReflectionNamedType && !$parameterType->isBuiltin()) {
                $parameterTypeName = $parameterType->getName();
                if (array_key_exists($parameterTypeName, $namedDependencies)) {
                    $resolvedDependencies[] = $namedDependencies[$parameterTypeName];
                    continue;
                }

                $resolvedDependencies[] = AppContainer::fromDefaultConfig()
                        ->componentDependencyResolver()
                        ->resolve($parameterTypeName);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolvedDependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new Exception("Unable to resolve dependency '{$parameterName}' for component '{$componentClass}'");
        }

        /** @var self $instance */
        $instance = $reflection->newInstanceArgs($resolvedDependencies);

        return $instance;
    }

    private static function helper(): ComponentHelper {
        return self::$helper ??= new ComponentHelper();
    }

    private function renderTemplateStyle(): string {
        $assetUrl = $this->templateAssetUrl('style.css');
        if ($assetUrl === null) {
            return '';
        }

        return '<link rel="stylesheet" href="' . self::helper()->e($assetUrl) . '">' . "\n";
    }

    private function renderTemplateScript(): string {
        $assetUrl = $this->templateAssetUrl('script.js');
        if ($assetUrl === null) {
            return '';
        }

        return "\n" . '<script src="' . self::helper()->e($assetUrl) . '"></script>';
    }

    private function templateAssetUrl(string $filename): ?string {
        $assetPath = \rtrim($this->templateDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!\is_file($assetPath)) {
            return null;
        }

        $assetRealPath = \realpath($assetPath);
        if ($assetRealPath === false) {
            return null;
        }

        $documentRoot = \realpath(Application::getDocumentRoot());
        if ($documentRoot !== false) {
            $url = self::pathUrlFromBase($assetRealPath, $documentRoot, '');
            if ($url !== null) {
                return $url;
            }
        }

        $componentsFolder = \realpath(Application::getComponentsFolder());
        if ($componentsFolder === false) {
            return null;
        }

        return self::pathUrlFromBase($assetRealPath, $componentsFolder, '/components');
    }

    private static function pathUrlFromBase(string $path, string $basePath, string $urlPrefix): ?string {
        $basePath = \rtrim($basePath, '/\\');
        if ($path !== $basePath && !\str_starts_with($path, $basePath . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $relativePath = \substr($path, \strlen($basePath));
        $relativePath = \str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || $relativePath[0] !== '/') {
            $relativePath = '/' . $relativePath;
        }

        return $urlPrefix . $relativePath;
    }
}
