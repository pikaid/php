<?php

declare(strict_types=1);

namespace Pikaid;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

class Pikaid
{
  public const LENGTH = 26;
  private const TS_LENGTH = 7;
  private const RAND_LENGTH = 19;
  private const RAND_BYTES = 12;
  private static ?bool $hasGmp = null;
  private static ?bool $hasBcmath = null;
  private static ?DateTimeZone $utcZone = null;
  private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';
  private static array $alphabetMap = [];

  /**
   * Generate a new pikaid (26 lowercase Base36 chars).
   */
  public static function generate(): string
  {
    // 1) Timestamp part (seconds since epoch → Base36)
    $seconds = time();
    $ts36 = base_convert((string) $seconds, 10, 36);
    $ts36 = str_pad($ts36, self::TS_LENGTH, '0', STR_PAD_LEFT);

    // 2) Randomness part (96 bits → Base36)
    $bytes = random_bytes(self::RAND_BYTES);
    $rand36 = self::toBase36($bytes);
    $rand36 = str_pad($rand36, self::RAND_LENGTH, '0', STR_PAD_LEFT);

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

  private static function getUtcZone(): DateTimeZone
  {
    return self::$utcZone ??= new DateTimeZone('UTC');
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

    $tsPart = substr($id, 0, self::TS_LENGTH);
    $randPart = substr($id, self::TS_LENGTH);

    // --- Decode timestamp (Base36 → seconds → DateTimeImmutable) ---
    $seconds = (int) base_convert($tsPart, 36, 10);
    $date = (new DateTimeImmutable("@{$seconds}"))
      ->setTimezone(self::getUtcZone());

    // --- Decode randomness to hex string ---
    $bytes = self::fromBase36($randPart);
    $hex = bin2hex($bytes);

    return [
      'timestamp' => $date,
      'randomness' => $hex,
    ];
  }

  private static function initExtensions(): void
  {
    if (self::$hasGmp === null) {
      self::$hasGmp = extension_loaded('gmp');
      self::$hasBcmath = extension_loaded('bcmath');
    }
  }

  /**
   * Divide a hex string by an integer divisor.
   *
   * @param string $hex       Hex string (no “0x”, lowercase or uppercase)
   * @param int    $divisor   Integer divisor (e.g. 36)
   * @return array{0:string,1:int}  [quotientHex, remainder]
   */
  private static function bcHexDivMod(string $hex, int $divisor): array
  {
    // Remove leading zeros
    $hex = ltrim($hex, '0');
    if ($hex === '') {
      return ['0', 0];
    }

    $quotient = '';
    $remainder = 0;

    for ($i = 0, $len = strlen($hex); $i < $len; $i++) {
      // Multiply previous remainder by 16 and add current hex digit
      $remainder = ($remainder << 4) + hexdec($hex[$i]);

      // Compute quotient digit and new remainder
      $qDigit = intdiv($remainder, $divisor);
      $remainder = $remainder % $divisor;

      // Append hex digit of the partial quotient
      $quotient .= dechex($qDigit);
    }

    // Strip leading zeros from quotient, ensure at least "0"
    $quotient = ltrim($quotient, '0');
    if ($quotient === '') {
      $quotient = '0';
    }
    return [$quotient, $remainder];
  }

  /**
   * Convert binary string to Base36, using GMP or BCMath as fallback.
   */
  private static function toBase36(string $bytes): string
  {
    self::initExtensions();
    if (self::$hasGmp) {
      $num = gmp_import($bytes, 1, GMP_BIG_ENDIAN);
      return gmp_strval($num, 36);
    }
    if (!self::$hasBcmath) {
      throw new RuntimeException('Require GMP or BCMath');
    }
    // Fallback BCMath optimized: hex‐string division
    $hex = bin2hex($bytes);
    $out = '';
    while ($hex !== '' && $hex !== '0') {
      [$hex, $mod] = self::bcHexDivMod($hex, 36);
      $out = self::digit($mod) . $out;
    }
    return $out;
  }

  /**
   * Convert Base36 string back to binary (12 bytes).
   */
  private static function fromBase36(string $str): string
  {
    self::initExtensions();
    if (self::$hasGmp) {
      $num = gmp_init($str, 36);
      $bytes = gmp_export($num, 1, GMP_BIG_ENDIAN);
      return str_pad($bytes, self::RAND_BYTES, "\0", STR_PAD_LEFT);
    }
    if (!self::$hasBcmath) {
      throw new RuntimeException('Require GMP or BCMath');
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

  private static function initAlphabet(): void
  {
    if (empty(self::$alphabetMap)) {
      self::$alphabetMap = array_flip(str_split(self::ALPHABET));
    }
  }

  /**
   * Map integer to Base36 digit (0–9, a–z).
   */
  private static function digit(int $value): string
  {
    return self::ALPHABET[$value];
  }

  /**
   * Map Base36 digit character to integer value.
   */
  private static function value(string $char): int
  {
    self::initAlphabet();
    return self::$alphabetMap[$char];
  }
}