<?php

use DusanKasan\Knapsack\Collection;
use DusanKasan\Knapsack\Exceptions\InvalidArgument;
use DusanKasan\Knapsack\Exceptions\InvalidReturnValue;
use DusanKasan\Knapsack\Exceptions\ItemNotFound;
use DusanKasan\Knapsack\Exceptions\NoMoreItems;
use DusanKasan\Knapsack\Tests\Helpers\PlusOneAdder;

describe(Collection::class, function () {
    it("is initializable", function () {
        $this->collection = new Collection([1, 2, 3]);
        expect($this->collection)->toBeAnInstanceOf(Collection::class);
        expect($this->collection)->toBeAnInstanceOf(Traversable::class);
        expect($this->collection)->toBeAnInstanceOf(Serializable::class);
    });
    it("can be instantiated from iterator", function () {
        $iterator = new ArrayIterator([1, 2]);
        $this->collection = new Collection($iterator);
        expect($this->collection->toArray())->toBe([1, 2]);
    });
    it("can be instantiated from iterator aggregate", function (IteratorAggregate $iteratorAggregate) {
        $iterator = new ArrayIterator([1, 2]);
        allow($iteratorAggregate->getIterator())->toReturn($iterator);
        $this->collection = new Collection($iteratorAggregate);
        expect($this->collection->toArray())->toBe([1, 2]);
    });
    it("can be instantiated from array", function () {
        $this->collection = new Collection([1, 2, 3]);
        expect($this->collection->toArray())->toBe([1, 2, 3]);
    });
    it("will throw when passed something other than array or traversable", function () {
        expect(fn() => new Collection(1))->toThrow(InvalidArgument::class);
    });
    it("can be instantiated from callable returning an array", function () {
        $this->collection = new Collection(fn() => [1, 2, 3]);
        expect($this->collection->toArray())->toBe([1, 2, 3]);
    });
    it("can be instantiated from callable returning an iterator", function () {
        $this->collection = new Collection(fn() => new ArrayIterator([1, 2, 3]));
        expect($this->collection->toArray())->toBe([1, 2, 3]);
    });
    it("can be instantiated from callable returning a generator", function () {
        $this->collection = new Collection(function () {
            foreach ([1, 2, 3] as $value) {
                yield $value;
            }
        });
        expect($this->collection->toArray())->toBe([1, 2, 3]);
    });
    it("will throw when passed callable will return something other than array or traversable", function () {
        expect(fn() => new Collection(fn () => 1))->toThrow(InvalidReturnValue::class);
    });
    it("can be created statically", function () {
        $this->collection = Collection::from([1, 2]);
        expect($this->collection->toArray())->toBe([1, 2]);
    });
    it("can be created to iterate over function infinitely", function () {
        $this->collection = Collection::iterate(1, fn($i) => $i + 1);
        expect($this->collection->take(2)->toArray())->toBe([1, 2]);
    });
    it("can be created to iterate over function non infinitely", function () {
        $this->collection = Collection::iterate(1, function ($i) {
            if ($i > 3) {
                throw new NoMoreItems();
            }
            return $i + 1;
        });
        expect($this->collection->toArray())->toBe([1, 2, 3, 4]);
    });
    it("can be created to repeat a value infinite times", function () {
        $this->collection = Collection::repeat(1);
        expect($this->collection->take(2)->toArray())->toBe([1, 1]);
    });
    it("can convert to array", function () {
        $iterator = new \ArrayIterator(['foo']);
        $this->collection = new Collection(function () use ($iterator) {
            yield 'no key';
            yield 'with key' => 'this value is overwritten by the same key';
            yield 'nested' => ['y' => 'z'];
            yield 'iterator is not converted' => $iterator;
            yield 'with key' => 'x';
        });
        expect($this->collection->toArray())->toBe(['no key', 'with key' => 'x', 'nested' => ['y' => 'z'], 'iterator is not converted' => $iterator]);
    });
    it("can filter", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->filter(fn($item) => $item > 2)->toArray())->toBe([1 => 3, 2 => 3]);
        expect($this->collection->filter(fn($item, $key) => $key > 2 && $item < 3)->toArray())->toBe([3 => 2]);
    });
    it("can filter falsy values", function () {
        $this->collection = new Collection([false, null, '', 0, 0.0, []]);
        expect($this->collection->filter()->isEmpty())->toBe(true);
    });
    it("can distinct", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->distinct()->toArray())->toBe([1, 3, 3 => 2]);
    });
    it("can concat", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        $collection = $this->collection->concat([4, 5]);
        expect($collection->toArray())->toBe([4, 5, 3, 2]);
        expect($collection->size())->toBe(6);
    });
    it("can map", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->map(fn($item) => $item + 1)->toArray())->toBe([2, 4, 4, 3]);
    });
    it("can reduce", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->reduce(function ($temp, $item) {
            $temp[] = $item;
            return $temp;
        }, ['a' => [1]], true)->values()->toArray())->toBe([[1], 1, 3, 3, 2]);
        expect($this->collection->reduce(fn($temp, $item) => $temp + $item, 0))->toBe(9);
        expect($this->collection->reduce(fn($temp, $item, $key) => $temp + $key + $item, 0))->toBe(15);
        expect($this->collection->reduce(fn(Collection $temp, $item) => $temp->append($item), new Collection([]))->toArray())->toBe([1, 3, 3, 2]);
    });
    it("can flatten", function () {
        $this->collection = new Collection([1, [2, [3]]]);
        expect($this->collection->flatten()->values()->toArray())->toBe([1, 2, 3]);
        expect($this->collection->flatten(1)->values()->toArray())->toBe([1, 2, [3]]);
    });
    it("can sort", function () {
        $this->collection = new Collection([3, 1, 2]);
        expect($this->collection->sort(fn($a, $b) => $a > $b ? 1 : -1)->toArray())->toBe([1 => 1, 2 => 2, 0 => 3]);
        expect($this->collection->sort(fn($v1, $v2, $k1, $k2) => $k1 < $k2 || $v1 == $v2 ? 1 : -1)->toArray())->toBe([2 => 2, 1 => 1, 0 => 3]);
    });
    it("can slice", function () {
        $this->collection = new Collection([1, 2, 3, 4, 5]);
        expect($this->collection->slice(2, 4)->toArray())->toBe([2 => 3, 3 => 4]);
        expect($this->collection->slice(4)->toArray())->toBe([4 => 5]);
    });
    it("can group by", function () {
        $this->collection = new Collection([1, 2, 3, 4, 5]);
        $collection = $this->collection->groupBy(fn($i) => $i % 2);
        expect($collection->get(0)->toArray())->toBe([2, 4]);
        expect($collection->get(1)->toArray())->toBe([1, 3, 5]);
        $collection = $this->collection->groupBy(fn($k, $i) => ($k + $i) % 3);
        expect($collection->get(0)->toArray())->toBe([2, 5]);
        expect($collection->get(1)->toArray())->toBe([1, 4]);
        expect($collection->get(2)->toArray())->toBe([3]);
    });
    it("can group by key", function () {
        $this->collection = new Collection(['some' => 'thing', ['letter' => 'A', 'type' => 'caps'], ['letter' => 'a', 'type' => 'small'], ['letter' => 'B', 'type' => 'caps'], ['letter' => 'Z']]);
        $collection = $this->collection->groupByKey('type');
        expect($collection->get('small')->toArray())->toBe([['letter' => 'a', 'type' => 'small']]);
        expect($collection->get('caps')->toArray())->toBe([['letter' => 'A', 'type' => 'caps'], ['letter' => 'B', 'type' => 'caps']]);
        $this->collection->groupByKey('types');
        expect(fn() => $this->collection->get('caps'))->toThrow(ItemNotFound::class);
    });
    it("can execute callback for each item", function (DOMXPath $a) {
        $this->collection = new Collection([$a]);
        expect($this->collection->each(function (DOMXPath $i) {
            $i->query('asd');
        })->toArray())->toBe([$a]);
    });
    it("can get size", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->size())->toBe(4);
    });
    it("can get item by key", function () {
        $this->collection = new Collection([1, [2], 3]);
        expect($this->collection->get(0))->toBe(1);
        expect($this->collection->get(1, true)->first())->toBe(2);
        expect($this->collection->get(1))->toBe([2]);
        expect(fn() => $this->collection->get(5))->toThrow(ItemNotFound::class);
    });
    it("can get item by key or return default", function () {
        $this->collection = new Collection([1, [2], 3]);
        expect($this->collection->getOrDefault(0))->toBe(1);
        expect($this->collection->getOrDefault(1, null, true)->first())->toBe(2);
        expect($this->collection->getOrDefault(1, null))->toBe([2]);
        expect($this->collection->getOrDefault(5))->toBe(null);
        expect($this->collection->getOrDefault(5, 'not found'))->toBe('not found');
    });
    it("can find", function () {
        $this->collection = new Collection([1, 3, 3, 2, [5]]);
        expect($this->collection->find(fn($v) => $v < 3))->toBe(1);
        expect($this->collection->find(fn($v, $k) => $v < 3 && $k > 1))->toBe(2);
        expect($this->collection->find(fn($v) => $v < 0))->toBe(null);
        expect($this->collection->find(fn($v) => $v < 0, 'not found'))->toBe('not found');
        expect($this->collection->find('\DusanKasan\Knapsack\isCollection', null, true)->first())->toBe(5);
        expect($this->collection->find('\DusanKasan\Knapsack\isCollection'))->toBe([5]);
    });
    it("can count by", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->countBy(fn($v) => $v % 2 == 0 ? 'even' : 'odd')->toArray())->toBe(['odd' => 3, 'even' => 1]);
        expect($this->collection->countBy(fn($k, $v) => ($k + $v) % 2 == 0 ? 'even' : 'odd')->toArray())->toBe(['odd' => 3, 'even' => 1]);
    });
    it("can index by", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->indexBy(fn($v) => $v)->toArray())->toBe([1 => 1, 3 => 3, 2 => 2]);
        expect($this->collection->indexBy(fn($v, $k) => $k . $v)->toArray())->toBe(['01' => 1, '13' => 3, '23' => 3, '32' => 2]);
    });
    it("can check if every item passes predicament test", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->every(fn($v) => $v > 0))->toBe(true);
        expect($this->collection->every(fn($v) => $v > 1))->toBe(false);
        expect($this->collection->every(fn($v, $k) => $v > 0 && $k >= 0))->toBe(true);
        expect($this->collection->every(fn($v, $k) => $v > 0 && $k > 0))->toBe(false);
    });
    it("can check if some items pass predicament test", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->some(fn($v) => $v < -1))->toBe(false);
        expect($this->collection->some(fn($v, $k) => $v > 0 && $k < -1))->toBe(false);
        expect($this->collection->some(fn($v) => $v < 2))->toBe(true);
        expect($this->collection->some(fn($v, $k) => $v > 0 && $k > 0))->toBe(true);
    });
    it("can check if it contains a value", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->contains(3))->toBe(true);
        expect($this->collection->contains(true))->toBe(false);
    });
    it("can reverse", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->reverse()->toArray())->toBe([3 => 2, 2 => 3, 1 => 3, 0 => 1]);
    });
    it("can reduce from right", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->reduceRight(fn($temp, $e) => $temp . $e, 0))->toBe('02331');
        expect($this->collection->reduceRight(fn($temp, $key, $item) => $temp + $key + $item, 0))->toBe(15);
        expect($this->collection->reduceRight(fn(Collection $temp, $item) => $temp->append($item), new Collection([]))->toArray())->toBe([2, 3, 3, 1]);
    });
    it("can return only first x elements", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->take(2)->toArray())->toBe([1, 3]);
    });
    it("can skip first x elements", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->drop(2)->toArray())->toBe([2 => 3, 3 => 2]);
    });
    it("can return values", function () {
        $this->collection = new Collection(['a' => 1, 'b' => 2]);
        expect($this->collection->values()->toArray())->toBe([1, 2]);
    });
    it("can reject elements from collection", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->reject(fn($v) => $v == 3)->toArray())->toBe([1, 3 => 2]);
        expect($this->collection->reject(fn($v, $k) => $k == 2 && $v == 3)->toArray())->toBe([1, 3, 3 => 2]);
    });
    it("can get keys", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->keys()->toArray())->toBe([0, 1, 2, 3]);
    });
    it("can interpose", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->interpose('a')->values()->toArray())->toBe([1, 'a', 3, 'a', 3, 'a', 2]);
    });
    it("can drop elements from end of the collection", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->dropLast()->toArray())->toBe([1, 3, 3]);
        expect($this->collection->dropLast(2)->toArray())->toBe([1, 3]);
    });
    it("can interleave elements", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->interleave(['a', 'b', 'c', 'd', 'e'])->values()->toArray())->toBe([1, 'a', 3, 'b', 3, 'c', 2, 'd', 'e']);
    });
    it("can repeat items of collection infinitely", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->cycle()->take(8)->values()->toArray())->toBe([1, 3, 3, 2, 1, 3, 3, 2]);
    });
    it("can prepend item", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->prepend(1)->values()->toArray())->toBe([1, 1, 3, 3, 2]);
    });
    it("can prepend item with key", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->prepend(1, 'a')->toArray())->toBe(['a' => 1, 0 => 1, 1 => 3, 2 => 3, 3 => 2]);
    });
    it("can append item", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->append(1)->values()->toArray())->toBe([1, 3, 3, 2, 1]);
    });
    it("can append item with key", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->append(1, 'a')->toArray())->toBe([1, 3, 3, 2, 'a' => 1]);
    });
    it("can drop items while predicament is true", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->dropWhile(fn($v) => $v < 3)->toArray())->toBe([1 => 3, 2 => 3, 3 => 2]);
        expect($this->collection->dropWhile(fn($v, $k) => $k < 2 && $v < 3)->toArray())->toBe([1 => 3, 2 => 3, 3 => 2]);
    });
    it("can map and then concatenate a collection", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->mapcat(fn($v) => [[$v]])->values()->toArray())->toBe([[1], [3], [3], [2]]);
        expect($this->collection->mapcat(fn($v, $k) => [[$k + $v]])->values()->toArray())->toBe([[1], [4], [5], [5]]);
    });
    it("can take items while predicament is true", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->takeWhile(fn($v) => $v < 3)->toArray())->toBe([1]);
        expect($this->collection->takeWhile(fn($v, $k) => $k < 2 && $v < 3)->toArray())->toBe([1]);
    });
    it("can split the collection at nth item", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->splitAt(2)->size())->toBe(2);
        expect($this->collection->splitAt(2)->first()->toArray())->toBeLike([1, 3]);
        expect($this->collection->splitAt(2)->second()->toArray())->toBeLike([2 => 3, 3 => 2]);
    });
    it("can split the collection with predicament", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        $s1 = $this->collection->splitWith(fn($v) => $v < 3);
        expect($s1->size())->toBe(2);
        expect($s1->first()->toArray())->toBe([1]);
        expect($s1->second()->toArray())->toBe([1 => 3, 2 => 3, 3 => 2]);
        $s2 = $this->collection->splitWith(fn($v, $k) => $v < 2 && $k < 3);
        expect($s2->size())->toBe(2);
        expect($s2->first()->toArray())->toBe([1]);
        expect($s2->second()->toArray())->toBe([1 => 3, 2 => 3, 3 => 2]);
    });
    it("can replace items by items from another collection", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->replace([3 => 'a'])->toArray())->toBe([1, 'a', 'a', 2]);
    });
    it("can get reduction steps", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->reductions(fn($tmp, $i) => $tmp + $i, 0)->toArray())->toBe([0, 1, 4, 7, 9]);
    });
    it("can return every nth item", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->takeNth(2)->toArray())->toBe([1, 2 => 3]);
    });
    it("can shuffle itself", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->shuffle()->reduce(fn($tmp, $i) => $tmp + $i, 0))->toBe(9);
    });
    it("can partition", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        $s1 = $this->collection->partition(3, 2, [0, 1]);
        expect($s1->size())->toBe(2);
        expect($s1->first()->toArray())->toBe([1, 3, 3]);
        expect($s1->second()->toArray())->toBe([2 => 3, 3 => 2, 0 => 0]);
        $s2 = $this->collection->partition(3, 2);
        expect($s2->size())->toBe(2);
        expect($s2->first()->toArray())->toBe([1, 3, 3]);
        expect($s2->second()->toArray())->toBe([2 => 3, 3 => 2]);
        $s3 = $this->collection->partition(3);
        expect($s3->size())->toBe(2);
        expect($s3->first()->toArray())->toBe([1, 3, 3]);
        expect($s3->second()->toArray())->toBe([3 => 2]);
        $s4 = $this->collection->partition(1, 3);
        expect($s4->size())->toBe(2);
        expect($s4->first()->toArray())->toBe([1]);
        expect($s4->second()->toArray())->toBe([3 => 2]);
    });
    it("can partition by", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        $s1 = $this->collection->partitionBy(fn($v) => $v % 3 == 0);
        expect($s1->size())->toBe(3);
        expect($s1->first()->toArray())->toBe([1]);
        expect($s1->second()->toArray())->toBe([1 => 3, 2 => 3]);
        expect($s1->values()->get(2)->toArray())->toBe([3 => 2]);
        $s2 = $this->collection->partitionBy(fn($v, $k) => $k - $v);
        expect($s2->size())->toBe(4);
        expect($s2->first()->toArray())->toBe([1]);
        expect($s2->values()->get(1)->toArray())->toBe([1 => 3]);
        expect($s2->values()->get(2)->toArray())->toBe([2 => 3]);
        expect($s2->values()->get(3)->toArray())->toBe([3 => 2]);
    });
    it("can get nth value", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->first(0))->toBe(1);
        expect($this->collection->values()->get(3))->toBe(2);
    });
    it("can create infinite collection of repeated values", function () {
        $this->collection = Collection::repeat(1);
        expect($this->collection->take(3)->toArray())->toBe([1, 1, 1]);
    });
    it("can create finite collection of repeated values", function () {
        $this->collection = Collection::repeat(1, 1);
        expect($this->collection->toArray())->toBe([1]);
    });
    it("can create range from value to infinity", function () {
        $this->collection = Collection::range(5);
        expect($this->collection->take(2)->toArray())->toBe([5, 6]);
    });
    it("can create range from value to another value", function () {
        $this->collection = Collection::range(5, 6);
        expect($this->collection->take(4)->toArray())->toBe([5, 6]);
    });
    it("can check if it is not empty", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->isEmpty())->toBe(false);
        expect($this->collection->isNotEmpty())->toBe(true);
    });
    it("can check if it is empty", function () {
        $this->collection = new Collection([]);
        expect($this->collection->isEmpty())->toBe(true);
        expect($this->collection->isNotEmpty())->toBe(false);
    });
    it("can check frequency of distinct items", function () {
        $this->collection = new Collection([1, 3, 3, 2]);
        expect($this->collection->frequencies()->toArray())->toBe([1 => 1, 3 => 2, 2 => 1]);
    });
    it("can get first item", function () {
        $this->collection = new Collection([1, [2], 3]);
        expect($this->collection->first())->toBe(1);
        expect($this->collection->drop(1)->first())->toBe([2]);
        expect($this->collection->drop(1)->first(true)->toArray())->toBe([2]);
    });
    it("will throw when trying to get first item of empty collection", function () {
        $this->collection = new Collection([]);
        expect(fn() => $this->collection->first())->toThrow(ItemNotFound::class);
    });
    it("can get last item", function () {
        $this->collection = new Collection([1, [2], 3]);
        expect($this->collection->last())->toBe(3);
        expect($this->collection->take(2)->last())->toBe([2]);
        expect($this->collection->take(2)->last(true)->toArray())->toBe([2]);
    });
    it("will throw when trying to get last item of empty collection", function () {
        $this->collection = new Collection([]);
        expect(fn() => $this->collection->last())->toThrow(ItemNotFound::class);
    });
    it("can realize the collection", function (PlusOneAdder $adder) {
        allow($adder->dynamicMethod(1))->toReturn(2);
        allow($adder->dynamicMethod(2))->toReturn(3);
        $this->collection = new Collection(function () use ($adder) {
            yield $adder->dynamicMethod(1);
            yield $adder->dynamicMethod(2);
        });
        $this->collection->realize();
        expect($this->collection->first())->toBe(2);
    });
    it("can combine the collection", function () {
        $this->collection = new Collection(['a', 'b']);
        expect($this->collection->combine([1, 2])->toArray())->toBe(['a' => 1, 'b' => 2]);
        expect($this->collection->combine([1])->toArray())->toBe(['a' => 1]);
        expect($this->collection->combine([1, 2, 3])->toArray())->toBe(['a' => 1, 'b' => 2]);
    });
    it("can get second item", function () {
        $this->collection = new Collection([1, 2]);
        expect($this->collection->second())->toBe(2);
    });
    it("throws when trying to get non existent second item", function () {
        $this->collection = new Collection([1]);
        expect(fn() => $this->collection->second())->toThrow(ItemNotFound::class);
    });
    it("can drop item by key", function () {
        $this->collection = new Collection(['a' => 1, 'b' => 2]);
        expect($this->collection->except(['a', 'b'])->toArray())->toBe([]);
    });
    it("can get the difference between collections", function () {
        $this->collection = new Collection([1, 2, 3, 4]);
        expect($this->collection->diff([1, 2])->toArray())->toBe([2 => 3, 3 => 4]);
        expect($this->collection->diff([1, 2], [3])->toArray())->toBe([3 => 4]);
    });
    it("can flip the collection", function () {
        $this->collection = new Collection(['a' => 1, 'b' => 2]);
        expect($this->collection->flip()->toArray())->toBe([1 => 'a', 2 => 'b']);
    });
    it("can check if key exits", function () {
        $this->collection = new Collection(['a' => 1, 'b' => 2]);
        expect($this->collection->has('a'))->toBe(true);
        expect($this->collection->has('x'))->toBe(false);
    });
    it("filters by keys", function () {
        $this->collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
        expect($this->collection->only(['a', 'b'])->toArray())->toBe(['a' => 1, 'b' => 2]);
        expect($this->collection->only(['a', 'b', 'x'])->toArray())->toBe(['a' => 1, 'b' => 2]);
    });
    it("can serialize and unserialize", function () {
        $original = Collection::from([1, 2, 3])->take(2);
        $this->collection = new Collection([1, 2, 3, 4]);
        expect($this->collection)->toBeAnInstanceOf(Serializable::class);
        $this->collection->unserialize($original->serialize());
        expect($this->collection->toArray())->toBe([1, 2]);
    });
    it("can zip", function () {
        $this->collection = new Collection([1, 2, 3]);
        expect($this->collection->zip(['a' => 1, 'b' => 2, 'c' => 4])->map('\DusanKasan\Knapsack\toArray')->toArray())->toBe([[1, 'a' => 1], [1 => 2, 'b' => 2], [2 => 3, 'c' => 4]]);
        expect($this->collection->zip([4, 5, 6], [7, 8, 9])->map('\DusanKasan\Knapsack\values')->map('\DusanKasan\Knapsack\toArray')->toArray())->toBe([[1, 4, 7], [2, 5, 8], [3, 6, 9]]);
        expect($this->collection->zip([4, 5])->map('\DusanKasan\Knapsack\values')->map('\DusanKasan\Knapsack\toArray')->toArray())->toBe([[1, 4], [2, 5]]);
    });
    it("can use callable as transformer", function () {
        $this->collection = new Collection([1, 2, 3]);
        expect($this->collection->transform(fn(Collection $collection) => $collection->map('\DusanKasan\Knapsack\increment'))->toArray())->toBe([2, 3, 4]);
        expect(fn() => $this->collection->transform(fn(Collection $collection) => $collection->first()))->toThrow(InvalidReturnValue::class);
    });
    it("can extract data from nested collections", function () {
        $input = [['a' => ['b' => 1]], ['a' => ['b' => 2]], ['*' => ['b' => 3]], ['.' => ['b' => 4], 'c' => ['b' => 5], ['a']]];
        $this->collection = new Collection($input);
        expect($this->collection->extract('')->toArray())->toBe($input);
        expect($this->collection->extract('a.b')->toArray())->toBe([1, 2]);
        expect($this->collection->extract('*.b')->toArray())->toBe([1, 2, 3, 4, 5]);
        expect($this->collection->extract('\*.b')->toArray())->toBe([3]);
        expect($this->collection->extract('\..b')->toArray())->toBe([4]);
    });
    it("can get the intersect of collections", function () {
        $this->collection = new Collection([1, 2, 3]);
        expect($this->collection->intersect([1, 2])->values()->toArray())->toBe([1, 2]);
        expect($this->collection->intersect([1], [3])->values()->toArray())->toBe([1, 3]);
    });
    it("can check if size is exactly n", function () {
        $this->collection = new Collection([1, 2]);
        expect($this->collection->sizeIs(2))->toBe(true);
        expect($this->collection->sizeIs(3))->toBe(false);
        expect($this->collection->sizeIs(0))->toBe(false);
    });
    it("can check if size is less than n", function () {
        $this->collection = new Collection([1, 2]);
        expect($this->collection->sizeIsLessThan(0))->toBe(false);
        expect($this->collection->sizeIsLessThan(2))->toBe(false);
        expect($this->collection->sizeIsLessThan(3))->toBe(true);
    });
    it("can check if size is greater than n", function () {
        $this->collection = new Collection([1, 2]);
        expect($this->collection->sizeIsGreaterThan(2))->toBe(false);
        expect($this->collection->sizeIsGreaterThan(1))->toBe(true);
        expect($this->collection->sizeIsGreaterThan(0))->toBe(true);
    });
    it("can check if size is between n and m", function () {
        $this->collection = new Collection([1, 2]);
        expect($this->collection->sizeIsBetween(1, 3))->toBe(true);
        expect($this->collection->sizeIsBetween(3, 4))->toBe(false);
        expect($this->collection->sizeIsBetween(0, 0))->toBe(false);
        expect($this->collection->sizeIsBetween(3, 1))->toBe(true);
    });
    it("can sum the collection", function () {
        $this->collection = new Collection([1, 2, 3, 4]);
        expect($this->collection->sum())->toBe(10);
        expect($this->collection->append(1.5)->sum())->toBe(11.5);
    });
    it("can get average of the collection", function () {
        $this->collection = new Collection([1, 2, 2, 3]);
        expect($this->collection->average())->toBe(2);
        expect($this->collection->append(3)->average())->toBe(2.2);
    });
    it("will return zero when average is called on empty collection", function () {
        $this->collection = new Collection([]);
        expect($this->collection->average())->toBe(0);
    });
    it("can get maximal value in the colleciton", function () {
        $this->collection = new Collection([1, 2, 3, 2]);
        expect($this->collection->max())->toBe(3);
    });
    it("will return null when max is called on empty collection", function () {
        $this->collection = new Collection([]);
        expect($this->collection->max())->toBe(null);
    });
    it("can get min value in the colleciton", function () {
        $this->collection = new Collection([2, 1, 3, 2]);
        expect($this->collection->min())->toBe(1);
    });
    it("will return null when min is called on empty collection", function () {
        $this->collection = new Collection([]);
        expect($this->collection->min())->toBe(null);
    });
    it("can convert the collection to string", function () {
        $this->collection = new Collection([2, 'a', 3, null]);
        expect($this->collection->toString())->toBe('2a3');
    });
    it("can replace by key", function () {
        $this->collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
        expect($this->collection->replaceByKeys(['b' => 3])->toArray())->toBe(['a' => 1, 'b' => 3, 'c' => 3]);
    });
    it("can transpose collections of collections", function () {
        $this->collection = new Collection([new Collection([1, 2, 3]), new Collection([4, 5, new Collection(['foo', 'bar'])]), new Collection([7, 8, 9])]);
        expect($this->collection->transpose()->toArray())->toBeLike([new Collection([1, 4, 7]), new Collection([2, 5, 8]), new Collection([3, new Collection(['foo', 'bar']), 9])]);
    });
    it("can transpose arrays of different lengths", function () {
        $this->collection = new Collection([new Collection(['a', 'b', 'c', 'd']), new Collection(['apple', 'box', 'car'])]);
        expect($this->collection->transpose()->toArray())->toBeLike([new Collection(['a', 'apple']), new Collection(['b', 'box']), new Collection(['c', 'car']), new Collection(['d', null])]);
    });
    it("should throw an invalid argument if collection items are not collection", function () {
        $this->collection = new Collection([[1, 2, 3], [4, 5, 6], [7, 8, 9]]);
        expect(fn() => $this->collection->transpose())->toThrow(InvalidArgument::class);
    });
    it("can use the utility methods", function () {
        $this->collection = new Collection([1, 3, 2]);
        expect($this->collection->sort('\DusanKasan\Knapsack\compare')->values()->toArray())->toBe([1, 2, 3]);
        expect($this->collection->map('\DusanKasan\Knapsack\compare')->toArray())->toBe([1, 1, 0]);
        expect($this->collection->map('\DusanKasan\Knapsack\decrement')->toArray())->toBe([0, 2, 1]);
    });
    it("can dump the collection", function () {
        $this->collection = new Collection([[[1, [2], 3], ['a' => 'b'], new ArrayIterator([1, 2, 3])], [1, 2, 3], new ArrayIterator(['a', 'b', 'c']), true, new \DusanKasan\Knapsack\Tests\Helpers\Car('sedan', 5), \DusanKasan\Knapsack\concat([1], [1])]);
        expect($this->collection->dump())->toBe([[[1, [2], 3], ['a' => 'b'], [1, 2, 3]], [1, 2, 3], ['a', 'b', 'c'], true, [\DusanKasan\Knapsack\Tests\Helpers\Car::class => ['numberOfSeats' => 5]], [1, '0//1' => 1]]);
        expect($this->collection->dump(2))->toBe([[[1, [2], '>>>'], ['a' => 'b'], '>>>'], [1, 2, '>>>'], '>>>']);
        expect($this->collection->dump(null, 3))->toBe([[[1, '^^^', 3], ['a' => 'b'], [1, 2, 3]], [1, 2, 3], ['a', 'b', 'c'], true, [\DusanKasan\Knapsack\Tests\Helpers\Car::class => ['numberOfSeats' => 5]], [1, '0//1' => 1]]);
        expect($this->collection->dump(2, 3))->toBe([[[1, '^^^', '>>>'], ['a' => 'b'], '>>>'], [1, 2, '>>>'], '>>>']);
    });
    it("can print dump", function () {
        $this->collection = new Collection([1, [2], 3]);
        ob_start();
        expect($this->collection->printDump())->toBe($this->collection);
        expect($this->collection->printDump(2))->toBe($this->collection);
        expect($this->collection->printDump(2, 2))->toBe($this->collection);
        ob_clean();
    });
});
