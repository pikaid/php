<?php

require __DIR__ . '/../vendor/autoload.php';

use Pikaid\Pikaid;
use Ramsey\Uuid\Uuid;
use Ulid\Ulid;
use Hidehalo\Nanoid\Client;

function bench(callable $fn, int $iters = 100000): float
{
  $start = microtime(true);
  for ($i = 0; $i < $iters; $i++) {
    $fn();
  }
  return (microtime(true) - $start) * 1000; // total ms
}

$iters = 100000;
$results = [];

// Pikaid
$time = bench(fn() => Pikaid::generate(), $iters);
$us   = $time * 1000 / $iters;
$results['Pikaid'] = ['ms' => $time, 'us' => $us];

// UUIDv1
$results['UUIDv1'] = ['ms' => $time = bench(fn() => Uuid::uuid1()->toString(), $iters)];
$results['UUIDv1']['us'] = $results['UUIDv1']['ms'] * 1000 / $iters;

// UUIDv4
$results['UUIDv4'] = ['ms' => $time = bench(fn() => Uuid::uuid4()->toString(), $iters)];
$results['UUIDv4']['us'] = $results['UUIDv4']['ms'] * 1000 / $iters;

// UUIDv6
$results['UUIDv6'] = ['ms' => $time = bench(fn() => Uuid::uuid6()->toString(), $iters)];
$results['UUIDv6']['us'] = $results['UUIDv6']['ms'] * 1000 / $iters;

// UUIDv7
$results['UUIDv7'] = ['ms' => $time = bench(fn() => Uuid::uuid7()->toString(), $iters)];
$results['UUIDv7']['us'] = $results['UUIDv7']['ms'] * 1000 / $iters;

// ULID
$results['ULID'] = ['ms' => $time = bench(fn() => (string) Ulid::generate(), $iters)];
$results['ULID']['us'] = $results['ULID']['ms'] * 1000 / $iters;

// NanoID
$client = new Client();
$results['NanoID'] = ['ms' => $time = bench(fn() => $client->generateId(), $iters)];
$results['NanoID']['us'] = $results['NanoID']['ms'] * 1000 / $iters;

// Compute ratios
$pikaUs = $results['Pikaid']['us'];
foreach ($results as $name => &$data) {
  $data['ratio'] = $data['us'] / $pikaUs;
}

// Display
echo "Benchmarking {$iters} iterations:\n\n";
printf("%-10s %10s %10s %8s\n", 'Library', 'Total ms', 'µs/op', 'Ratio');
echo str_repeat('-', 44) . "\n";
foreach ($results as $name => $data) {
  printf(
    "%-10s %10.2f %10.3f %8.2f\n",
    $name,
    $data['ms'],
    $data['us'],
    $data['ratio']
  );
}
