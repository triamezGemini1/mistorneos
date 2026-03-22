<?php



namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Lib\Security\Sanitizer;

/**
 * Tests para Sanitizer
 */
class SanitizerTest extends TestCase
{
    /**
     * @test
     */
    public function it_sanitizes_basic_strings(): void
    {
        $dirty = '<script>alert("XSS")</script>Hello World';
        $clean = Sanitizer::string($dirty);
        
        $this->assertEquals('Hello World', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
    }

    /**
     * @test
     */
    public function it_sanitizes_emails(): void
    {
        $email = 'Test@Example.COM';
        $clean = Sanitizer::email($email);
        
        $this->assertEquals('test@example.com', $clean);
    }

    /**
     * @test
     */
    public function it_rejects_invalid_emails(): void
    {
        $invalid = 'not-an-email';
        $clean = Sanitizer::email($invalid);
        
        $this->assertNull($clean);
    }

    /**
     * @test
     */
    public function it_sanitizes_integers(): void
    {
        $this->assertEquals(123, Sanitizer::int('123'));
        $this->assertEquals(0, Sanitizer::int('0'));
        $this->assertNull(Sanitizer::int('abc'));
        $this->assertNull(Sanitizer::int(''));
    }

    /**
     * @test
     */
    public function it_sanitizes_floats(): void
    {
        $this->assertEquals(123.45, Sanitizer::float('123.45'));
        $this->assertEquals(0.0, Sanitizer::float('0'));
        $this->assertNull(Sanitizer::float('not-a-number'));
    }

    /**
     * @test
     */
    public function it_sanitizes_booleans(): void
    {
        $this->assertTrue(Sanitizer::bool(true));
        $this->assertTrue(Sanitizer::bool('1'));
        $this->assertTrue(Sanitizer::bool('true'));
        $this->assertTrue(Sanitizer::bool('yes'));
        
        $this->assertFalse(Sanitizer::bool(false));
        $this->assertFalse(Sanitizer::bool('0'));
        $this->assertFalse(Sanitizer::bool('false'));
        $this->assertFalse(Sanitizer::bool('no'));
    }

    /**
     * @test
     */
    public function it_escapes_html_for_output(): void
    {
        $html = '<div class="test">Hello & "World"</div>';
        $escaped = Sanitizer::escape($html);
        
        $this->assertStringContainsString('&lt;', $escaped);
        $this->assertStringContainsString('&gt;', $escaped);
        $this->assertStringContainsString('&amp;', $escaped);
        $this->assertStringContainsString('&quot;', $escaped);
    }

    /**
     * @test
     */
    public function it_sanitizes_filenames(): void
    {
        $this->assertEquals('test.txt', Sanitizer::filename('test.txt'));
        $this->assertEquals('my_file-1.pdf', Sanitizer::filename('my_file-1.pdf'));
        
        // Path traversal attempts
        $this->assertEquals('test.txt', Sanitizer::filename('../../../test.txt'));
        $this->assertEquals('test.txt', Sanitizer::filename('../../test.txt'));
        
        // Special characters
        $this->assertEquals('test_____.txt', Sanitizer::filename('test@#$%&.txt'));
    }

    /**
     * @test
     */
    public function it_sanitizes_documents(): void
    {
        $this->assertEquals('12345678', Sanitizer::document('12345678'));
        $this->assertEquals('12345678-9', Sanitizer::document('12345678-9'));
        $this->assertEquals('12345678', Sanitizer::document('V-12345678'));
    }

    /**
     * @test
     */
    public function it_sanitizes_phone_numbers(): void
    {
        $this->assertEquals('+1 (555) 123-4567', Sanitizer::phone('+1 (555) 123-4567'));
        $this->assertEquals('+56912345678', Sanitizer::phone('+56912345678'));
        $this->assertEquals('555-1234', Sanitizer::phone('555-1234abc'));
    }

    /**
     * @test
     */
    public function it_sanitizes_dates(): void
    {
        $this->assertEquals('2025-10-04', Sanitizer::date('2025-10-04'));
        $this->assertNull(Sanitizer::date('invalid-date'));
        $this->assertNull(Sanitizer::date('2025-13-40')); // Invalid date
    }

    /**
     * @test
     */
    public function it_sanitizes_slugs(): void
    {
        $this->assertEquals('hello-world', Sanitizer::slug('Hello World'));
        $this->assertEquals('hello-world', Sanitizer::slug('  Hello   World  '));
        $this->assertEquals('hello-world-123', Sanitizer::slug('Hello-World-123'));
        $this->assertEquals('test', Sanitizer::slug('Test@#$%'));
    }

    /**
     * @test
     */
    public function it_sanitizes_arrays_recursively(): void
    {
        $dirty = [
            'name' => '<script>alert()</script>John',
            'age' => '25',
            'nested' => [
                'email' => 'Test@Example.COM'
            ]
        ];
        
        $clean = Sanitizer::array($dirty);
        
        $this->assertEquals('John', $clean['name']);
        $this->assertStringNotContainsString('<script>', $clean['name']);
    }

    /**
     * @test
     */
    public function it_sanitizes_request_data(): void
    {
        $input = [
            'name' => '<b>John</b>',
            'email' => 'TEST@EXAMPLE.COM',
            'age' => '30',
            'website' => 'http://example.com'
        ];
        
        $rules = [
            'name' => 'string',
            'email' => 'email',
            'age' => 'int',
            'website' => 'url'
        ];
        
        $sanitized = Sanitizer::sanitizeRequest($input, $rules);
        
        $this->assertEquals('John', $sanitized['name']);
        $this->assertEquals('test@example.com', $sanitized['email']);
        $this->assertEquals(30, $sanitized['age']);
        $this->assertEquals('http://example.com', $sanitized['website']);
    }

    /**
     * @test
     */
    public function it_handles_null_values_gracefully(): void
    {
        $this->assertNull(Sanitizer::string(null));
        $this->assertNull(Sanitizer::email(null));
        $this->assertNull(Sanitizer::int(null));
        $this->assertNull(Sanitizer::float(null));
        $this->assertEquals('', Sanitizer::escape(null));
    }

    /**
     * @test
     */
    public function it_removes_null_bytes(): void
    {
        $malicious = "test\0string";
        $clean = Sanitizer::string($malicious);
        
        $this->assertStringNotContainsString("\0", $clean);
        $this->assertEquals('teststring', $clean);
    }
}







