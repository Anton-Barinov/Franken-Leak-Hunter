<?php
namespace Tests\Fixtures;

class StaticFunctionMemo
{
    public function calculateTax(float $amount, string $country): float
    {
        // Intentional static memoization leak inside method
        static $taxRates = [];

        if (!isset($taxRates[$country])) {
            $taxRates[$country] = 0.20;
        }

        return $amount * $taxRates[$country];
    }
}
