<?php

declare(strict_types=1);

namespace Switchboard\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Switchboard\Runtime\Dispatcher;
use Switchboard\Runtime\NormalizedRequest;
use Switchboard\Runtime\Registry;

final class DispatcherTest extends TestCase
{
    private function minimalEndpoint(): array
    {
        return [
            'id' => 'e-minimal-health',
            'app_id' => 'minimal-php-app',
            'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle',
            'path' => '/health',
            'method' => 'GET',
        ];
    }

    private function request(): NormalizedRequest
    {
        return new NormalizedRequest(
            'localhost',
            '/sb/minimal/v1/health',
            'GET',
            [],
            [],
            [],
            null,
            null,
            null,
            null
        );
    }

    public function testStubWithoutHandlers(): void
    {
        Dispatcher::setHandlersPath(null);
        $registry = $this->registry();
        $result = Dispatcher::dispatch($this->minimalEndpoint(), $this->request(), $registry);
        $this->assertSame(200, $result['status']);
        $this->assertArrayHasKey('matched', $result['body']);
        $this->assertSame('Minimal\\Health', $result['body']['handler_class']);
        $this->assertSame('handle', $result['body']['handler_method']);
    }

    public function testInvokesHandler(): void
    {
        $handlersPath = dirname(__DIR__, 2) . '/examples/minimal-handler';
        $this->assertDirectoryExists($handlersPath);
        Dispatcher::setHandlersPath($handlersPath);
        $registry = $this->registry();
        $result = Dispatcher::dispatch($this->minimalEndpoint(), $this->request(), $registry);
        $this->assertSame(200, $result['status']);
        $this->assertSame(['ok' => true, 'service' => 'minimal-handler'], $result['body']);
    }

    public function testInvokesCustomMethod(): void
    {
        $dir = $this->makeHandlersDir(
            <<<'PHP'
<?php
namespace DispatchFixtures;

use Switchboard\Runtime\NormalizedRequest;

final class CustomMethod
{
    public function respond(NormalizedRequest $request): array
    {
        return ['status' => 202, 'headers' => [], 'body' => ['method' => 'respond']];
    }
}
PHP
        );

        Dispatcher::setHandlersPath($dir);
        $result = Dispatcher::dispatch(
            ['id' => 'e-custom', 'app_id' => 'minimal-php-app', 'handler_class' => 'DispatchFixtures\\CustomMethod', 'handler_method' => 'respond', 'path' => '/custom', 'methods' => ['GET']],
            $this->request(),
            $this->registry()
        );

        $this->assertSame(202, $result['status']);
        $this->assertSame(['method' => 'respond'], $result['body']);
    }

    public function testInvokesCallableObject(): void
    {
        $dir = $this->makeHandlersDir(
            <<<'PHP'
<?php
namespace InvokeFixtures;

use Switchboard\Runtime\NormalizedRequest;

final class Invokable
{
    public function __invoke(NormalizedRequest $request): array
    {
        return ['status' => 200, 'headers' => [], 'body' => ['invoked' => true]];
    }
}
PHP
        );

        Dispatcher::setHandlersPath($dir);
        $result = Dispatcher::dispatch(
            ['id' => 'e-invokable', 'app_id' => 'minimal-php-app', 'handler_class' => 'InvokeFixtures\\Invokable', 'handler_method' => '__invoke', 'path' => '/invokable', 'methods' => ['GET']],
            $this->request(),
            $this->registry()
        );

        $this->assertSame(200, $result['status']);
        $this->assertSame(['invoked' => true], $result['body']);
    }

    public function testCustomMethodWins(): void
    {
        $dir = $this->makeHandlersDir(
            <<<'PHP'
<?php
namespace InterfaceFixtures;

use Switchboard\Runtime\SwitchboardAppInterface;
use Switchboard\Runtime\SwitchboardContext;
use Switchboard\Runtime\SwitchboardRequest;

final class DualHandler implements SwitchboardAppInterface
{
    public function handle(SwitchboardRequest $request, ?SwitchboardContext $context = null): array
    {
        return ['status' => 200, 'headers' => [], 'body' => ['method' => 'handle']];
    }

    public function respond(SwitchboardRequest $request): array
    {
        return ['status' => 202, 'headers' => [], 'body' => ['method' => 'respond']];
    }
}
PHP
        );

        Dispatcher::setHandlersPath($dir);
        $result = Dispatcher::dispatch(
            ['id' => 'e-interface-custom', 'app_id' => 'minimal-php-app', 'handler_class' => 'InterfaceFixtures\\DualHandler', 'handler_method' => 'respond', 'path' => '/custom', 'methods' => ['GET']],
            $this->request(),
            $this->registry()
        );

        $this->assertSame(202, $result['status']);
        $this->assertSame(['method' => 'respond'], $result['body']);
    }

    public function testMissingClassError(): void
    {
        $dir = $this->makeHandlersDir('<?php');
        Dispatcher::setHandlersPath($dir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler class not found: Missing\\Handler');

        Dispatcher::dispatch(
            ['id' => 'e-missing', 'app_id' => 'minimal-php-app', 'handler_class' => 'Missing\\Handler', 'handler_method' => 'handle', 'path' => '/missing', 'methods' => ['GET']],
            $this->request(),
            $this->registry()
        );
    }

    public function testMissingMethodError(): void
    {
        $dir = $this->makeHandlersDir(
            <<<'PHP'
<?php
namespace MissingMethodFixtures;

final class Methodless
{
}
PHP
        );

        Dispatcher::setHandlersPath($dir);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler method not found: MissingMethodFixtures\\Methodless::handle');

        Dispatcher::dispatch(
            ['id' => 'e-methodless', 'app_id' => 'minimal-php-app', 'handler_class' => 'MissingMethodFixtures\\Methodless', 'handler_method' => 'handle', 'path' => '/methodless', 'methods' => ['GET']],
            $this->request(),
            $this->registry()
        );
    }

    private function registry(): Registry
    {
        $registry = new Registry();
        $registry->apps = [
            ['id' => 'minimal-php-app', 'slug' => 'minimal', 'name' => 'Minimal', 'enabled' => true],
        ];
        return $registry;
    }

    private function makeHandlersDir(string $contents): string
    {
        $dir = sys_get_temp_dir() . '/switchboard-dispatcher-test-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/handlers.php', $contents);
        return $dir;
    }
}
