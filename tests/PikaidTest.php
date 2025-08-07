<?php

declare(strict_types=1);

namespace Pikaid\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Pikaid\Pikaid;
use DateTimeImmutable;
use InvalidArgumentException;

final class PikaidTest extends TestCase
{
  #[DataProvider('invalidIdProvider')]
  public function testIsValidRejectsInvalidIds(string $invalid): void
  {
    $this->assertFalse(
      Pikaid::isValid($invalid),
      sprintf('"%s" should be invalid', $invalid)
    );
  }

  public static function invalidIdProvider(): array
  {
    return [
      'empty'            => [''],
      'too short'        => [str_repeat('a', 25)],
      'too long'         => [str_repeat('a', 27)],
      'uppercase'        => [str_repeat('Z', 26)],
      'non-alphanumeric' => [str_repeat('z', 25) . '!'],
      'space'            => [str_repeat('0', 25) . ' '],
    ];
  }

  public function testGenerateCreatesValidFormat(): void
  {
    $id = Pikaid::generate();
    $this->assertIsString($id);
    $this->assertMatchesRegularExpression(
      '/^[0-9a-z]{26}$/',
      $id,
      'Generated ID must be 26 lowercase base36 chars'
    );
  }

  public function testTimestampWithinGenerationBounds(): void
  {
    $start = time() - 1;
    $id    = Pikaid::generate();
    $end   = time() + 1;

    $timestamp = Pikaid::parse($id)['timestamp']->getTimestamp();
    $this->assertGreaterThanOrEqual(
      $start,
      $timestamp,
      'Parsed timestamp should not be before generation start'
    );
    $this->assertLessThanOrEqual(
      $end,
      $timestamp,
      'Parsed timestamp should not be after generation end'
    );
  }

  #[DataProvider('knownTimestampProvider')]
  public function testParseKnownTimestamps(
    int $seconds,
    string $rand36,
    string $expectedHex
  ): void {
    $ts36 = base_convert((string) $seconds, 10, 36);
    $ts36 = str_pad($ts36, 7, '0', STR_PAD_LEFT);
    $id   = $ts36 . $rand36;

    $data = Pikaid::parse($id);
    $this->assertSame(
      $seconds,
      $data['timestamp']->getTimestamp(),
      'Parsed timestamp should match expected'
    );
    $this->assertSame(
      $expectedHex,
      $data['randomness'],
      'Parsed randomness hex should match expected'
    );
    $this->assertSame(
      24,
      strlen($data['randomness']),
      'Randomness hex length must be 24'
    );
  }

  public static function knownTimestampProvider(): array
  {
    $zeroHex    = str_repeat('0', 24);
    $zeroBase36 = str_repeat('0', 19);
    $maxSeconds = (int) (pow(36, 7) - 1);

    return [
      'epoch'       => [0, $zeroBase36, $zeroHex],
      'max seconds' => [$maxSeconds, $zeroBase36, $zeroHex],
    ];
  }

  public function testParseThrowsOnInvalidInput(): void
  {
    $this->expectException(InvalidArgumentException::class);
    Pikaid::parse('invalid_pikaid_format_!!!!!!!!!!!!');
  }

  public function testRandomnessPartIs19CharsBase36(): void
  {
    $id       = Pikaid::generate();
    $randPart = substr($id, 7);

    $this->assertSame(19, strlen($randPart));
    $this->assertMatchesRegularExpression('/^[0-9a-z]{19}$/', $randPart);
  }

  public function testMultipleGenerationsAreUnique(): void
  {
    $ids = [];
    for ($i = 0; $i < 1000; $i++) {
      $ids[] = Pikaid::generate();
    }

    $uniqueCount = count(array_unique($ids));
    $this->assertSame(
      1000,
      $uniqueCount,
      'All generated IDs in a batch should be unique'
    );
  }
}