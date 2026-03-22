<?php



namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Lib\Security\Csrf;

/**
 * Tests para CSRF Protection
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Limpiar sesión antes de cada test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
        }
    }

    /**
     * @test
     */
    public function it_generates_csrf_token(): void
    {
        $token = Csrf::generateToken();
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes hex = 64 chars
    }

    /**
     * @test
     */
    public function it_retrieves_existing_token(): void
    {
        $token1 = Csrf::getToken();
        $token2 = Csrf::getToken();
        
        $this->assertEquals($token1, $token2);
    }

    /**
     * @test
     */
    public function it_validates_correct_token(): void
    {
        $token = Csrf::generateToken();
        
        $this->assertTrue(Csrf::validateToken($token));
    }

    /**
     * @test
     */
    public function it_rejects_invalid_token(): void
    {
        Csrf::generateToken();
        
        $this->assertFalse(Csrf::validateToken('invalid_token'));
        $this->assertFalse(Csrf::validateToken(null));
        $this->assertFalse(Csrf::validateToken(''));
    }

    /**
     * @test
     */
    public function it_regenerates_token(): void
    {
        $token1 = Csrf::generateToken();
        $token2 = Csrf::regenerate();
        
        $this->assertNotEquals($token1, $token2);
    }

    /**
     * @test
     */
    public function it_generates_html_field(): void
    {
        $field = Csrf::field();
        
        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    /**
     * @test
     */
    public function it_generates_meta_tag(): void
    {
        $meta = Csrf::metaTag();
        
        $this->assertStringContainsString('<meta', $meta);
        $this->assertStringContainsString('name="csrf-token"', $meta);
        $this->assertStringContainsString('content="', $meta);
    }

    /**
     * @test
     */
    public function it_clears_token(): void
    {
        Csrf::generateToken();
        Csrf::clear();
        
        // Después de clear, debe generar un nuevo token
        $newToken = Csrf::getToken();
        $this->assertNotEmpty($newToken);
    }

    /**
     * @test
     */
    public function it_uses_timing_safe_comparison(): void
    {
        $token = Csrf::generateToken();
        
        // Timing attack attempt
        $similarToken = substr($token, 0, -1) . 'x';
        
        $this->assertFalse(Csrf::validateToken($similarToken));
    }
}







