## Install

You can install the package via Composer:
```bash
composer require aruns/laravel-html-validator 
```

Next, you must install the service provider:

```php
// config/app.php
'providers' => [
    ...
    Aruns\LaravelHTMLValidator\LaravelHTMLValidator::class,
];
```

You must register the `\Aruns\LaravelHTMLValidator\LaravelHTMLValidator`:

```php
// app/Console/Kernel.php
protected $commands = [
    ...
    \Aruns\LaravelHTMLValidator\LaravelHTMLValidator::class,
];
```
