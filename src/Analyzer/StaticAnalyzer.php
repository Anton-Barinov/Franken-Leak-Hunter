<?php
/**
 * Franken-Leak-Hunter
 * Static Code Analyzer Orchestrator
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Analyzer;

use FrankenLeakHunter\Analyzer\Rule\RuleInterface;
use FrankenLeakHunter\Analyzer\Rule\StaticPropertyRule;
use FrankenLeakHunter\Analyzer\Rule\StaticVariableRule;
use FrankenLeakHunter\Analyzer\Rule\GlobalMutationRule;
use FrankenLeakHunter\Analyzer\Rule\SingletonStateRule;

class StaticAnalyzer
{
    /** @var RuleInterface[] */
    private array $rules = [];

    /** @var array */
    private array $excludePatterns = [
        '/vendor/',
        '/tests/',
        '/cache/',
        '/var/',
        '/.git/',
    ];

    public function __construct(array $rules = [])
    {
        if (empty($rules)) {
            $this->rules = [
                new StaticPropertyRule(),
                new StaticVariableRule(),
                new GlobalMutationRule(),
                new SingletonStateRule(),
            ];
        } else {
            $this->rules = $rules;
        }
    }

    /**
     * Add custom exclude pattern
     */
    public function addExclude(string $pattern): self
    {
        $this->excludePatterns[] = $pattern;
        return $this;
    }

    /**
     * Analyze a single file or a directory
     *
     * @param string $path
     * @return AnalysisResult
     */
    public function analyze(string $path): AnalysisResult
    {
        $startTime = microtime(true);
        $result = new AnalysisResult();

        if (is_file($path)) {
            $this->analyzeFile($path, $result);
        } elseif (is_dir($path)) {
            $this->analyzeDirectory($path, $result);
        } else {
            throw new \InvalidArgumentException("Target path '{$path}' is neither a file nor a directory.");
        }

        $result->duration = microtime(true) - $startTime;
        return $result;
    }

    /**
     * Scan directory recursively
     */
    private function analyzeDirectory(string $dir, AnalysisResult $result): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getExtension() === 'php') {
                $filePath = $item->getRealPath();

                if ($this->isExcluded($filePath)) {
                    continue;
                }

                $this->analyzeFile($filePath, $result);
            }
        }
    }

    /**
     * Check if path matches exclusion list
     */
    private function isExcluded(string $path): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Analyze single file
     */
    public function analyzeFile(string $filePath, AnalysisResult $result): void
    {
        $content = @file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return;
        }

        $result->filesScanned++;

        $tokens = token_get_all($content);

        foreach ($this->rules as $rule) {
            $hazards = $rule->inspect($filePath, $content, $tokens);
            foreach ($hazards as $h) {
                $result->addHazard($h);
            }
        }
    }
}
