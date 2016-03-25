<?php

/**
 * PHProxy
 * 
 * Simple PHP Proxy Server
 * 
 * @author Master Klavi <masterklavi@gmail.com>
 * @version 0.1
 */


// Listening

$listen = 7777;
if ($argc >= 2 && $argv[1] > 1000 && $argv[1] <= 9999) {
    $listen = (int)$argv[1];
}


// Stream Context

$context = stream_context_create([
    /*'socket' => [
        'bindto' => '192.168.0.100:0',
    ],*/
]);


// Start server

$server = stream_socket_server('tcp://localhost:'.$listen, $errno, $errstr);
if (!$server) {
    print $errstr.' ['.$errno.']'.PHP_EOL;
    exit;
}


// Accept a new client

while (true) {
    $client = @stream_socket_accept($server, 30);
    if ($client) {
        handle($client, $context);
    }
}


// Stop server (never)

fclose($server);


// Handle new client's request

function handle($client, $context) {
    
    // multi-thread (forks)
    $pid = pcntl_fork();
	if ($pid == -1) {
        print 'Fork Error'.PHP_EOL;
        exit;
        
	} elseif ($pid) {
		// parent process
		return;
	}
    
    // parse the request line, e.g. GET /blog/12 HTTP/1.1
    if (!preg_match('#^(?<method>HEAD|GET|POST) (?:http://[^/ ]+)?(?<uri>/\S{0,4095}) HTTP/(?<http_version>[\d\.]+)$#', rtrim(fgets($client)), $request)) {
        fwrite($client, "HTTP/1.1 400 Bad Request\r\n");
        stream_socket_shutdown($client, STREAM_SHUT_RDWR);
        fclose($client);
        exit;
    }
    if (!in_array($request['http_version'], ['1.1'])) {
        fwrite($client, "HTTP/1.1 505 HTTP Version Not Supported\r\n");
        stream_socket_shutdown($client, STREAM_SHUT_RDWR);
        fclose($client);
        exit;
    }
    
    
    // parse the request headers
    
    $headers = [];
    $headers_map = [];
    while (!feof($client)) {
        $line = fgets($client);
        if ($line === "\r\n" || $line === false) {
            break;
        }
        if (preg_match('#^(?<name>[A-Za-z0-9-]{1,65}): ?(?<value>.+)\r\n$#m', $line, $header)) {
            unset($header[1], $header[2]);
            $headers[] = $header;
            $headers_map[strtolower($header['name'])] = $header['value'];
        }
    }
    
    if (!isset($headers_map['host'])) {
        // http 1.1 requires the host
        fwrite($client, "HTTP/1.1 400 Bad Request\r\n");
        stream_socket_shutdown($client, STREAM_SHUT_RDWR);
        fclose($client);
        exit;
    }
    
    if (in_array($headers_map['host'], ['localhost', '127.0.0.1'])) {
        // decline the local requests
        fwrite($client, "HTTP/1.1 403 Forbidden\r\n");
        stream_socket_shutdown($client, STREAM_SHUT_RDWR);
        fclose($client);
        exit;
    }
    
    
    // parse the request body
    
    if (isset($headers_map['content-length']) && $headers_map['content-length']['value'] > 0) {
        $content_length = (int)$headers_map['content-length']['value'];
    } else {
        $content_length = 0;
    }
    
    if (in_array($request['method'], ['POST', 'PUT']) && $content_length > 0) {
        $body = '';
        while (!feof($client)) {
            $buffer = fread($client, 1024);
            if ($buffer === false) {
                break;
            }
            $body .= $buffer;
            if (strlen($body) >= $content_length) {
                break;
            }
        }
        $body = substr($body, 0, $content_length);
    } else {
        $body = null;
    }
    
    
    // request to destination
    
    $dest = stream_socket_client('tcp://'.$headers_map['host'].':80', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    if (!$dest) {
        fwrite($client, "HTTP/1.1 502 Bad Gateway\r\n");
        stream_socket_shutdown($client, STREAM_SHUT_RDWR);
        fclose($client);
        exit;  
    }
    fwrite($dest, $request['method']." ".$request['uri']." HTTP/1.1\r\n");
    foreach ($headers as $header) {
        fwrite($dest, $header[0]);
    }
    fwrite($dest, "\r\n");
    if (!is_null($body)) {
        fwrite($dest, $body);
    }
    
    $response = '';
    while (!feof($dest)) {
        $response .= fread($dest, 512);
    }
    fclose($dest);

    
    // respond to client
    
    fwrite($client, $response);
    
    stream_socket_shutdown($client, STREAM_SHUT_RDWR);
    fclose($client);
    exit;
    
}
