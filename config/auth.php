<?php
if (!defined('APP_BOOTSTRAPPED')) { require __DIR__ . '/bootstrap.php'; }
require_once __DIR__ . '/db.php';

class Auth {
  public static function login(string $username, string $password): bool {
    require_once __DIR__ . '/../lib/security.php';
    
    $user = Security::authenticateUser($username, $password);
    if ($user) {
      $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'email' => $user['email'],
        'uuid' => $user['uuid'],
        'photo_path' => $user['photo_path'],
        'club_id' => $user['club_id'],
        'entidad' => isset($user['entidad']) ? (int)$user['entidad'] : 0
      ];
      session_regenerate_id(true);
      return true;
    }
    return false;
  }

  public static function logout(): void {
    // Limpiar toda la sesi�n primero
    $_SESSION = [];
    
    // Destruir la sesi�n actual
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_unset();
      session_destroy();
    }
    
    // Limpiar cookies de sesi�n si est�n habilitadas
    if (!headers_sent()) {
      $params = session_get_cookie_params();
      
      // Eliminar la cookie de sesi�n principal
      setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
      );
      
      // Eliminar otras cookies relacionadas con la sesi�n si existen
      if (isset($_COOKIE[session_name()])) {
        unset($_COOKIE[session_name()]);
      }
      
      // Limpiar headers adicionales para seguridad
      header_remove('Set-Cookie');
    }
    
    // Limpiar array de cookies
    if (isset($_COOKIE)) {
      foreach ($_COOKIE as $name => $value) {
        if (strpos($name, session_name()) === 0) {
          unset($_COOKIE[$name]);
        }
      }
    }
  }

  /**
   * ID del usuario actual (único punto de acceso, evita inconsistencia user_id/id).
   * @return int 0 si no hay sesión.
   */
  public static function id(): int {
    $u = $_SESSION['user'] ?? null;
    if (!$u) {
      return 0;
    }
    return (int)($u['id'] ?? $u['user_id'] ?? 0);
  }

  public static function user(): ?array {
    $u = $_SESSION['user'] ?? null;
    if ($u !== null && !isset($u['id']) && isset($u['user_id'])) {
      $u['id'] = $u['user_id'];
      $_SESSION['user'] = $u;
    }
    return $u;
  }

  public static function requireRole(array $roles): void {
    $u = self::user();
    if (!$u || !in_array($u['role'], $roles, true)) {
      // Redirigir a una p�gina de error en lugar de establecer c�digo de respuesta
      if (!headers_sent()) {
        $base = class_exists('AppHelpers') && method_exists('AppHelpers', 'getRequestEntryUrl') ? AppHelpers::getRequestEntryUrl() : rtrim(app_base_url(), '/') . '/public';
        header('Location: ' . $base . '/access_denied.php');
        exit;
      } else {
        // Si los headers ya se enviaron, mostrar mensaje de error
        echo '<div class="alert alert-danger text-center mt-4">';
        echo '<h4>Acceso Denegado</h4>';
        echo '<p>No tienes permisos para acceder a esta secci�n.</p>';
        $base = class_exists('AppHelpers') && method_exists('AppHelpers', 'getRequestEntryUrl') ? AppHelpers::getRequestEntryUrl() : rtrim(app_base_url(), '/') . '/public';
        echo '<a href="' . $base . '/index.php?page=registrants" class="btn btn-primary">Ir a Inscripciones</a>';
        echo '</div>';
        exit;
      }
    }
  }

  public static function requireRoleOrTournamentResponsible(array $roles, ?int $tournament_id = null): void {
    $u = self::user();
    
    // Si tiene alguno de los roles especificados, permitir acceso
    if ($u && in_array($u['role'], $roles, true)) {
      return;
    }
    
    // Si el usuario es admin_club (admin organización) y hay torneo_id, verificar por organización
    if ($u && $u['role'] === 'admin_club' && $tournament_id !== null) {
      try {
        $stmt = DB::pdo()->prepare("SELECT club_responsable FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_id]);
        $tournament = $stmt->fetch();
        if (!$tournament) {
          // Nada que permitir
        } else {
          $org_id = self::getUserOrganizacionId();
          if ($org_id && (int)$tournament['club_responsable'] === (int)$org_id) {
            return;
          }
          if (!empty($u['club_id']) && $tournament['club_responsable'] == $u['club_id']) {
            return;
          }
        }
      } catch (Exception $e) {
        // Error al verificar torneo, denegar acceso por seguridad
      }
    }
    
    // Si llega aqu�, no tiene permisos
    if (!headers_sent()) {
      header('Location: ' . app_base_url() . '/public/access_denied.php');
      exit;
    } else {
      echo '<div class="alert alert-danger text-center mt-4">';
      echo '<h4>Acceso Denegado</h4>';
      echo '<p>No tienes permisos para acceder a esta secci�n.</p>';
      echo '<a href="' . app_base_url() . '/public/index.php?page=registrants" class="btn btn-primary">Ir a Inscripciones</a>';
      echo '</div>';
      exit;
    }
  }

  /**
   * Verifica si el usuario actual es admin_general
   * @return bool
   */
  public static function isAdminGeneral(): bool {
    $u = self::user();
    return $u && $u['role'] === 'admin_general';
  }

  /**
   * Verifica si el usuario actual es admin_torneo
   * @return bool
   */
  public static function isAdminTorneo(): bool {
    $u = self::user();
    return $u && $u['role'] === 'admin_torneo';
  }

  /**
   * Verifica si el usuario actual es admin_club
   * @return bool
   */
  public static function isAdminClub(): bool {
    $u = self::user();
    return $u && $u['role'] === 'admin_club';
  }

  /**
   * Obtiene el club_id del usuario actual
   * @return int|null
   */
  public static function getUserClubId(): ?int {
    $u = self::user();
    return $u['club_id'] ?? null;
  }

  /** Cache por petición para evitar consultas repetidas */
  private static $cached_organizacion_id = null;
  private static $cached_user_clubes = null;
  private static $cached_dashboard_organizacion = null;

  /**
   * Datos de la organización/club para mostrar en el dashboard (logo + nombre).
   * Solo cuando el usuario NO es admin_general.
   * @return array|null ['nombre' => string, 'logo' => string|null] o null para admin_general/sin org
   */
  public static function getDashboardOrganizacion(): ?array {
    if (self::$cached_dashboard_organizacion !== null) {
      return self::$cached_dashboard_organizacion;
    }
    $u = self::user();
    if (!$u || $u['role'] === 'admin_general') {
      self::$cached_dashboard_organizacion = null;
      return null;
    }
    try {
      if ($u['role'] === 'admin_club') {
        $stmt = DB::pdo()->prepare("SELECT nombre, logo FROM organizaciones WHERE admin_user_id = ? AND estatus = 1 LIMIT 1");
        $stmt->execute([self::id()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          self::$cached_dashboard_organizacion = ['nombre' => $row['nombre'], 'logo' => $row['logo'] ?: null];
          return self::$cached_dashboard_organizacion;
        }
      }
      if ($u['role'] === 'admin_torneo' && !empty($u['club_id'])) {
        $stmt = DB::pdo()->prepare("SELECT c.nombre, c.logo AS club_logo, o.nombre AS org_nombre, o.logo AS org_logo FROM clubes c LEFT JOIN organizaciones o ON c.organizacion_id = o.id AND o.estatus = 1 WHERE c.id = ? LIMIT 1");
        $stmt->execute([$u['club_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $nombre = !empty($row['org_nombre']) ? $row['org_nombre'] : $row['nombre'];
          $logo = !empty($row['org_logo']) ? $row['org_logo'] : ($row['club_logo'] ?? null);
          self::$cached_dashboard_organizacion = ['nombre' => $nombre, 'logo' => $logo];
          return self::$cached_dashboard_organizacion;
        }
      }
      if (($u['role'] === 'operador' || $u['role'] === 'usuario') && !empty($u['club_id'])) {
        $stmt = DB::pdo()->prepare("SELECT c.nombre, c.logo AS club_logo, o.nombre AS org_nombre, o.logo AS org_logo FROM clubes c LEFT JOIN organizaciones o ON c.organizacion_id = o.id AND o.estatus = 1 WHERE c.id = ? LIMIT 1");
        $stmt->execute([$u['club_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $nombre = !empty($row['org_nombre']) ? $row['org_nombre'] : $row['nombre'];
          $logo = !empty($row['org_logo']) ? $row['org_logo'] : ($row['club_logo'] ?? null);
          self::$cached_dashboard_organizacion = ['nombre' => $nombre, 'logo' => $logo];
          return self::$cached_dashboard_organizacion;
        }
      }
    } catch (Exception $e) {
      self::$cached_dashboard_organizacion = null;
      return null;
    }
    self::$cached_dashboard_organizacion = null;
    return null;
  }

  /**
   * Obtiene el ID de la organización del admin_club actual
   * @return int|null
   */
  public static function getUserOrganizacionId(): ?int {
    if (self::$cached_organizacion_id !== null) {
      return self::$cached_organizacion_id;
    }
    $u = self::user();
    if (!$u || $u['role'] !== 'admin_club') {
      self::$cached_organizacion_id = null;
      return null;
    }
    try {
      $stmt = DB::pdo()->prepare("SELECT id FROM organizaciones WHERE admin_user_id = ? AND estatus = 1 LIMIT 1");
      $stmt->execute([self::id()]);
      $org_id = $stmt->fetchColumn();
      self::$cached_organizacion_id = $org_id ? (int)$org_id : null;
      return self::$cached_organizacion_id;
    } catch (Exception $e) {
      self::$cached_organizacion_id = null;
      return null;
    }
  }

  /**
   * Verifica si un torneo pertenece al club del admin_torneo o admin_club
   * Para admin_club, verifica por organización (no requiere club_id)
   * @param int $tournament_id
   * @return bool
   */
  public static function canAccessTournament(int $tournament_id): bool {
    $u = self::user();
    
    // Admin general puede acceder a todo
    if (self::isAdminGeneral()) {
      return true;
    }
    
    // Admin club: verifica por organización (club_responsable = ID de organización)
    if (self::isAdminClub()) {
      $org_id = self::getUserOrganizacionId();
      
      if (!$org_id) {
        return false;
      }
      
      try {
        // club_responsable ahora contiene el ID de la organización
        $stmt = DB::pdo()->prepare("SELECT club_responsable FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_id]);
        $tournament = $stmt->fetch();
        
        if (!$tournament) {
          return false;
        }
        
        return $tournament['club_responsable'] == $org_id;
      } catch (Exception $e) {
        return false;
      }
    }
    
    // Admin torneo: solo su club directo
    if (self::isAdminTorneo()) {
      $user_club_id = self::getUserClubId();
      
      if (!$user_club_id) {
        return false;
      }
      
      try {
        $stmt = DB::pdo()->prepare("SELECT club_responsable FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_id]);
        $tournament = $stmt->fetch();
        
        if (!$tournament) {
          return false;
        }
        
        return $tournament['club_responsable'] == $user_club_id;
      } catch (Exception $e) {
        return false;
      }
    }
    
    // Usuario (jugador): puede acceder solo a torneos en los que está inscrito (para ver posiciones o su resumen)
    if (($u['role'] ?? '') === 'usuario') {
      try {
        $uid = self::id();
        if ($uid <= 0) return false;
        $stmt = DB::pdo()->prepare("SELECT 1 FROM inscritos WHERE torneo_id = ? AND id_usuario = ? AND (estatus IS NULL OR estatus != 'retirado') LIMIT 1");
        $stmt->execute([$tournament_id, $uid]);
        return $stmt->fetch() !== false;
      } catch (Exception $e) {
        return false;
      }
    }
    
    return false;
  }

  /**
   * Verifica si un torneo ya pas� (fechator < hoy)
   * @param int $tournament_id
   * @return bool
   */
  public static function isTournamentPast(int $tournament_id): bool {
    try {
      $stmt = DB::pdo()->prepare("SELECT fechator FROM tournaments WHERE id = ?");
      $stmt->execute([$tournament_id]);
      $tournament = $stmt->fetch();
      
      if (!$tournament || !$tournament['fechator']) {
        return false;
      }
      
      $fecha_torneo = strtotime($tournament['fechator']);
      $hoy = strtotime(date('Y-m-d'));
      
      return $fecha_torneo < $hoy;
    } catch (Exception $e) {
      return false;
    }
  }

  /**
   * Verifica si el admin_torneo o admin_club puede modificar un torneo
   * (debe ser de su club Y no debe haber pasado)
   * @param int $tournament_id
   * @return bool
   */
  public static function canModifyTournament(int $tournament_id): bool {
    // Admin general puede modificar todo
    if (self::isAdminGeneral()) {
      return true;
    }
    
    // Admin torneo y admin organización tienen restricciones
    if (self::isAdminTorneo() || self::isAdminClub()) {
      // Debe ser de su club
      if (!self::canAccessTournament($tournament_id)) {
        return false;
      }
      
      // No debe haber pasado
      if (self::isTournamentPast($tournament_id)) {
        return false;
      }
      
      return true;
    }
    
    return false;
  }

  /**
   * Agrega filtro WHERE para limitar torneos según el rol del usuario
   * Retorna array con ['where' => string, 'params' => array]
   * @param string $table_alias Alias de la tabla tournaments (ej: 't')
   * @return array
   */
  public static function getTournamentFilterForRole(string $table_alias = 't'): array {
    $u = self::user();
    
    // Admin general ve todo
    if (self::isAdminGeneral()) {
      return ['where' => '', 'params' => []];
    }
    
    // Admin torneo solo ve torneos de su club directo
    if (self::isAdminTorneo()) {
      $user_club_id = self::getUserClubId();
      
      if (!$user_club_id) {
        return ['where' => "{$table_alias}.club_responsable = ?", 'params' => [0]];
      }
      
      return [
        'where' => "{$table_alias}.club_responsable = ?",
        'params' => [$user_club_id]
      ];
    }
    
    // Admin club ve torneos de su organización (club_responsable = ID de organización)
    if (self::isAdminClub()) {
      $org_id = self::getUserOrganizacionId();
      
      if (!$org_id) {
        return ['where' => "{$table_alias}.club_responsable = ?", 'params' => [0]];
      }
      
      // Torneos de su organización
      return [
        'where' => "{$table_alias}.club_responsable = ?",
        'params' => [$org_id]
      ];
    }
    
    return ['where' => '1=0', 'params' => []]; // Denegar acceso por defecto
  }

  /**
   * Agrega filtro WHERE para limitar clubes según el rol del usuario
   * Retorna array con ['where' => string, 'params' => array]
   * @param string $table_alias Alias de la tabla clubs (ej: 'c'), vacío para sin alias
   * @return array
   */
  public static function getClubFilterForRole(string $table_alias = ''): array {
    $u = self::user();
    $col = $table_alias ? "{$table_alias}.id" : "id";
    
    // Admin general ve todo
    if (self::isAdminGeneral()) {
      return ['where' => '', 'params' => []];
    }
    
    // Admin torneo ve solo su club
    if (self::isAdminTorneo()) {
      $user_club_id = self::getUserClubId();
      
      if (!$user_club_id) {
        return ['where' => "{$col} = ?", 'params' => [0]]; // No verá nada
      }
      
      return [
        'where' => "{$col} = ?",
        'params' => [$user_club_id]
      ];
    }
    
    // Admin club ve sus clubes supervisados
    if (self::isAdminClub()) {
      $clubes = self::getUserClubes();
      
      if (empty($clubes)) {
        return ['where' => "{$col} = ?", 'params' => [0]]; // No verá nada
      }
      
      $placeholders = implode(',', array_fill(0, count($clubes), '?'));
      return [
        'where' => "{$col} IN ($placeholders)",
        'params' => $clubes
      ];
    }
    
    return ['where' => '1=0', 'params' => []]; // Denegar acceso por defecto
  }

  /**
   * Genera un UUID v4 simple
   * @return string
   */
  public static function generateUUID(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
  }

  /**
   * Obtiene la credencial UUID del usuario actual
   * @return string|null
   */
  public static function getUserCredential(): ?string {
    $u = self::user();
    return $u['uuid'] ?? null;
  }

  /**
   * Verifica si se están usando las credenciales por defecto
   * @param string $username
   * @param string $password
   * @return bool
   */
  public static function isUsingDefaultCredentials(string $username, string $password): bool {
    // Lista de credenciales por defecto conocidas
    $defaultCredentials = [
      ['username' => 'admin', 'password' => 'admin123'],
      ['username' => 'admin', 'password' => 'password'],
      ['username' => 'admin', 'password' => '123456'],
    ];
    
    foreach ($defaultCredentials as $cred) {
      if (strtolower($username) === strtolower($cred['username']) && $password === $cred['password']) {
        return true;
      }
    }
    
    return false;
  }

  /**
   * Verifica si el usuario actual debe cambiar su contraseña
   * @return bool
   */
  public static function mustChangePassword(): bool {
    return isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true;
  }

  /**
   * Limpia el flag de cambio de contraseña obligatorio
   */
  public static function clearPasswordChangeFlag(): void {
    unset($_SESSION['force_password_change']);
    unset($_SESSION['password_change_reason']);
  }

  /**
   * Obtiene todos los clubes que supervisa el usuario actual
   * Incluye club principal + asociados. Cache por petición.
   * @return array Lista de IDs de clubes
   */
  public static function getUserClubes(): array {
    if (self::$cached_user_clubes !== null) {
      return self::$cached_user_clubes;
    }
    $u = self::user();
    if (!$u) {
      self::$cached_user_clubes = [];
      return [];
    }
    if ($u['role'] === 'admin_general') {
      try {
        $stmt = DB::pdo()->query("SELECT id FROM clubes WHERE estatus = 1");
        self::$cached_user_clubes = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return self::$cached_user_clubes;
      } catch (Exception $e) {
        self::$cached_user_clubes = [];
        return [];
      }
    }
    require_once __DIR__ . '/../lib/ClubHelper.php';
    // Admin organización: clubes de su organización (organizaciones.admin_user_id → clubes.organizacion_id)
    $clubes = ClubHelper::getClubesByAdminClubId(self::id());
    if (empty($clubes) && !empty($u['club_id'])) {
      $clubes = ClubHelper::getClubesSupervised($u['club_id']);
    }
    self::$cached_user_clubes = $clubes;
    return $clubes;
  }

  /**
   * Verifica si el usuario puede gestionar un club específico
   * @param int $club_id
   * @return bool
   */
  public static function canManageClub(int $club_id): bool {
    $u = self::user();
    if (!$u) return false;
    
    if ($u['role'] === 'admin_general') return true;
    
    $clubes = self::getUserClubes();
    return in_array($club_id, $clubes);
  }
}

