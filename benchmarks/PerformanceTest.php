<?php

require __DIR__ . '/../vendor/autoload.php';

use Pikaid\Pikaid;
use Ramsey\Uuid\Uuid;
use Ulid\Ulid;
use Hidehalo\Nanoid\Client;

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
printf("Pikaid (pikaid/pikaid-php):  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$time = bench(fn() => Uuid::uuid1()->toString(), $iterations);
printf("UUIDv1 (ramsey/uuid):  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$time = bench(fn() => Uuid::uuid4()->toString(), $iterations);
printf("UUIDv4 (ramsey/uuid):  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$time = bench(fn() => Uuid::uuid6()->toString(), $iterations);
printf("UUIDv6 (ramsey/uuid):  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$time = bench(fn() => Uuid::uuid7()->toString(), $iterations);
printf("UUIDv7 (ramsey/uuid):  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$time = bench(fn() => (string) Ulid::generate(), $iterations);
printf("ULID (robinvdvleuten/ulid):    %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);

$nanoClient = new Client();
$time = bench(fn() => $nanoClient->generateId(), $iterations);
printf("NanoID (hidehalo/nanoid-php):  %.2f ms (%.3f µs/op)\n", $time, $time * 1000 / $iterations);
