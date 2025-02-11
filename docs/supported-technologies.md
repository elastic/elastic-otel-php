

# The Elastic Distribution of OpenTelemetry PHP supports the following technologies

## PHP Versions
- PHP 8.1 - 8.4

## Supported PHP SAPI's
- php-cli
- php-fpm
- php-cgi/fcgi
- mod_php (prefork)

EDOT PHP supports all popular variations of using PHP in combination with a web server, such as Apache + mod_php, Apache + php_fpm or cgi, NGINX + php_fpm or cgi, and others.

## Supported Operating Systems
- **Linux**
  - Architectures: **x86_64** and **ARM64**
  - **glibc-based systems**: Packages available as **DEB** and **RPM**
  - **musl libc-based systems (Alpine Linux)**: Packages available as **APK**

## Instrumented Frameworks
- Laravel (v6.x/v7.x/v8.x/v9.x/v10.x/v11.x)
- Slim (v4.x)

## Instrumented Libraries
- Curl (v8.1 - v8.4)
- HTTP Async (php-http/httplug v2.x)
- MySQLi (v8.1 - v8.4)
- PDO (v8.1 - v8.4)

## Additional Features
- Automatic Root/Transaction Span
- Root/Transaction Span URL Grouping
- Inferred Spans (preview version)
- Asynchronous data sending
