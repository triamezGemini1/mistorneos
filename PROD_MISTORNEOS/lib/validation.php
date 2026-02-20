<?php


class V {
  public static function str(string $v, int $min=0, int $max=255): string {
    $v = trim($v);
    if (mb_strlen($v) < $min || mb_strlen($v) > $max) {
      throw new InvalidArgumentException("String length must be between $min and $max");
    }
    return $v;
  }
  public static function email(?string $v): ?string {
    if ($v === null || $v === '') return null;
    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException("Invalid email");
    return strtolower($v);
  }
  public static function phone(?string $v): ?string {
    if ($v === null || $v === '') return null;
    $v = preg_replace('/[^0-9+\-\s]/', '', $v);
    return $v;
  }
  public static function int($v, int $min=null, int $max=null): int {
    if (!is_numeric($v)) throw new InvalidArgumentException("Invalid int");
    $i = (int)$v;
    if ($min !== null && $i < $min) throw new InvalidArgumentException("Min $min");
    if ($max !== null && $i > $max) throw new InvalidArgumentException("Max $max");
    return $i;
  }
  public static function date(?string $v): ?string {
    if ($v === null || $v === '') return null;
    $d = date_create_from_format('Y-m-d', $v);
    if (!$d) throw new InvalidArgumentException("Invalid date");
    return $v;
  }
  public static function enum($v, array $allowed) {
    if (!in_array($v, $allowed, true)) throw new InvalidArgumentException("Invalid enum");
    return $v;
  }
}
