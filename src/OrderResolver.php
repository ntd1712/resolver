<?php

namespace Chaos\Support\Resolver;

/**
 * Class OrderResolver.
 *
 * @author t(-.-t) <ntd1712@mail.com>
 */
class OrderResolver
{
    // <editor-fold defaultstate="collapsed" desc="Default properties">

    /**
     * @var int
     */
    private $limit = 10;

    /**
     * @var array
     */
    private $map = [
        'direction' => 'direction',
        'nulls' => 'nulls',
        'order' => 'order'
    ];

    /**
     * @var array
     */
    private $permit = [];

    /**
     * @param int $limit Number of fields used for ordering.
     *
     * @return $this
     */
    public function setLimit($limit)
    {
        $this->limit = (int) $limit;

        return $this;
    }

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
     * @param array $permit Controls which fields used for ordering.
     *
     * @return $this
     */
    public function setPermit(array $permit)
    {
        $this->permit = $permit;

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
     * GET ?order=desc(Id),asc(Name),asc(CreatedBy)
     *     ?order=[
     *       {"property":"Id","direction":"desc","nulls":"first"},
     *       {"property":"Name","direction":"asc","nulls":"last"}
     *     ]
     *     ?order=-Id,+Name
     *     ?order[Id]=desc&order[Name]=asc
     *     ?order=Name&direction=asc
     *
     * <code>
     * $criteria = [];
     * OrderResolver::make()->resolve([], $criteria);                  // false
     *
     * OrderResolver::make()->resolve(['order' => 'Name'], $criteria); // ['order' => ['Name' => 'ASC']]
     * OrderResolver::make()->resolve(                                 // ['order' => ['Name' => 'DESC']]
     *   ['order' => 'Name', 'direction' => 'desc'],
     *   $criteria
     * );
     * OrderResolver::make()->resolve(                                 // ['order' => ['Name' => 'ASC NULLS LAST']]
     *   ['order' => 'Name', 'nulls' => 'last'],
     *   $criteria
     * );
     * OrderResolver::make()->resolve(                                 // ['order' => ['Name' => 'DESC NULLS FIRST']]
     *   ['order' => 'Name', 'direction' => 'desc', 'nulls' => 'first'],
     *   $criteria
     * );
     *
     * OrderResolver::make()              // ['order' => [['Id' => 'DESC NULLS FIRST'],['Name' => 'ASC NULLS LAST']]]
     *   ->setParameterMap(['direction' => 'direction', 'nulls' => 'nulls', 'order' => 'order'])
     *   ->setPermit($this->service->repository->fieldMappings)
     *   ->resolve(
     *     [
     *       'order' => [
     *         ['property' => 'Id', 'direction' => 'desc', 'nulls' => 'first'],
     *         ['property' => 'Name', 'direction' => 'asc', 'nulls' => 'last']
     *       ]
     *     ],
     *     $criteria
     *   );
     * </code>
     *
     * <code>
     * $criteria = [
     *   'order' => 'Id DESC, Name',
     *   'order' => 'Id DESC NULLS FIRST, Name ASC NULLS LAST',
     *   'order' => ['Id DESC NULLS FIRST', 'Name ASC NULLS LAST'],
     *   'order' => ['Id' => 'DESC NULLS FIRST', 'Name' => 'ASC NULLS LAST']
     * ];
     * </code>
     *
     * @param array $query Query to resolve.
     * @param array $criteria Resolved criteria.
     *
     * @return false|array Returns an array of values, like: ['order' => (string|array)], FALSE otherwise.
     */
    public function resolve(array $query, array &$criteria = [])
    {
        if (empty($query[$this->map['order']])) {
            return false;
        }

        if (is_string($order = $query[$this->map['order']])) {
            $decoded = urldecode($order);

            if (false !== strpos($decoded, '(')) {
                preg_match_all('#(asc|desc)\(([\w]+)\)#i', $decoded, $matches);
                $order = [];

                foreach ($matches[2] as $index => $property) {
                    $order[] = [
                        'property' => $property,
                        'direction' => $matches[1][$index]
                    ];
                }
            } elseif (false !== strpos($decoded, '{')) {
                $decoded = json_decode($decoded, true);
                $order = json_last_error() ? [] : (array) $decoded;
            } elseif (false !== strpos($decoded, ',')) {
                $matches = preg_split('#\s*,\s*#', $decoded, -1, PREG_SPLIT_NO_EMPTY);
                $order = [];

                foreach ($matches as $property) {
                    $order[] = [
                        'property' => trim($property, '-+ '),
                        'direction' => 0 === strpos($property, '-') ? null : 'desc'
                    ];
                }
            } else {
                $order = [
                    [
                        'property' => trim($decoded),
                        'direction' => isset($query[$this->map['direction']])
                            ? $query[$this->map['direction']] : null,
                        'nulls' => isset($query[$this->map['nulls']])
                            ? $query[$this->map['nulls']] : null
                    ]
                ];
            }
        }

        $orderSet = $this->sanitize($order);

        if (!empty($orderSet)) {
            if (isset($criteria['order'])) {
                $order = $criteria['order'];
                $isArray = is_array($order);
                $isString = is_string($order);

                foreach ($orderSet as $property => $direction) {
                    if ($isArray) {
                        $order[$property] = $direction;
                    } elseif ($isString) {
                        $order .= ', ' . $property . ' ' . $direction;
                    }
                }

                $criteria['order'] = $order;
            } else {
                $criteria['order'] = $orderSet;
            }
        }

        return $criteria;
    }

    /**
     * @param array $criteria The criteria.
     *
     * @return array Returns an array of values, like: ['Id' => 'DESC', 'Name' => 'ASC']
     */
    private function sanitize(array $criteria)
    {
        $orderSet = [];
        $count = 0;

        foreach ($criteria as $key => $value) {
            if (is_string($key)) {
                if (!isset($this->permit[$key])) {
                    continue;
                }

                $orderSet[$key] = !is_string($value) || 'DESC' !== strtoupper($value)
                    ? 'ASC'
                    : 'DESC';
            } else {
                if (!is_array($value) || empty($value['property']) || !isset($this->permit[$value['property']])) {
                    continue;
                }

                $orderSet[$value['property']] = empty($value['direction']) || !is_string($value['direction'])
                || 'DESC' !== strtoupper($value['direction'])
                    ? 'ASC'
                    : 'DESC';

                if (isset($value['nulls']) && is_string($value['nulls'])) {
                    $nulls = 'NULLS ' . strtoupper($value['nulls']);

                    if ('NULLS FIRST' === $nulls || 'NULLS LAST' === $nulls) {
                        $orderSet[$value['property']] .= ' ' . $nulls;
                    }
                }
            }

            if ($this->limit <= ++$count) {
                break;
            }
        }

        return $orderSet;
    }
}
