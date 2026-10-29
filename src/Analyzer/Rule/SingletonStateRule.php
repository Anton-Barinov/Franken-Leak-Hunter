<?php
/**
 * Franken-Leak-Hunter
 * Rule: Unreset Singleton State Pollution
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Analyzer\Rule;

use FrankenLeakHunter\Analyzer\Hazard;

class SingletonStateRule implements RuleInterface
{
    public function inspect(string $filePath, string $content, array $tokens): array
    {
        $hazards = [];

        // Quick check for singleton pattern keywords
        if (!preg_match('/getInstance\s*\(/i', $content)) {
            return [];
        }

        // Check if class implements ResetInterface or has reset method
        if (preg_match('/implements\s+[^\{]*(ResetInterface|ResettableService)/i', $content) ||
            preg_match('/public\s+function\s+(reset|clear|resetState)\s*\(/i', $content)) {
            return [];
        }

        // Check if class has mutable instance properties (supporting typed properties)
        if (preg_match('/(private|protected|public)\s+(?!static\b)(?:[\w\\?|&<>, ]+\s+)?(\$\w+)/', $content, $matches)) {
            $propName = $matches[2];
            $className = basename($filePath, '.php');
            if (preg_match('/class\s+(\w+)/', $content, $cm)) {
                $className = $cm[1];
            }

            // Find line number of getInstance
            $lines = explode("\n", $content);
            $lineNum = 1;
            $snippet = '';
            foreach ($lines as $idx => $line) {
                if (stripos($line, 'getInstance') !== false) {
                    $lineNum = $idx + 1;
                    $snippet = trim($line);
                    break;
                }
            }

            $symbol = "{$className}::getInstance()";
            $hazards[] = new Hazard(
                $filePath,
                $lineNum,
                Hazard::SEVERITY_CRITICAL,
                'singleton-state',
                $symbol,
                "Singleton {$symbol} maintains mutable instance state ({$propName}) without a reset hook.",
                $snippet,
                "Implement a public static function reset() on {$className} to clear {$className}::\$instance between worker requests, or convert to dependency injection with request scoping."
            );
        }

        return $hazards;
    }
}
