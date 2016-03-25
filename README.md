# PHProxy
Simple PHP Proxy Server

# How to run
`$ php phproxy/server.php [port]`

# How to check
`$ telnet localhost [port]`
```
GET /line/?fields=query HTTP/1.1
Host: ip-api.com

HTTP/1.1 200 OK
Access-Control-Allow-Origin: *
Content-Type: text/plain; charset=utf-8
Date: Fri, 25 Mar 2016 13:12:41 GMT
Content-Length: 14
Connection: Keep-Alive

xxx.xxx.xxx.xxx
```