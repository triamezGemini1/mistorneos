<?php


function json_ok($data = [], int $code=200, $meta=null) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  $out = ['ok' => true, 'data' => $data];
  if ($meta !== null) $out['meta'] = $meta;
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}
function json_err(string $message, int $code=400, $meta=null) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  $out = ['ok' => false, 'error' => $message];
  if ($meta !== null) $out['meta'] = $meta;
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}

function get_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') { return []; }
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function page_params(int $default=20, int $max=100): array {
  $page = max(1, (int)($_GET['page'] ?? 1));
  $per  = (int)($_GET['per_page'] ?? $default);
  if ($per < 1) $per = 1;
  if ($per > $max) $per = $max;
  $offset = ($page - 1) * $per;
  return [$page, $per, $offset];
}
