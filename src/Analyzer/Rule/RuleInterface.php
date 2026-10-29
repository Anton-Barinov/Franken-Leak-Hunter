<?php
/**
 * Franken-Leak-Hunter
 * Rule Interface for Static Analyzer
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Analyzer\Rule;

use FrankenLeakHunter\Analyzer\Hazard;

interface RuleInterface
{
    /**
     * Inspect file tokens and code for state pollution hazards
     *
     * @param string $filePath
     * @param string $content
     * @param array $tokens (token_get_all result)
     * @return Hazard[]
     */
    public function inspect(string $filePath, string $content, array $tokens): array;
}
