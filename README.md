# SoFinder HTTP

Framework-neutral PSR-7/PSR-17 HTTP use cases for SoFinder. It contains the
canonical 52-route endpoint catalog, request validation, shared browser/API
actions, response and stream results, and stable error mapping.

```bash
composer require sohophp/sofinder-http:^1.0
```

The package supports PHP 8.2–8.5 and depends on `sofinder-core`, not on a full
framework. A host must register the actions required by its enabled features and
must provide authorization, actor, workspace and CSRF implementations. Missing
security dependencies are not treated as anonymous access.

This package remains a framework-neutral building block. Combine it with
`sohophp/sofinder-psr15` for the browser/API runtime on Slim, Mezzio or plain
PHP, or use a full framework bridge when framework DI and console integration
are required.

Documentation: <https://sofinder.sohophp.app/framework-support>

License: MIT.
