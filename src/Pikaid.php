<?php

declare(strict_types=1);

namespace Pikaid;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

class Pikaid
{
  public const LENGTH       = 26;
  private const TS_LENGTH   = 7;
  private const RAND_LENGTH = 19;
  private const RAND_BYTES  = 12;

  /**
   * Generate a new pikaid (26 lowercase Base36 chars).
   */
  public static function generate(): string
  {
    // 1) Timestamp part (seconds since epoch → Base36)
    $seconds = time();
    $ts36    = base_convert((string) $seconds, 10, 36);
    $ts36    = str_pad($ts36, self::TS_LENGTH, '0', STR_PAD_LEFT);

    // 2) Randomness part (96 bits → Base36)
    $bytes   = random_bytes(self::RAND_BYTES);
    $rand36  = self::toBase36($bytes);
    $rand36  = str_pad($rand36, self::RAND_LENGTH, '0', STR_PAD_LEFT);

    return $ts36 . $rand36;
  }

  /**
   * Check if a pikaid string is valid.
   */
  public static function isValid(string $id): bool
  {
    return strlen($id) === self::LENGTH
      && preg_match('/^[0-9a-z]{26}$/', $id) === 1;
  }

  /**
   * Parse a pikaid into timestamp and randomness.
   *
   * @return array{timestamp: DateTimeImmutable, randomness: string}
   * @throws InvalidArgumentException on invalid format
   */
  public static function parse(string $id): array
  {
    if (!self::isValid($id)) {
      throw new InvalidArgumentException('Invalid pikaid format');
    }

    $tsPart   = substr($id, 0, self::TS_LENGTH);
    $randPart = substr($id, self::TS_LENGTH);

    // --- Decode timestamp (Base36 → seconds → DateTimeImmutable) ---
    $seconds = (int) base_convert($tsPart, 36, 10);
    $date    = new DateTimeImmutable("@{$seconds}");
    $date    = $date->setTimezone(new DateTimeZone('UTC'));

    // --- Decode randomness to hex string ---
    $bytes = self::fromBase36($randPart);
    $hex   = bin2hex($bytes);

    return [
      'timestamp'  => $date,
      'randomness' => $hex,
    ];
  }

  /**
   * Convert binary string to Base36, using GMP or BCMath as fallback.
   */
  private static function toBase36(string $bytes): string
  {
    if (extension_loaded('gmp')) {
      $num = gmp_import($bytes, 1, GMP_BIG_ENDIAN);
      return gmp_strval($num, 36);
    }

    if (!extension_loaded('bcmath')) {
      throw new RuntimeException('Either GMP or BCMath extension is required');
    }

    // Fallback: hex → decimal (BCMath) → Base36
    $hex = bin2hex($bytes);
    $dec = '0';

    foreach (str_split($hex, 1) as $digit) {
      $dec = bcmul($dec, '16', 0);
      $dec = bcadd($dec, (string) hexdec($digit), 0);
    }

    $out = '';
    while (bccomp($dec, '0', 0) === 1) {
      $mod  = bcmod($dec, '36');
      $out  = self::digit((int) $mod) . $out;
      $dec  = bcdiv($dec, '36', 0);
    }

    return $out;
  }

  /**
   * Convert Base36 string back to binary (12 bytes).
   */
  private static function fromBase36(string $str): string
  {
    if (extension_loaded('gmp')) {
      $num   = gmp_init($str, 36);
      $bytes = gmp_export($num, 1, GMP_BIG_ENDIAN);
      return str_pad($bytes, self::RAND_BYTES, "\0", STR_PAD_LEFT);
    }

    if (!extension_loaded('bcmath')) {
      throw new RuntimeException('Either GMP or BCMath extension is required');
    }

    // Base36 → decimal
    $dec = '0';
    foreach (str_split($str, 1) as $char) {
      $dec = bcmul($dec, '36', 0);
      $dec = bcadd($dec, (string) self::value($char), 0);
    }

    // Decimal → hex
    $hex = '';
    while (bccomp($dec, '0', 0) === 1) {
      $mod = bcmod($dec, '16');
      $hex = dechex((int) $mod) . $hex;
      $dec = bcdiv($dec, '16', 0);
    }

    $hex = str_pad($hex, self::RAND_BYTES * 2, '0', STR_PAD_LEFT);
    return hex2bin($hex) ?: str_repeat("\0", self::RAND_BYTES);
  }

  /**
   * Map integer to Base36 digit (0–9, a–z).
   */
  private static function digit(int $value): string
  {
    return $value < 10
      ? (string) $value
      : chr(97 + $value - 10);
  }

  /**
   * Map Base36 digit character to integer value.
   */
  private static function value(string $char): int
  {
    $code = ord($char);
    return $code < 58
      ? $code - 48
      : $code - 87;
  }
}
