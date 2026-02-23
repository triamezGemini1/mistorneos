<?php
/**
 * Definición de rutas de la aplicación
 * 
 * Este archivo registra las rutas usando el Router moderno.
 * Las rutas legacy (?page=xxx) siguen funcionando por compatibilidad.
 */

use Core\Routing\Router;
use Core\Middleware\AuthMiddleware;
use Core\Middleware\RateLimitMiddleware;

/**
 * @param Router $router
 */
return function (Router $router) {
    
    // =================================================================
    // RUTAS PÚBLICAS (sin autenticación)
    // =================================================================
    
    // Landing page
    $router->get('/', function() {
        include __DIR__ . '/../public/landing.php';
        return '';
    });
    
    // Login con rate limiting (5 intentos por minuto)
    $router->group(['prefix' => '/auth', 'middleware' => [new RateLimitMiddleware(5, 60)]], function($router) {
        $router->match(['GET', 'POST'], '/login', function() {
            include __DIR__ . '/../modules/auth/login.php';
            return '';
        });
        
        $router->get('/logout', function() {
            include __DIR__ . '/../modules/auth/logout.php';
            return '';
        });
        
        $router->match(['GET', 'POST'], '/forgot-password', function() {
            include __DIR__ . '/../modules/auth/forgot_password.php';
            return '';
        });

        $router->match(['GET', 'POST'], '/recover-user', function() {
            include __DIR__ . '/../modules/auth/recover_user.php';
            return '';
        });
        
        $router->match(['GET', 'POST'], '/reset-password', function() {
            include __DIR__ . '/../modules/auth/reset_password.php';
            return '';
        });
    });
    
    // API pública: envío de resultados de mesa con foto de acta
    $router->post('/actions/public-score-submit', function() {
        include __DIR__ . '/../actions/public_score_submit.php';
        return '';
    });
    
    // Registro de invitaciones (público)
    $router->group(['prefix' => '/invitation'], function($router) {
        $router->match(['GET', 'POST'], '/register', function() {
            include __DIR__ . '/../modules/invitation_register.php';
            return '';
        });
        
        $router->match(['GET', 'POST'], '/register-select', function() {
            include __DIR__ . '/../modules/invitation_register_select.php';
            return '';
        });

        // Tarjeta de invitación digital (pública por token)
        $router->get('/digital', function() {
            include __DIR__ . '/../modules/invitacion_digital.php';
            return '';
        });

        // Descarga PDF de la invitación digital (pública por token)
        $router->get('/digital/pdf', function() {
            include __DIR__ . '/../modules/invitacion_digital_pdf.php';
            return '';
        });
    });
    
    // =================================================================
    // RUTAS PROTEGIDAS (requieren autenticación)
    // =================================================================
    
    $router->group(['middleware' => [new AuthMiddleware(['admin_general', 'admin_torneo'])]], function($router) {
        
        // Dashboard principal
        $router->get('/dashboard', function() {
            include __DIR__ . '/../modules/home.php';
            return '';
        });
        
        // API endpoints
        $router->group(['prefix' => '/api'], function($router) {
            $router->get('/tournament-stats', function() {
                include __DIR__ . '/../api/tournament_stats.php';
                return '';
            });
            
            $router->get('/search-persona', function() {
                include __DIR__ . '/../api/search_persona.php';
                return '';
            });
        });
    });
    
    // =================================================================
    // RUTAS DE ADMIN GENERAL
    // =================================================================
    
    $router->group(['prefix' => '/admin', 'middleware' => [new AuthMiddleware(['admin_general'])]], function($router) {
        
        // Gestión de usuarios
        $router->get('/users', function() {
            include __DIR__ . '/../modules/users.php';
            return '';
        });
        
        // Gestión de clubes
        $router->get('/clubs', function() {
            include __DIR__ . '/../modules/clubs.php';
            return '';
        });

        // Reporte de actividad (auditoría) — redirige a legacy para usar el layout
        $router->get('/auditoria', function() {
            require_once __DIR__ . '/../lib/app_helpers.php';
            header('Location: ' . \AppHelpers::dashboard('auditoria'));
            exit;
        });
    });
};


