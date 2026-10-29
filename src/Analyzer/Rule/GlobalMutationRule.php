<?php
/**
 * Franken-Leak-Hunter
 * Rule: Superglobal Mutation & Contamination
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Analyzer\Rule;

use FrankenLeakHunter\Analyzer\Hazard;

class GlobalMutationRule implements RuleInterface
{
    private array $pollutingGlobals = [
        '$GLOBALS',
        '$_SERVER',
        '$_SESSION',
        '$_ENV',
    ];

    public function inspect(string $filePath, string $content, array $tokens): array
    {
        $hazards = [];
        $lines = explode("\n", $content);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_VARIABLE && in_array($token[1], $this->pollutingGlobals)) {
                $globalVar = $token[1];
                $lineNum = $token[2];

                // Check if this variable is being mutated: $GLOBALS[...] = ...
                $isMutation = false;
                $bracketOpen = false;

                for ($j = $i + 1; $j < min($i + 15, $count); $j++) {
                    $next = $tokens[$j];
                    if ($next === '[') {
                        $bracketOpen = true;
                    } elseif ($next === ']') {
                        // Look ahead for assignment operator '='
                        for ($k = $j + 1; $k < min($j + 5, $count); $k++) {
                            if ($tokens[$k] === '=') {
                                $isMutation = true;
                                break 2;
                            } elseif (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_WHITESPACE, T_COMMENT])) {
                                continue;
                            } else {
                                break 2;
                            }
                        }
                    } elseif ($next === '=') {
                        $isMutation = true;
                        break;
                    }
                }

                if ($isMutation) {
                    $snippet = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                    $symbol = "{$globalVar} mutation";

                    $hazards[] = new Hazard(
                        $filePath,
                        $lineNum,
                        Hazard::SEVERITY_CRITICAL,
                        'global-mutation',
                        $symbol,
                        "Direct mutation of {$globalVar} contaminates the global environment across all worker requests.",
                        $snippet,
                        "Use dependency injection, request attributes, or a scoped context service instead of global superglobals."
                    );
                }
            }
        }

        return $hazards;
    }
}
