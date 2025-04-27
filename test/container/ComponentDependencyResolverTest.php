<?php

declare(strict_types=1);

namespace app\test\container;

use app\container\AppContainer;
use app\container\ComponentDependencyResolver;
use app\repository\UserRepository;
use app\service\UserService;
use app\test\support\BaseTestCase;
use app\test\support\FakePdo;
use RuntimeException;

class ComponentDependencyResolverTest extends BaseTestCase {
    public function testResolveReturnsContainerDependencyAndCachesIt(): void {
        // region Arrange.
        $container = new AppContainer('config_example.json');
        $this->setPrivateProperty($container, 'pdo', new FakePdo());
        $resolver = new ComponentDependencyResolver($container);
        // endregion.

        // region Act.
        $userRepository = $resolver->resolve(UserRepository::class);
        $userService = $resolver->resolve(UserService::class);
        // endregion.

        // region Assert.
        $this->assertSame($container->userRepository(), $userRepository);
        $this->assertSame($container->userService(), $userService);
        $this->assertSame($userService, $resolver->resolve(UserService::class));
        // endregion.
    }

    public function testResolveThrowsForUnknownDependency(): void {
        // region Arrange.
        $container = new AppContainer('config_example.json');
        $resolver = new ComponentDependencyResolver($container);
        // endregion.

        // region Act.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to resolve component dependency 'DateTimeImmutable'");
        $resolver->resolve(\DateTimeImmutable::class);
        // endregion.
    }
}
