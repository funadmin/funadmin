<?php

declare(strict_types=1);

namespace Tests\Console;

final class LocalTlsReceiver
{
    private mixed $process;
    private array $pipes = [];
    public readonly int $port;
    public readonly string $certificate;

    public function __construct(private readonly string $directory)
    {
        mkdir($directory, 0700, true);
        $config = $directory . '/openssl.cnf';
        file_put_contents($config, "[req]\ndistinguished_name=dn\nx509_extensions=extensions\nprompt=no\n[dn]\nCN=8.8.8.8\n[extensions]\nsubjectAltName=IP:8.8.8.8\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\n");
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => '8.8.8.8'], $key, ['config' => $config, 'digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['config' => $config, 'x509_extensions' => 'extensions', 'digest_alg' => 'sha256']);
        openssl_x509_export($cert, $public);
        openssl_pkey_export($key, $private);
        $this->certificate = $directory . '/certificate.pem';
        file_put_contents($this->certificate, $public);
        file_put_contents($directory . '/server.pem', $public . $private);
        chmod($directory . '/server.pem', 0600);
        $this->process = proc_open([PHP_BINARY, __FILE__, '--serve', $directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes);
        if (!is_resource($this->process)) throw new \RuntimeException('无法启动本地 TLS 接收端');
        fclose($this->pipes[0]);
        $ready = fgets($this->pipes[1]);
        if (!is_string($ready) || !ctype_digit(trim($ready))) throw new \RuntimeException('本地 TLS 接收端未就绪');
        $this->port = (int) trim($ready);
    }

    public function client(): \GuzzleHttp\Client
    {
        $curl = new \GuzzleHttp\Handler\CurlHandler();
        $port = $this->port;
        return new \GuzzleHttp\Client(['verify' => $this->certificate, 'handler' => \GuzzleHttp\HandlerStack::create(static function ($request, array $options) use ($curl, $port) {
            $options['curl'][CURLOPT_CONNECT_TO] = ['8.8.8.8:443:127.0.0.1:' . $port];
            return $curl($request, $options);
        })]);
    }

    public function requests(): array
    {
        return array_map(static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($this->directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES) ?: []);
    }

    public function close(): void
    {
        if (!is_resource($this->process)) return;
        proc_terminate($this->process);
        foreach ([1, 2] as $index) fclose($this->pipes[$index]);
        proc_close($this->process);
    }
}

if (($argv[1] ?? '') === '--serve' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $directory = $argv[2];
    $context = stream_context_create(['ssl' => ['local_cert' => $directory . '/server.pem', 'verify_peer' => false]]);
    $server = stream_socket_server('tls://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
    if ($server === false) exit(1);
    echo substr(strrchr(stream_socket_get_name($server, false), ':'), 1) . "\n"; flush();
    $counts = [];
    while ($connection = @stream_socket_accept($server, 15)) {
        stream_set_timeout($connection, 5);
        $headers = '';
        while (($line = fgets($connection)) !== false && $line !== "\r\n") $headers .= $line;
        preg_match('/Content-Length:\s*(\d+)/i', $headers, $length);
        $body = ''; $remaining = (int) ($length[1] ?? 0);
        while ($remaining > 0 && ($chunk = fread($connection, $remaining)) !== false && $chunk !== '') { $body .= $chunk; $remaining -= strlen($chunk); }
        preg_match('~^POST (/[^ ]*) HTTP/~', $headers, $path);
        $path = $path[1] ?? '/';
        $counts[$path] = ($counts[$path] ?? 0) + 1;
        file_put_contents($directory . '/requests.jsonl', json_encode(['headers' => $headers, 'body' => $body, 'path' => $path], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX);
        $status = $path === '/retry' && $counts[$path] === 1 ? 503 : ($path === '/redirect' ? 302 : 204);
        fwrite($connection, "HTTP/1.1 $status Response\r\nContent-Length: 0\r\nConnection: close\r\n" . ($status === 302 ? "Location: https://127.0.0.1/blocked\r\n" : '') . "\r\n");
        fclose($connection);
    }
    fclose($server);
}
