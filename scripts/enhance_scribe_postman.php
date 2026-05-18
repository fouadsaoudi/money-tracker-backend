<?php

declare(strict_types=1);

use App\Services\PostmanCollectionEnhancer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$relativePath = 'scribe/collection.json';

if (! Storage::disk('local')->exists($relativePath)) {
    fwrite(STDERR, "Postman collection not found. Run 'php artisan scribe:generate' first.\n");
    exit(1);
}

app(PostmanCollectionEnhancer::class)->enhance(
    Storage::disk('local')->path($relativePath)
);

fwrite(STDOUT, 'Enhanced Postman collection: '.Storage::disk('local')->path($relativePath).PHP_EOL);
