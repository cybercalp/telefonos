<?php
namespace Tests;

class PhpTokenScanner
{
    private array $tokens = [];

    public function __construct(string $content)
    {
        $all = token_get_all($content);
        $this->tokens = array_values(array_filter($all, function ($t) {
            if (is_array($t)) {
                return !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]);
            }
            return true;
        }));
    }

    private function tokensMatch($expected, $actualToken): bool
    {
        $actualType = is_array($actualToken) ? $actualToken[0] : null;
        $actualText = is_array($actualToken) ? $actualToken[1] : $actualToken;

        if (is_int($expected)) {
            return $actualType === $expected;
        }

        if ($actualType === T_CONSTANT_ENCAPSED_STRING) {
            $actualText = trim($actualText, "'\"");
        }

        $expectedText = trim($expected, "'\"");
        return strcasecmp($expectedText, $actualText) === 0;
    }

    public function hasSequence(array $sequence): bool
    {
        $seqCount = count($sequence);
        if ($seqCount === 0) {
            return true;
        }

        $tokenCount = count($this->tokens);
        for ($i = 0; $i <= $tokenCount - $seqCount; $i++) {
            $match = true;
            for ($j = 0; $j < $seqCount; $j++) {
                if (!$this->tokensMatch($sequence[$j], $this->tokens[$i + $j])) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return true;
            }
        }
        return false;
    }

    public function countSequence(array $sequence): int
    {
        $seqCount = count($sequence);
        if ($seqCount === 0) {
            return 0;
        }

        $count = 0;
        $tokenCount = count($this->tokens);
        for ($i = 0; $i <= $tokenCount - $seqCount; ) {
            $match = true;
            for ($j = 0; $j < $seqCount; $j++) {
                if (!$this->tokensMatch($sequence[$j], $this->tokens[$i + $j])) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $count++;
                $i += $seqCount;
            } else {
                $i++;
            }
        }
        return $count;
    }
}
