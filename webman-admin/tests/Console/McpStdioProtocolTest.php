<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;

final class McpStdioProtocolTest extends TestCase
{
    private array $pipes = [];
    private mixed $process;
    private int $requestId = 0;

    protected function setUp(): void
    {
        $this->process = proc_open([PHP_BINARY, __DIR__ . '/mcp-entry.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes, dirname(__DIR__, 2));
        self::assertIsResource($this->process);
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
    }

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        if (is_resource($this->process)) { proc_terminate($this->process); proc_close($this->process); }
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
