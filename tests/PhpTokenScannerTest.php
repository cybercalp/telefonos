<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/PhpTokenScanner.php';
use Tests\PhpTokenScanner;

class PhpTokenScannerTest extends TestCase
{
    public function testScannerIgnoresWhitespaceAndComments(): void
    {
        $code = '<?php // Comment
        $a = "hello"; /* Block comment */';
        $scanner = new PhpTokenScanner($code);
        
        $sequence = ['$a', '=', 'hello'];
        $this->assertTrue($scanner->hasSequence($sequence));
        $this->assertEquals(1, $scanner->countSequence($sequence));
    }

    public function testScannerFeatureTriangulation(): void
    {
        $code = '<?php
        $var = \'single_quote\';
        IF ($var === "SINGLE_QUOTE") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        }
        ';
        $scanner = new PhpTokenScanner($code);

        // 1. Quote normalization
        $this->assertTrue($scanner->hasSequence(['$var', '=', '"single_quote"']));
        $this->assertTrue($scanner->hasSequence(['$var', '=', '\'single_quote\'']));

        // 2. Case-insensitivity (IF vs if)
        $this->assertTrue($scanner->hasSequence(['if', '(', '$var', '===', '"single_quote"', ')']));

        // 3. Token type matching using integer (T_VARIABLE)
        $this->assertTrue($scanner->hasSequence([T_VARIABLE, '=', '"single_quote"']));

        // 4. Non-matching sequence
        $this->assertFalse($scanner->hasSequence(['$var', '=', '"double_quote"']));
        $this->assertEquals(0, $scanner->countSequence(['$var', '=', '"double_quote"']));
        
        // 5. Sequence count
        $this->assertEquals(1, $scanner->countSequence(['curl_setopt', '(', '$ch', ',', 'CURLOPT_SSL_VERIFYPEER', ',', 'true', ')']));
    }
}
