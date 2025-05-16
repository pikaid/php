<?php

require __DIR__ . '/../vendor/autoload.php';

use Pikaid\Pikaid;
use Ramsey\Uuid\Uuid;         // si tu utilises ramsey/uuid
use Ulid\Ulid;               // si tu as ulid/php
use Hidehalo\Nanoid\Client;  // ou tout package NanoID

function bench(callable $fn, int $iterations = 10000): float
{
  $start = microtime(true);
  for ($i = 0; $i < $iterations; $i++) {
    $fn();
  }
  return (microtime(true) - $start) * 1000; // ms
}

$iterations = 100000;

echo "Benchmarking {$iterations} iterations:\n\n";

$time = bench(fn() => Pikaid::generate(), $iterations);
printf("Pikaid:  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$time = bench(fn() => Uuid::uuid4()->toString(), $iterations);
printf("UUIDv4:  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$time = bench(fn() => (string) Ulid::generate(), $iterations);
printf("ULID:    %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$nanoClient = new Client();
$time = bench(fn() => $nanoClient->generateId(), $iterations);
printf("NanoID:  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);
