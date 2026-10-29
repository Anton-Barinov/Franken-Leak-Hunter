<?php
/**
 * Franken-Leak-Hunter
 * Rule: Static Function Variable Memoization
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Analyzer\Rule;

use FrankenLeakHunter\Analyzer\Hazard;

class StaticVariableRule implements RuleInterface
{
    public function inspect(string $filePath, string $content, array $tokens): array
    {
        $hazards = [];
        $lines = explode("\n", $content);
        $count = count($tokens);

        $inFunction = false;
        $functionName = 'anonymous';
        $fnBraceLevel = 0;
        $braceLevel = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if ($token[0] === T_FUNCTION) {
                    $inFunction = true;
                    // Find function name if available
                    $functionName = 'anonymous';
                    for ($j = $i + 1; $j < min($i + 10, $count); $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $functionName = $tokens[$j][1];
                            break;
                        } elseif ($tokens[$j] === '(') {
                            break;
                        }
                    }
                    $fnBraceLevel = $braceLevel + 1;
                }

                // If inside a function body and encounter `static $var`
                if ($inFunction && $token[0] === T_STATIC) {
                    // Check next tokens for variable
                    for ($j = $i + 1; $j < min($i + 10, $count); $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
                            $varName = $tokens[$j][1];
                            $lineNum = $tokens[$j][2];
                            $snippet = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                            $symbol = "{$functionName}():static {$varName}";

                            $hazards[] = new Hazard(
                                $filePath,
                                $lineNum,
                                Hazard::SEVERITY_HIGH,
                                'static-variable',
                                $symbol,
                                "Static local variable {$symbol} retains state between requests in worker mode.",
                                $snippet,
                                "Replace static local memoization with an injectable cache service (e.g., PSR-6/PSR-16) or WeakMap."
                            );
                            break;
                        } elseif (is_array($tokens[$j]) && $tokens[$j][0] === T_FUNCTION) {
                            // Closure: static function()
                            break;
                        }
                    }
                }
            } elseif ($token === '{') {
                $braceLevel++;
            } elseif ($token === '}') {
                $braceLevel--;
                if ($inFunction && $braceLevel < $fnBraceLevel) {
                    $inFunction = false;
                }
            }
        }

        return $hazards;
    }
}
