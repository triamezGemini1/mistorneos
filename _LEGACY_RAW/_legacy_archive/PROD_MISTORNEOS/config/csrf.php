<?php
if (!defined('APP_BOOTSTRAPPED')) { require __DIR__ . '/bootstrap.php'; }

class CSRF {
  public static function token(): string {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }
  public static function input(): string {
    $t = self::token();
    return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($t, ENT_QUOTES, 'UTF-8').'">';
  }
  public static function validate(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $t = $_POST['csrf_token'] ?? '';
      if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
        http_response_code(400);
        die('CSRF validation failed');
      }
    }
  }
  public static function validateApi(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
      $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
      $sess = $_SESSION['csrf_token'] ?? '';
      if (!$hdr || !$sess || !hash_equals($sess, $hdr)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'CSRF token missing/invalid'], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
  }
}
