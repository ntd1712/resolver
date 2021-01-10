<?php

namespace Tests\Unit;

use Chaos\Support\Resolver\PagerResolver;
use PHPUnit\Framework\TestCase;

/**
 * Class PagerTest.
 */
class PagerTest extends TestCase
{
    /**
     * @param   array $query Query.
     * @param   array $criteria Criteria.
     * @param   false|array $expectedResult Result.
     *
     * @dataProvider dataProviderTestPagerReturnsResolvedQuery
     */
    public function testPagerReturnsResolvedQuery($query, $criteria, $expectedResult)
    {
        $result = PagerResolver::make()->resolve($query, $criteria);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function dataProviderTestPagerReturnsResolvedQuery()
    {
        return [
            [[], [], false],

            [['offset' => 0, 'limit' => 20], [], ['limit' => 20, 'offset' => 0, 'page' => 1]],
            [['offset' => 20, 'limit' => 20], [], ['limit' => 20, 'offset' => 20, 'page' => 2]],
            [['offset' => 40, 'limit' => 20], [], ['limit' => 20, 'offset' => 40, 'page' => 3]],
            [['offset' => 180, 'limit' => 20], [], ['limit' => 20, 'offset' => 180, 'page' => 10]],

            [['offset' => -1], ['limit' => 10], ['limit' => 10, 'offset' => 0, 'page' => 1]],
            [['offset' => 0], ['limit' => 10], ['limit' => 10, 'offset' => 0, 'page' => 1]],
            [['offset' => 5], ['limit' => 10], ['limit' => 10, 'offset' => 10, 'page' => 2]],
            [['offset' => 10], ['limit' => 10], ['limit' => 10, 'offset' => 10, 'page' => 2]],
            [['offset' => 2021], ['limit' => 10], ['limit' => 10, 'offset' => 2030, 'page' => 204]],

            [['offset' => -1], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['offset' => 0], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['offset' => 5], [], ['limit' => 1, 'offset' => 5, 'page' => 6]],
            [['offset' => 10], [], ['limit' => 1, 'offset' => 10, 'page' => 11]],
            [['offset' => 2021], [], ['limit' => 1, 'offset' => 2021, 'page' => 2022]],

            [['offset' => -1, 'limit' => -1], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['offset' => 0, 'limit' => -1], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['offset' => 5, 'limit' => -1], [], ['limit' => 1, 'offset' => 5, 'page' => 6]],
            [['offset' => 10, 'limit' => -1], [], ['limit' => 1, 'offset' => 10, 'page' => 11]],
            [['offset' => 2021, 'limit' => -1], [], ['limit' => 1, 'offset' => 2021, 'page' => 2022]],

            [['offset' => -1, 'limit' => 0], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['offset' => 0, 'limit' => 0], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['offset' => 5, 'limit' => 0], [], ['limit' => 1, 'offset' => 5, 'page' => 6]],
            [['offset' => 10, 'limit' => 0], [], ['limit' => 1, 'offset' => 10, 'page' => 11]],
            [['offset' => 2021, 'limit' => 0], [], ['limit' => 1, 'offset' => 2021, 'page' => 2022]],

            [['offset' => -1, 'limit' => 101], [], ['limit' => 100, 'offset' => 0, 'page' => 1]],
            [['offset' => 0, 'limit' => 101], [], ['limit' => 100, 'offset' => 0, 'page' => 1]],
            [['offset' => 5, 'limit' => 101], [], ['limit' => 100, 'offset' => 100, 'page' => 2]],
            [['offset' => 10, 'limit' => 101], [], ['limit' => 100, 'offset' => 100, 'page' => 2]],
            [['offset' => 2021, 'limit' => 101], [], ['limit' => 100, 'offset' => 2100, 'page' => 22]],

            [['page' => 1, 'limit' => 20], [], ['limit' => 20, 'offset' => 0, 'page' => 1]],
            [['page' => 2, 'limit' => 20], [], ['limit' => 20, 'offset' => 20, 'page' => 2]],
            [['page' => 3, 'limit' => 20], [], ['limit' => 20, 'offset' => 40, 'page' => 3]],
            [['page' => 9, 'limit' => 20], [], ['limit' => 20, 'offset' => 160, 'page' => 9]],
            [['page' => 10, 'limit' => 20], [], ['limit' => 20, 'offset' => 180, 'page' => 10]],

            [['page' => -1], ['limit' => 10], ['limit' => 10, 'offset' => 0, 'page' => 1]],
            [['page' => 0], ['limit' => 10], ['limit' => 10, 'offset' => 0, 'page' => 1]],
            [['page' => 2], ['limit' => 10], ['limit' => 10, 'offset' => 10, 'page' => 2]],
            [['page' => 2], ['limit' => 10], ['limit' => 10, 'offset' => 10, 'page' => 2]],
            [['page' => 204], ['limit' => 10], ['limit' => 10, 'offset' => 2030, 'page' => 204]],

            [['page' => -1], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['page' => 0], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['page' => 6], [], ['limit' => 1, 'offset' => 5, 'page' => 6]],
            [['page' => 11], [], ['limit' => 1, 'offset' => 10, 'page' => 11]],
            [['page' => 2022], [], ['limit' => 1, 'offset' => 2021, 'page' => 2022]],

            [['page' => -1, 'limit' => -1], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['page' => 0, 'limit' => -1], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['page' => 6, 'limit' => -1], [], ['limit' => 1, 'offset' => 5, 'page' => 6]],
            [['page' => 11, 'limit' => -1], [], ['limit' => 1, 'offset' => 10, 'page' => 11]],
            [['page' => 2022, 'limit' => -1], [], ['limit' => 1, 'offset' => 2021, 'page' => 2022]],

            [['page' => -1, 'limit' => 0], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['page' => 0, 'limit' => 0], [], ['limit' => 1, 'offset' => 0, 'page' => 1]],
            [['page' => 6, 'limit' => 0], [], ['limit' => 1, 'offset' => 5, 'page' => 6]],
            [['page' => 11, 'limit' => 0], [], ['limit' => 1, 'offset' => 10, 'page' => 11]],
            [['page' => 2022, 'limit' => 0], [], ['limit' => 1, 'offset' => 2021, 'page' => 2022]],

            [['page' => -1, 'limit' => 101], [], ['limit' => 100, 'offset' => 0, 'page' => 1]],
            [['page' => 0, 'limit' => 101], [], ['limit' => 100, 'offset' => 0, 'page' => 1]],
            [['page' => 2, 'limit' => 101], [], ['limit' => 100, 'offset' => 100, 'page' => 2]],
            [['page' => 2, 'limit' => 101], [], ['limit' => 100, 'offset' => 100, 'page' => 2]],
            [['page' => 22, 'limit' => 101], [], ['limit' => 100, 'offset' => 2100, 'page' => 22]],
        ];
    }
}
