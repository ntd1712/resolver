<?php

namespace Chaos\Support\Resolver;

/**
 * Class PagerResolver.
 *
 * @author t(-.-t) <ntd1712@mail.com>
 */
class PagerResolver
{
    // <editor-fold defaultstate="collapsed" desc="Default properties">

    /**
     * @var array
     */
    private $map = [
        'limit' => 'limit',
        'offset' => 'offset',
        'page' => 'page'
    ];

    /**
     * @var array
     */
    private $sizes = [1, 5, 10, 15, 20, 25, 50, 100, 200];

    /**
     * @param array $map Parameter map.
     *
     * @return $this
     */
    public function setParameterMap(array $map)
    {
        $this->map = $map;

        return $this;
    }

    /**
     * @param array $sizes Page sizes.
     *
     * @return $this
     */
    public function setPageSizes(array $sizes)
    {
        $this->sizes = $sizes;

        return $this;
    }

    // </editor-fold>

    /**
     * @return static
     */
    public static function make()
    {
        return new static();
    }

    /**
     * Resolves a query.
     *
     * GET ?limit=10&offset=0
     *     ?limit=10&page=1
     *
     * <code>
     * $criteria = [];
     *
     * PagerResolver::make()->resolve([], $criteria);              // false
     * PagerResolver::make()->resolve(['page' => 2], $criteria);   // ['limit' => 1, 'offset' => 1]
     * PagerResolver::make()->resolve(['offset' => 1], $criteria); // ['limit' => 1, 'offset' => 1]
     *
     * PagerResolver::make()
     *   ->setParameterMap(['limit' => 'length', 'offset' => 'start'])
     *   ->setPageSizes([1, 10, 20, 50, 100, 200])
     *   ->resolve(['length' => 10, 'start' => 10], $criteria);    // ['limit' => 10, 'offset' => 10]
     * </code>
     *
     * <code>
     * $input = $request->input();
     * $criteria = [];
     *
     * FilterResolver::make()
     *   ->setPermit($this->service->repository->fieldMappings)
     *   ->resolve($input, $criteria);
     *
     * OrderResolver::make()
     *   ->setPermit($this->service->repository->fieldMappings)
     *   ->resolve($input, $criteria);
     *
     * PagerResolver::make()->resolve($input, $criteria);
     *
     * echo $this->service->repository->getQueryBuilder($criteria)->getQuery()->getSQL();
     * </code>
     *
     * <code>
     * $criteria = [
     *   'limit' => 10,
     *   'offset' => 0
     * ];
     * </code>
     *
     * @param array $query Query to resolve.
     * @param array $criteria Resolved criteria.
     *
     * @return false|array Returns an array of values, like: ['limit' => (int), 'offset' => (int)], FALSE otherwise.
     */
    public function resolve(array $query, array &$criteria = [])
    {
        if (!(($hasOffset = isset($query[$this->map['offset']])) || isset($query[$this->map['page']]))) {
            return false;
        }

        if (isset($criteria['limit'])) {
            $limit = $criteria['limit'];
        } else {
            if (isset($query[$this->map['limit']])) {
                $limit = (int) $query[$this->map['limit']];

                if (!in_array($limit, $this->sizes)) {
                    $limit = $this->sizes[0];
                }
            } else {
                $limit = $this->sizes[0];
            }

            $criteria['limit'] = $limit;
        }

        if ($hasOffset) {
            $offset = (int) $query[$this->map['offset']];

            if (0 > $offset) {
                $offset = 0;
            } elseif (0 !== ($offset % $limit)) {
                $offset = ceil($offset / $limit) * $limit;
            }

            $page = $offset / $limit + 1;
        } else {
            $page = (int) $query[$this->map['page']];

            if (1 > $page) {
                $page = 1;
            }

            $offset = $limit * ($page - 1);
        }

        $criteria['offset'] = $offset;
        $criteria['page'] = $page;

        return $criteria;
    }
}
