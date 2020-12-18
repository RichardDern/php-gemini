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
allowing you to serve files from various locations

## Documentation

### Client

To query a remote server, you first need to instanciate the _Client_ class:

```php
use RichardDern\Gemini\Client;

// This will connect to a server running on the same computer
$client = new Client();

// This will connect to a remote server on default port
//$client = new Client('gemini.circumlunar.space');

// And this will connect to a server using a custom port
//$client = new Client('10.0.0.1', 1966);

// You can define the server and remote port after instanciating the class,
// or whenever you want before your query:
$client->setServer('127.0.0.1');
$client->setPort(1965);
```

You are ready to query the server:

```php
// You can define the base URI that will be used to resolve subsequent
// relative queries
$client->setBaseUri('gemini://127.0.0.1:1965/absolute_path');

// This will then resolve to 
// gemini://127.0.0.1:1965/absolute_path/relative_path/document
$client->request('relative_path/document');

// Or, you can request an absolute URI directly:
$client->request('gemini://127.0.0.1:1965/absolute_path/relative_path/document');
```

The result will be a _RichardDern\Gemini\Response_ object which exposes the
following properties:

- _$status_, a two-digits status code ; you can see the full list of status
codes in [Gemini's specifications](https://gemini.circumlunar.space/docs/specification.html), Appendix 1
- _$meta_, containing various informations about the response such as MIME type,
redirect URL or language, depending on server's response
- _$body_, the raw, unformated content of response body

### Server

This library also allows you to run a Gemini server with ease.

```php
use RichardDern\Gemini\Server;

// This will create a server on 127.0.0.1 and listening on default port (1965)
$server = new Server();

// You can set the binding address and port when instanciating the class...
//$server = new Server('[::1]', 1966);

// ...or after
$server->setAddress('[::1]');
$server->setPort(1965);
```

You are required to provide the server with the path to a certificate file prior
to actually start the server.

```php
$server->setCertificatePath('./localhost.pem');
```

You can use the provided _bin/generate-self-signed-certificate.php_ file. 

```bash
php ./bin/generate-self-signed-certificate.php > localhost.pem
```

This implementation support basic directory indexing, but you need to enable it
manually.

```php
$server->enableDirectoryIndex(true);
```

This implementation allows you to serve files from various file systems, 
including the local file system as well as a FTP server, or even in-memory
file system. Please look at the [league/flysystem](https://flysystem.thephpleague.com/v2/docs/) 
documentation to find out which adapters you can use.

Unless specified otherwise, the server will use the _LocalFilesystemAdapter_, 
and will look for files in a _www_ folder located where you launched the server
from.

However, you can use a different adapter if you want:

```php
// Serving files on Gemini from a FTP site
$adapter = new League\Flysystem\Ftp\FtpAdapter(
    // Connection options
    League\Flysystem\Ftp\FtpConnectionOptions::fromArray([
        'host' => 'hostname', // required
        'root' => '/root/path/', // required
        'username' => 'username', // required
        'password' => 'password', // required
        'port' => 21
    ])
);

$server->setFileSystemAdapter($adapter);
```

You can then start your server:

```php
$server->start();
```

You will need to use a process manager to ensure your server is kept running.
You could use systemd or supervisor to do this. The documentation will soon be
updated with some examples.

## Author

Richard Dern - https://github.com/RichardDern

## License

MIT