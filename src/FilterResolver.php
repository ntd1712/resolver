<?php

namespace Chaos\Support\Resolver;

use Laminas\Db\Sql\Predicate\Predicate;

use function Chaos\escape;
use function Chaos\escapeDate;
use function Chaos\escapeQuotes;

/**
 * Class FilterResolver.
 *
 * @author t(-.-t) <ntd1712@mail.com>
 */
class FilterResolver
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
        'filter' => 'filter',
        '|eq|' => '=',
        '|neq|' => '<>',
        '|gt|' => '>',
        '|gte|' => '>=',
        '|lt|' => '<',
        '|lte|' => '<='
    ];

    /**
     * @var int
     */
    private $minlength = 3;

    /**
     * @var array
     */
    private $permit = [];

    /**
     * @param int $limit Number of fields used for matching.
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
     * @param int $minlength Minimum characters before starting a search.
     *
     * @return $this
     */
    public function setMinlength($minlength)
    {
        $this->minlength = (int) $minlength;

        return $this;
    }

    /**
     * @param array $permit Controls which fields used for matching.
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
     * GET ?Id=1&Name=demo
     *     ?CreatedAt[gte]=9/29/2014&CreatedAt[lte]=10/29/2014
     *     ?filter=[
     * {"predicate":"between|notBetween","identifier":"CreatedAt","minValue":"9/29/2014","maxValue":"10/29/2014",
     *  "combine":"AND|OR","nesting":"nest|unnest"},
     * {"predicate":"equalTo|notEqualTo|lessThan|greaterThan|lessThanOrEqualTo|greaterThanOrEqualTo",
     *  "left":"Profile.DisplayName","right":"ntd1712","leftType":"identifier","rightType":"value",
     *  "combine":"AND|OR","nesting":"nest|unnest"},
     * {"predicate":"expression","expression":"CONCAT(?0,?1) IS NOT NULL","parameters":["CreatedAt","UpdatedAt"],
     *  "combine":"AND|OR","nesting":"nest|unnest"}
     * {"predicate":"in|notIn","identifier":"Name","valueSet":["ntd1712","moon",17],
     *  "combine":"AND|OR","nesting":"nest|unnest"},
     * {"predicate":"isNull|isNotNull","identifier":"Name","combine":"AND|OR","nesting":"nest|unnest"},
     * {"predicate":"like|notLike","identifier":"Name","like|notLike":"ntd1712",
     *  "combine":"AND|OR","nesting":"nest|unnest"}
     * {"predicate":"literal","literal":"NotUse=false","combine":"AND|OR","nesting":"nest|unnest"}
     *     ]
     *     ?filter[Id]=1&filter[Name]=demo
     *     ?filter=ntd1712
     *
     * <code>
     * ?filter=[
     *   {"predicate":"equalTo","left":"Id","right":"1","leftType":"identifier","rightType":"value","combine":"AND",
     *    "nesting":"nest"},
     *   {"predicate":"equalTo","left":"Id","right":"2","leftType":"identifier","rightType":"value","combine":"OR"},
     *   {"predicate":"like","identifier":"Name","like":"demo","combine":"and","nesting":"unnest"}
     * ]
     *
     * // equals to: (Id = 1 or Id = 2) and Name like 'demo'
     * // and is equivalent to:
     *
     * $predicate = new \Laminas\Db\Sql\Predicate\Predicate();
     * $predicate
     *   ->nest()
     *     ->equalTo('Id', 1)
     *     ->or
     *     ->equalTo('Id', 2)
     *   ->unnest()
     *   ->and
     *   ->like('Name', 'demo');
     * </code>
     *
     * <code>
     * $criteria = [];
     * FilterResolver::make()->resolve([], $criteria); // false
     *
     * FilterResolver::make()
     *   ->setParameterMap(['filter' => 'filter'])
     *   ->setPermit($this->service->repository->fieldMappings)
     *   ->resolve(['filter' => 'ntd1712'], $criteria);
     * </code>
     *
     * <code>
     * $criteria = [
     *   'where' => \Laminas\Db\Sql\Predicate\Predicate $predicate
     *   'where' => 'Id = 1 OR Name = "ntd1712"',
     *   'where' => ['Id' => 1, 'Name' => 'ntd1712'] // a.k.a. 'Id = 1 AND Name = "ntd1712"'
     *   'where' => ['Id' => 1, 'Name = "ntd1712"']  // a.k.a. 'Id = 1 AND Name = "ntd1712"'
     * ];
     * </code>
     *
     * @param array $query Query to resolve.
     * @param array $criteria Resolved criteria.
     *
     * @return false|array
     */
    public function resolve(array $query, array &$criteria = [])
    {
        if (!isset($query[$this->map['filter']]) || '' === ($filter = $query[$this->map['filter']])) {
            $filter = array_diff_key($query, $this->map);

            if (empty($filter)) {
                return false;
            }
        } elseif (is_string($filter)) {
            $filter = urldecode($filter);

            if (false !== strpos($filter, '{')) {
                $decoded = json_decode($filter, true);

                if (!is_null($decoded) && JSON_ERROR_NONE === json_last_error()) {
                    $filter = $decoded;
                }
            }
        }

        $filterSet = $this->sanitize($filter);

        if (0 !== count($filterSet)) {
            if (isset($criteria['where'])) {
                $filterSet->addPredicates($criteria['where']);
            }

            $criteria['where'] = $filterSet;
        }

        return $criteria;
    }

    /**
     * @param string|array $query Query to resolve.
     * @param null|\Laminas\Db\Sql\Predicate\PredicateInterface $predicate Optional.
     *
     * @return Predicate|\Laminas\Db\Sql\Predicate\PredicateInterface
     */
    private function sanitize($query, $predicate = null)
    {
        if (null === $predicate) {
            $predicate = new Predicate();
        }

        if (is_array($query)) {
            foreach ($query as $k => $v) {
                if (is_string($k)) {
                    if (is_array($v)) {
                        if (!isset($this->permit[$k])) {
                            continue;
                        }

                        $count = 0;
                        $andX = $parameters = [];

                        foreach ($v as $key => $value) {
                            if (isset($this->map[$op = '|' . strtolower($key) . '|'])) {
                                $andX[] = "%1\$s.{$k} {$op} ?" . $count++;
                                $parameters[] = $value;
                            }
                        }

                        $v = [
                            'predicate' => 'expression',
                            'expression' => '(' . implode(') AND (', $andX) . ')',
                            'parameters' => $parameters
                        ];
                    } else {
                        $v = ['predicate' => 'equalTo', 'left' => $k, 'right' => $v];
                    }
                } elseif (!is_array($v) || empty($v['predicate'])) {
                    continue;
                }

                if (isset($v['nesting']) && ('nest' === $v['nesting'] || 'unnest' === $v['nesting'])) {
                    /* @see \Laminas\Db\Sql\Predicate\Predicate::nest */
                    /* @see \Laminas\Db\Sql\Predicate\Predicate::unnest */
                    $predicate = $predicate->{$v['nesting']}();
                }

                if (
                    isset($v['combine'])
                    && (Predicate::OP_OR === $v['combine'] || strtolower(Predicate::OP_OR) === $v['combine'])
                ) {
                    $predicate->or;
                }

                switch ($v['predicate']) {
                    case 'between':
                    case 'notBetween':
                        if (
                            empty($v['identifier'])
                            || !isset($v['minValue'])
                            || !isset($v['maxValue'])
                            || !isset($this->permit[$v['identifier']])
                        ) {
                            break;
                        }

                        /* @see \Laminas\Db\Sql\Predicate\Predicate::between */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::notBetween */
                        $predicate->{$v['predicate']}(
                            $v['identifier'],
                            escapeDate($v['minValue']),
                            escapeDate($v['maxValue'], 86399)
                        );
                        break;
                    case 'equalTo':
                    case 'notEqualTo':
                    case 'greaterThan':
                    case 'greaterThanOrEqualTo':
                    case 'lessThan':
                    case 'lessThanOrEqualTo':
                        if (!isset($v['left']) || !isset($v['right'])) {
                            break;
                        }

                        $leftParts = explode('.', $v['left']);
                        $rightParts = explode('.', $v['right']);

                        if (empty($v['leftType']) || Predicate::TYPE_VALUE !== $v['leftType']) {
                            $v['leftType'] = Predicate::TYPE_IDENTIFIER;
                        }

                        if (empty($v['rightType']) || Predicate::TYPE_IDENTIFIER !== $v['rightType']) {
                            $v['rightType'] = Predicate::TYPE_VALUE;
                        }

                        if ($v['leftType'] == $v['rightType']) {
                            $v['leftType'] = Predicate::TYPE_IDENTIFIER;
                            $v['rightType'] = Predicate::TYPE_VALUE;
                        }

                        if (Predicate::TYPE_IDENTIFIER !== $v['leftType']) {
                            $v['left'] = escapeDate($v['left']);

                            if (isset($rightParts[1])) {
                                $v['right'] = "JSON_EXTRACT(%1\$s.{$rightParts[0]}, '\$"
                                    . (str_replace($rightParts[0], '', $v['right'])) . "')";
                            }
                        } elseif (!isset($this->permit[$leftParts[0]])) {
                            break;
                        }

                        if (Predicate::TYPE_IDENTIFIER !== $v['rightType']) {
                            $v['right'] = escapeDate($v['right']);

                            if (isset($leftParts[1])) {
                                $v['left'] = "JSON_EXTRACT(%1\$s.{$leftParts[0]}, '\$"
                                    . (str_replace($leftParts[0], '', $v['left'])) . "')";
                            }
                        } elseif (!isset($this->permit[$rightParts[0]])) {
                            break;
                        }

                        /* @see \Laminas\Db\Sql\Predicate\Predicate::equalTo */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::notEqualTo */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::lessThan */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::greaterThan */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::lessThanOrEqualTo */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::greaterThanOrEqualTo */
                        $predicate->{$v['predicate']}($v['left'], $v['right'], $v['leftType'], $v['rightType']);
                        break;
                    case 'expression':
                    case 'expr':
                        if (empty($v['expression'])) {
                            break;
                        }

                        $v['expression'] = strtr(escape($v['expression']), $this->map);

                        if (isset($v['parameters'])) {
                            if (!is_array($v['parameters'])) {
                                $v['parameters'] = [$v['parameters']];
                            }

                            foreach ($v['parameters'] as $key => &$value) {
                                if (!is_scalar($value)) {
                                    unset($v['parameters'][$key]);
                                } elseif (!isset($this->permit[$value])) {
                                    $value = trim(escapeQuotes($value), "'");
                                }
                            }
                            unset($value);

                            $v['parameters'] = array_values($v['parameters']);
                        } else {
                            $v['parameters'] = null;
                        }

                        $predicate->expression($v['expression'], $v['parameters']);
                        break;
                    case 'in':
                    case 'notIn':
                        if (empty($v['identifier']) || empty($v['valueSet']) || !is_array($v['valueSet'])) {
                            break;
                        }

                        if (is_array($v['identifier'])) {
                            foreach ($v['identifier'] as $key => $value) {
                                if (!isset($this->permit[$value])) {
                                    unset($v['identifier'][$key]);
                                }
                            }

                            if (empty($v['identifier'])) {
                                break;
                            }

                            $v['identifier'] = array_values($v['identifier']);
                        } elseif (!isset($this->permit[$v['identifier']])) {
                            break;
                        }

                        foreach ($v['valueSet'] as &$value) {
                            $value = escapeQuotes($value);
                        }
                        unset($value);

                        /* @see \Laminas\Db\Sql\Predicate\Predicate::in */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::notIn */
                        $predicate->{$v['predicate']}($v['identifier'], $v['valueSet']);
                        break;
                    case 'isNull':
                    case 'isNotNull':
                        if (empty($v['identifier']) || !isset($this->permit[$v['identifier']])) {
                            break;
                        }

                        /* @see \Laminas\Db\Sql\Predicate\Predicate::isNull */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::isNotNull */
                        $predicate->{$v['predicate']}($v['identifier']);
                        break;
                    case 'like':
                    case 'notLike':
                        if (
                            empty($v['identifier'])
                            || empty($v[$v['predicate']])
                            || !isset($this->permit[$v['identifier']])
                        ) {
                            break;
                        }

                        $filtered = escapeQuotes($v[$v['predicate']]);
                        $v[$v['predicate']] = "'%" . trim($filtered, "'") . "%'";

                        /* @see \Laminas\Db\Sql\Predicate\Predicate::like */
                        /* @see \Laminas\Db\Sql\Predicate\Predicate::notLike */
                        $predicate->{$v['predicate']}($v['identifier'], $v[$v['predicate']]);
                        break;
                    case 'literal':
                        if (empty($v['literal'])) {
                            break;
                        }

                        $v['literal'] = escape($v['literal']);
                        $predicate->literal($v['literal']);
                        break;
                    default:
                }
            }
        } elseif (is_string($query)) {
            $count = 0;
            $predicateSet = new Predicate();
            $searchable = $this->minlength <= strlen($query);

            $equalValue = escapeQuotes($query);
            $likeValue = "'%" . trim($equalValue, "'") . "%'";

            foreach ($this->permit as $k => $v) {
                if (
                    ('string' === $v['type'] || 'text' === $v['type'])
                    && ($searchable || (($isChar = isset($v['options'])) && isset($v['options']['fixed'])))
                ) {
                    $predicateSet->or;
                    isset($isChar) && $isChar
                        ? $predicateSet->equalTo($k, $equalValue)
                        : $predicateSet->like($k, $likeValue);

                    if ($this->limit <= ++$count) {
                        break;
                    }
                }
            }

            if (0 !== count($predicateSet)) {
                $predicate->predicate($predicateSet);
            }
        }

        return $predicate;
    }
}
