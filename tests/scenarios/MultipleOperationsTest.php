<?php

namespace DusanKasan\Knapsack\Tests\Scenarios;

use DusanKasan\Knapsack\Collection;
use PHPUnit\Framework\TestCase;

class MultipleOperationsTest extends TestCase
{
    /**
     * Example of a longer pipeline. If this was real code, you should probably split it into smaller chunks.
     */
    public function testIt()
    {
        $array = [1, 2, 8, 3, 7, 5, 1, 4, 4,];
        $collection = new Collection($array);

        $result = $collection
            ->reject(fn($v) => $v > 2)
            ->filter(fn($k) => $k > 5)
            ->distinct()
            ->concat([1, 2])
            ->map(fn($i) => [$i, $i + 1])
            ->flatten()
            ->sort(fn($a, $b) => $a - $b)
            ->slice(2, 5)
            ->groupBy(fn($v) => $v % 2 == 0 ? 'even' : 'odd')
            ->get('even')
            ->toArray();

        $this->assertEquals([2], $result);
    }
}
