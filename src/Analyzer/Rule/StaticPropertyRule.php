<?php
/**
 * Franken-Leak-Hunter
 * Rule: Static Property State Accumulation
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Analyzer\Rule;

use FrankenLeakHunter\Analyzer\Hazard;

class StaticPropertyRule implements RuleInterface
{
    public function inspect(string $filePath, string $content, array $tokens): array
    {
        $hazards = [];
        $lines = explode("\n", $content);

        // Check if file defines classes with static properties
        $currentClass = null;
        $classImplementsReset = false;
        $classHasResetMethod = false;
        $classStaticProperties = []; // [ ['name' => '$items', 'line' => 24, 'snippet' => ...] ]

        // First pass: detect if class implements ResetInterface or has reset() method
        if (preg_match('/implements\s+[^\{]*(ResetInterface|ResettableService)/i', $content)) {
            $classImplementsReset = true;
        }

        $count = count($tokens);
        $inClass = false;
        $braceLevel = 0;
        $classBraceLevel = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if ($token[0] === T_CLASS) {
                    // Look ahead for class name
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $currentClass = $tokens[$j][1];
                            $inClass = true;
                            $classBraceLevel = $braceLevel;
                            break;
                        }
                    }
                }

                // Check for reset method
                if ($inClass && $token[0] === T_FUNCTION) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $fnName = strtolower($tokens[$j][1]);
                            if (in_array($fnName, ['reset', 'clear', 'resetstate', 'cleanup', 'flush'])) {
                                $classHasResetMethod = true;
                            }
                            break;
                        }
                    }
                }

                // Check for static property
                // Pattern: (public|protected|private)? static [type]? $var
                if ($inClass && $token[0] === T_STATIC) {
                    // Check if this is within class body but NOT inside a function/method
                    // Look back to verify it's a property declaration or check ahead
                    $isProperty = false;
                    $propName = '';
                    $propLine = $token[2];

                    for ($j = $i + 1; $j < min($i + 15, $count); $j++) {
                        if (is_array($tokens[$j])) {
                            if ($tokens[$j][0] === T_FUNCTION) {
                                // This is `static function`, not static property
                                break;
                            }
                            if ($tokens[$j][0] === T_VARIABLE) {
                                $propName = $tokens[$j][1];
                                $propLine = $tokens[$j][2];
                                $isProperty = true;
                                break;
                            }
                        } elseif ($tokens[$j] === '(' || $tokens[$j] === '{') {
                            // Method or closure
                            break;
                        }
                    }

                    if ($isProperty && !empty($propName)) {
                        $classStaticProperties[] = [
                            'class' => $currentClass ?: basename($filePath, '.php'),
                            'name'  => $propName,
                            'line'  => $propLine,
                        ];
                    }
                }
            } elseif ($token === '{') {
                $braceLevel++;
            } elseif ($token === '}') {
                $braceLevel--;
                if ($inClass && $braceLevel <= $classBraceLevel) {
                    // End of class - evaluate properties
                    if (!$classImplementsReset && !$classHasResetMethod) {
                        foreach ($classStaticProperties as $prop) {
                            $lineNum = $prop['line'];
                            $snippet = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                            $symbol = $prop['class'] . '::' . $prop['name'];

                            $hazards[] = new Hazard(
                                $filePath,
                                $lineNum,
                                Hazard::SEVERITY_CRITICAL,
                                'static-property',
                                $symbol,
                                "Static property {$symbol} persists in worker memory across requests without a reset hook.",
                                $snippet,
                                "Implement Symfony\Contracts\Service\ResetInterface or add a public reset() method to clear {$prop['name']} between requests."
                            );
                        }
                    }
                    // Reset for next class
                    $inClass = false;
                    $currentClass = null;
                    $classHasResetMethod = false;
                    $classStaticProperties = [];
                }
            }
        }

        return $hazards;
    }
}
