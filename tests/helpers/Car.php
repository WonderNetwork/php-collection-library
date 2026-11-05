<?php

namespace DusanKasan\Knapsack\Tests\Helpers;

class Car extends Machine
{
    public function __construct(string $name, private int $numberOfSeats)
    {
        parent::__construct($name);
    }
}
