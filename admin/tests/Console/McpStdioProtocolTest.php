<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;

final class McpStdioProtocolTest extends TestCase
{
    private array $pipes = [];
    private mixed $process;
    private int $requestId = 0;
    private string $temporary;

    private function copyTree(string $source, string $target): void
    {
        if (!is_dir($target)) mkdir($target, 0700, true);
        foreach (new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS) as $file) {
            $destination = $target . '/' . $file->getFilename();
            if ($file->isDir()) $this->copyTree($file->getPathname(), $destination);
            else copy($file->getPathname(), $destination);
        }
    }

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->temporary = sys_get_temp_dir() . '/funadmin-mcp-entry-' . bin2hex(random_bytes(8));
        mkdir($this->temporary, 0700, true);
        copy($root . '/webman', $this->temporary . '/webman');
        copy($root . '/start.php', $this->temporary . '/start.php');
        symlink($root . '/vendor', $this->temporary . '/vendor');
        $this->copyTree($root . '/config', $this->temporary . '/config');
        $this->copyTree($root . '/support', $this->temporary . '/support');
        foreach (glob($root . '/app/admin/*/command', GLOB_ONLYDIR) as $directory) {
            $this->copyTree($directory, $this->temporary . '/app/admin/' . basename(dirname($directory)) . '/command');
        }
        copy($root . '/app/functions.php', $this->temporary . '/app/functions.php');
        mkdir($this->temporary . '/app/common/functions', 0700, true);
        copy($root . '/app/common/functions/plugin.php', $this->temporary . '/app/common/functions/plugin.php');
        $environment = array_replace(getenv(), ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'AI_QUEUE_ENABLED' => 'false']);
        $this->process = proc_open([PHP_BINARY, $this->temporary . '/webman', 'mcp', 'start', '--transport=stdio'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes, $this->temporary, $environment);
        self::assertIsResource($this->process);
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
    }

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        if (is_resource($this->process)) { proc_terminate($this->process); proc_close($this->process); }
        if (isset($this->temporary) && is_dir($this->temporary)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->temporary);
        }
    }

    private function request(string $method, ?array $params = null, bool $expectError = false): array
    {
        $id = ++$this->requestId;
        $request = ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method];
        if ($params !== null) $request['params'] = $params;
        fwrite($this->pipes[0], json_encode($request, JSON_THROW_ON_ERROR) . "\n");
        fflush($this->pipes[0]);
        $line = '';
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $part = fgets($this->pipes[1]);
            if ($part !== false) {
                $line .= $part;
                if (str_ends_with($line, "\n")) break;
            }
            if (!proc_get_status($this->process)['running']) break;
            usleep(10000);
        }
        self::assertNotSame('', $line, stream_get_contents($this->pipes[2]));
        self::assertJson($line, 'MCP stdout 非协议内容 HEX：' . bin2hex($line) . "\n" . stream_get_contents($this->pipes[2]));
        $response = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('2.0', $response['jsonrpc']);
        self::assertSame($id, $response['id']);
        if ($expectError) {
            self::assertArrayHasKey('error', $response);
            return $response['error'];
        }
        self::assertArrayNotHasKey('error', $response, json_encode($response));
        return $response['result'];
    }

    public function test_command_rejects_unknown_action_with_nonzero_exit(): void
    {
        $tester = new \Symfony\Component\Console\Tester\CommandTester(new \app\admin\common\command\McpServer());
        self::assertSame(1, $tester->execute(['action' => 'invalid']));
        self::assertStringContainsString('未知的操作', $tester->getDisplay());
    }

    public function test_stdio_handshake_tools_resources_calls_and_eof_are_clean(): void
    {
        $initialized = $this->request('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'console-test', 'version' => '1.0']]);
        self::assertSame('mcp', $initialized['serverInfo']['name']);
        fwrite($this->pipes[0], json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']) . "\n");
        $tools = $this->request('tools/list');
        self::assertCount(8, $tools['tools']);
        self::assertContains('webman-command', array_column($tools['tools'], 'name'));
        self::assertNotContains('think-command', array_column($tools['tools'], 'name'));
        $resources = $this->request('resources/list');
        self::assertCount(2, $resources['resources']);
        $system = $this->request('tools/call', ['name' => 'system-info', 'arguments' => ['type' => 'general']]);
        self::assertStringContainsString('Webman', json_encode($system));
        $query = $this->request('tools/call', ['name' => 'db-query', 'arguments' => ['query' => 'SELECT 42 AS value']]);
        self::assertStringContainsString('42', json_encode($query));
        $denied = $this->request('tools/call', ['name' => 'sys-config', 'arguments' => ['key' => 'database.connections.mysql.password']], true);
        self::assertSame(-32603, $denied['code']);
        $config = $this->request('resources/read', ['uri' => 'config://system']);
        self::assertStringNotContainsString('password', json_encode($config));
        fclose($this->pipes[0]);
        $deadline = microtime(true) + 3;
        do { $state = proc_get_status($this->process); usleep(10000); } while ($state['running'] && microtime(true) < $deadline);
        self::assertFalse($state['running'], 'EOF 后 MCP 进程必须退出');
        self::assertSame(0, $state['exitcode']);
        self::assertSame('', trim(stream_get_contents($this->pipes[1])), 'stdout 不得包含非协议输出');
        self::assertSame('', trim(stream_get_contents($this->pipes[2])));
    }
}
