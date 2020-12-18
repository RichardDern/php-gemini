# php-gemini

php-gemini is a library implementing the 
[Project Gemini protocol](https://gemini.circumlunar.space), as both client and
server-side.

## Installation

```bash
composer require richarddern/php-gemini
```

## Common features

- Supports TLS from both client and server-side, thanks to [react/socket](https://reactphp.org/socket/)
- A script is included to generate your own self-signed certificate in 
case you don't or can't use an official one
- URIs are parsed, validated, and resolved, so you can use relative
URIs as well, thanks to [league/uri](https://uri.thephpleague.com)
- Extensive logging, thanks to [monolog](https://github.com/Seldaek/monolog)
- Filesystem abstraction provided by [league/flysystem](https://flysystem.thephpleague.com/v2/docs/)
allowing you to serve files from the local file-system, FTP, AWS and even in-memory

## Client

### Simple example

```php
use RichardDern\Gemini\Client;

$client   = new Client('gemini.circumlunar.space');
$response = $client->request('/software/');

print_r($reponse);
```

The Response object will provide you access to response status code, response
meta and body:

```
RichardDern\Gemini\Response Object
(
    [status] => 20
    [meta] => text/gemini
    [body] => # Gemini software

Here is a list of all known Gemini-related software.
[...]
)
```

You can see the status code on the Appendix 1. of 
[Gemini specifications](https://gemini.circumlunar.space/docs/specification.html).

### Client class

You can define the server you want to connect to when instanciating the
client:

```php
$client = new Client('gemini.circumlunar.space', 1965);
```

Or later:

```php
$client = new Client();
$client->setServer('gemini.circumlunar.space')->setPort(1965);
```

If you intend on using relative URIs, you must define the base URI 
before making any request:

```php
$client->setBaseUri('gemini://gemini.circumlunar.space:1965');
```

Note that this step is not required if you already have defined the
target server, either in the class constructor or by calling the
_setServer_ method.

Finally, you can send a request using the following:

```php
$response = $client->request('gemini://gemini.circumlunar.space/software/');

$response = $client->request('/software/');
```

The response object provides the following public properties:

- _status_, a two-digits code that informs client about the response
state
- _meta_, which contains requested file's MIME type
- _body_, response's body, requested file's content

## Server

### Simple example

```php
$server = new RichardDern\Gemini\Server('0.0.0.0', 1965, '/var/www/gemini');
$server->start();
```

Now you can query this server from another process. By default, files must be
placed inside a _www_ folder where the server is run.

### Server class

You can specify the address and the port the server must bind to in
the class constructor, as well as server's root directory, where it
will look for requested files:

```php
$server = new Server('127.0.0.1', 4587, '/home/user/gemini');
$server->start();
```

### VirtualHosts

A - very - basic virtual hosts system is provided. It works simply by looking at
the host provided in the requested URL. If a directory with that name exists in
server's root directory, files will be served from there. 

If there is no directory matching the hostname, the server will look for
requested files in the _default_ directory, if it exists.

### TLS

For TLS to work, you need a certificate. A script to produce a self-signed
certificate if available in the _bin_ folder.

```bash
php ./bin/generate-self-signed-certificate.php > localhost.pem
```

Of course, you can use any valid certificate you want.

When instanciating the server class, pass the path to the certificate to the
constructor:

```php
$server = new Server('127.0.0.1', 4587, '/home/user/gemini', '/home/user/certificate/pem');
```

## Author

Richard Dern - https://github.com/RichardDern

## License

MIT