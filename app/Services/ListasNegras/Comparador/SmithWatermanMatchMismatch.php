<?php

namespace App\Services\ListasNegras\Comparador;

use Exception;

class SmithWatermanMatchMismatch
{
    private $matchValue;

    private $mismatchValue;

    /**
     * Constructor: valores de match / mismatch.
     *
     * @param matchValue
     *   value when characters are equal
     * @param mismatchValue
     *   value when characters are not equal
     */
    public function __construct($matchValue, $mismatchValue)
    {
        if ($matchValue <= $mismatchValue) {
            throw new Exception('matchValue must be > mismatchValue');
        }

        $this->matchValue = $matchValue;
        $this->mismatchValue = $mismatchValue;

    }

    public function compare($a, $aIndex, $b, $bIndex)
    {
        return ($a[$aIndex] === $b[$bIndex])
            ? $this->matchValue
            : $this->mismatchValue;
    }

    public function max()
    {
        return $this->matchValue;
    }

    public function min()
    {
        return $this->mismatchValue;
    }
}
