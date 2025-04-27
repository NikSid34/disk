<?php

declare(strict_types=1);

namespace app\test\support;

use app\container\AppContainer;
use app\ui\Component;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;

abstract class BaseTestCase extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        $this->resetSessionState();
        $this->resetAppContainer();
        $this->resetComponentState();
    }

    protected function resetSessionState(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [];
    }

    protected function resetAppContainer(): void {
        $reflection = new \ReflectionClass(AppContainer::class);
        $property = $reflection->getProperty('default');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    protected function resetComponentState(): void {
        $reflection = new \ReflectionClass(Component::class);

        $classesMap = $reflection->getProperty('classesMap');
        $classesMap->setAccessible(true);
        $classesMap->setValue(null, []);

        $helper = $reflection->getProperty('helper');
        $helper->setAccessible(true);
        $helper->setValue(null, null);
    }

    protected function setPrivateProperty(object|string $target, string $propertyName, mixed $value): void {
        $reflection = new \ReflectionClass($target);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue(is_string($target) ? null : $target, $value);
    }

    protected function getPrivateProperty(object|string $target, string $propertyName): mixed {
        $reflection = new \ReflectionClass($target);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue(is_string($target) ? null : $target);
    }

    protected function createTemporaryDirectory(string $prefix = 'simpledisk-test-'): string {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid('', true);
        mkdir($path, 0777, true);

        return $path;
    }

    protected function createTemporaryFile(string $contents = '', string $suffix = '.tmp'): string {
        $directory = $this->createTemporaryDirectory('simpledisk-file-');
        $path = $directory . DIRECTORY_SEPARATOR . 'file' . $suffix;
        file_put_contents($path, $contents);

        return $path;
    }

    protected function createRequest(
            string $method = 'GET',
            string $uri = '/',
            array $queryParams = [],
            mixed $parsedBody = null,
            ?string $body = null,
            array $uploadedFiles = []
    ): ServerRequestInterface {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest($method, $uri)
                ->withQueryParams($queryParams)
                ->withUploadedFiles($uploadedFiles);

        if ($parsedBody !== null) {
            $request = $request->withParsedBody($parsedBody);
        }

        if ($body !== null) {
            $stream = (new StreamFactory())->createStream($body);
            $request = $request->withBody($stream);
        }

        return $request;
    }

    protected function createResponse(): ResponseInterface {
        return new Response();
    }

    protected function getResponseBody(ResponseInterface $response): string {
        $body = $response->getBody();
        $body->rewind();

        return $body->getContents();
    }
}
