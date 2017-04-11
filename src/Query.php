<?php

namespace chenyuanqi\elasticsearch;

use Elasticsearch\ClientBuilder;

class Query
{
    /**
     * 客户端链接
     *
     * @var
     */
    protected static $client;

    /**
     * 索引配置
     *
     * @var
     */
    protected $config;

    /**
     * 索引名称
     *
     * @var
     */
    protected $index;

    /**
     * 类型名称
     *
     * @var
     */
    protected $type;

    /**
     * 查询条件
     *
     * @var array
     */
    protected $where = [];

    /**
     * 聚合条件
     *
     * @var array
     */
    protected $aggregations = [];

    /**
     * 查询结果
     *
     * @var
     */
    protected $output;

    /**
     * 查询字段
     *
     * @var array
     */
    protected $columns = [];

    /**
     * 查询限制数量
     *
     * @var int
     */
    protected $limit = 10;

    /**
     * 查询偏移数量
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * 查询排序
     *
     * @var array
     */
    protected $order = [];

    /**
     * 每次 scroll 取结果数量
     *
     * @var int
     */
    protected $scroll_size;

    /**
     * scroll 请求过期时间 (尽量不要设置过长)
     * @var string
     */
    protected $scroll_expire;

    /**
     * scroll 搜索类型
     *
     * @var string
     */
    protected $scroll_type;

    /**
     * scroll 执行次数
     *
     * @var int
     */
    protected $scroll_twice;

    /**
     * 获取配置，建立链接
     *
     */
    public function __construct()
    {
        // 默认索引及类型判断
        if (!isset($this->type)) {
            $this->index = \Config::get('elasticsearch.default_index', 'default');
        }
        if (!isset($this->type)) {
            $this->type = \Config::get('elasticsearch.default_type', 'default');
        }

        // 获取索引配置
        $config       = \Config::get('elasticsearch.'.$this->index, []);
        if(!isset($config['indices'][$this->type])) {
            echo 'Keep the configure right, please!';
            exit();
        }

        // 类型下的配置
        $this->config = $config['indices'][$this->type];
        static::$client = ClientBuilder::create()->setHosts($config['connection'])->build();
    }

    /**
     * 设置索引
     *
     * @param  string $index 索引名称
     *
     * @return $this
     */
    public function index($index = null)
    {

        $this->index = $index;

        return $this;
    }

    /**
     * 设置类型
     *
     * @param $type
     *
     * @return $this
     */
    public function type($type = null)
    {

        $this->type = $type;

        return $this;
    }

    /**
     * 获取链接 (外部使用)
     *
     * @return mixed
     */
    public function getClient()
    {
        return static::$client;
    }

    /**
     * 批量处理限制次数
     *
     * @return mixed
     */
    public function getLimitByConfig()
    {
        return array_get($this->config, 'limit', 10000);
    }

    /**
     * 对应 model 实例
     *
     * @return \stdClass
     */
    public function getModel()
    {
        if($model = array_get($this->config, 'model', '')) {
            return new $model;
        }

        return new \stdClass();
    }

    /**
     * 创建索引
     *
     * @return array
     */
    public function createIndex()
    {
        try {
            return self::$client->indices()->create([
                'index' => $this->index
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 输出调试信息
     */
    public function debug()
    {
        dd(self::$client->transport->getConnection()->getLastRequestInfo());
    }


    /**
     * 创建映射
     *
     * @return array
     */
    public function createMapping()
    {
        try {
            return self::$client->indices()->create([
                'index' => $this->index,
                'body'  => [
                    'mappings' => $this->config['mappings']
                ]
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 更新映射
     *
     * @return array
     */
    public function updateMapping()
    {
        try {
            return self::$client->indices()->putMapping([
                'index' => $this->index,
                'type'  => $this->type,
                'body'  => $this->config['mappings']
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 删除映射
     *
     * @return array
     */
    public function deleteMapping()
    {
        try {
            return self::$client->indices()->deleteMapping([
                'index' => $this->index,
                'type'  => $this->type,
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 新增数据
     *
     * @param $body
     * @param $id
     *
     * @return array
     */
    public function insert($body, $id = '')
    {
        try {
            $params = [
                'index' => $this->index,
                'type'  => $this->type,
                'body'  => $this->filterFields($body)
            ];
            $id && $params['id'] = $id;

            return self::$client->create($params);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 根据 ID 更新数据 (局部更新)
     *
     * @param array  $body
     * @param string $id
     *
     * @return array
     */
    public function updateById(array $body, $id)
    {
        try {
            return self::$client->update([
                'index' => $this->index,
                'type'  => $this->type,
                'id'    => $id,
                'body'  => [
                    'doc' => $this->filterFields($body)
                ]
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 根据查询条件更新数据
     *
     * @param array $body
     *
     * @return array
     */
    public function update(array $body)
    {
        if (!$this->where) {
            return [];
        }

        try {
            $params = $this->filterFields($body);
            $inline = collect($params)->map(function($item, $key) {
                return "ctx._source.{$key} = {$key}";
            })->implode(';');

            return self::$client->updateByQuery([
                'index'     => $this->index,
                'type'      => $this->type,
                'conflicts' => 'proceed',
                'body'      => [
                    'query'  => $this->where['query'],
                    'script' => [
                        'inline' => $inline,
                        'params' => $params
                    ]
                ]
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 新增或更新数据
     *
     * @param        $body
     * @param string $id
     *
     * @return array
     */
    public function insertOrUpdate($body, $id)
    {
        try {
            $params = $this->filterFields($body);
            $inline = collect($params)->map(function($item, $key) {
                return "ctx._source.{$key} = {$key}";
            })->implode(';');

            return self::$client->update([
                'index'     => $this->index,
                'type'      => $this->type,
                'id'        => $id,
                'conflicts' => 'proceed',
                'body'      => [
                    'script' => [
                        'inline' => $inline,
                        'params' => $params
                    ],
                    'upsert' => [
                        'id' => $id
                    ]
                ]
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 指定字段自增
     *
     * @param  string $field 自增字段
     * @param  int    $value 自增数值 (默认 1)
     *
     * @return array
     */
    public function increase($field, $value = 1)
    {
        if(!$field || !$this->where) {
            return [];
        }

        try {
            return self::$client->updateByQuery([
                'index'     => $this->index,
                'type'      => $this->type,
                'conflicts' => 'proceed',
                'body'      => [
                    'query'  => $this->where['query'],
                    'script' => [
                        'inline' => "ctx._source.{$field} += count",
                        'params' => [
                            'count' => $value
                        ]
                    ]
                ]
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 指定字段自减
     *
     * @param  string $field 自减字段
     * @param  int    $value 自减数值
     *
     * @return array
     */
    public function decrease($field, $value = 1)
    {
        if(!$field || !$this->where) {
            return [];
        }

        try {
            return self::$client->updateByQuery([
                'index'     => $this->index,
                'type'      => $this->type,
                'conflicts' => 'proceed',
                'body'      => [
                    'query'  => $this->where['query'],
                    'script' => [
                        'inline' => "ctx._source.{$field} -= count",
                        'params' => [
                            'count' => $value
                        ]
                    ]
                ]
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 根据 ID 删除数据
     *
     * @param $id
     *
     * @return array
     */
    public function deleteById($id)
    {
        try {
            return self::$client->delete([
                'index' => $this->index,
                'type'  => $this->type,
                'id'    => $id
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 根据查询条件删除数据
     *
     * @return array
     */
    public function delete()
    {
        if (!$this->where) {
            return [];
        }

        try {
            return self::$client->deleteByQuery([
                'index' => $this->index,
                'type'  => $this->type,
                'body'  => $this->where
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 删除并清空索引
     *
     * @return array
     */
    public function truncate()
    {
        try {
            return self::$client->indices()->delete([
                'index' => $this->index
            ]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 批量增删改
     *
     * @param  array $data
     *         e.g. []
     *
     * @return array
     */
    public function bulk($data = [])
    {
        if(!count($data)) {
            return [];
        }

        try {
            $params['body'] = [];
            // 构造 body
            foreach ($data as $item) {
                $allowOperation = ['index', 'create', 'update', 'delete'];
                $type           = isset($item[0]) && in_array($item[0], $allowOperation, true) ? $item[0] : 'index';

                $params['body'][][$type] = [
                    '_index' => $this->index,
                    '_type'  => $this->type,
                    '_id'    => $item['_id']
                ];

                if('delete' === $type) {
                    continue;
                } elseif ('update' === $type) {
                    unset($item[0], $item['_id']);
                    $params['body'][]['doc'] = $this->filterFields($item);
                } else {
                    unset($item[0], $item['_id']);
                    $params['body'][] = $this->filterFields($item);
                }
            }

            return self::$client->bulk($params);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 查找记录
     *
     * @param $id
     *
     * @return array
     */
    public function find($id)
    {
        try {
            return self::$client->get([
                'index'  => $this->index,
                'type'   => $this->type,
                'id'     => $id,
                'client' => [
                    'ignore' => [400, 404]
                ]
            ]);
        } catch (\Exception $e) {
            echo $e->getCode() . ': ' . $e->getMessage() . "\n";
            exit();
        }
    }

    /**
     * 执行查询
     *
     * @param  boolean $paging 是否分页
     * @return array
     */
    public function search($paging = false)
    {
        $params = [
            'index' => $this->index,
            'type'  => $this->type,
            'body'  => $this->where
        ];
        // 是否指定字段
        if($this->columns) {
            $params['body']['_source']['includes'] = $this->columns;
        }
        // 是否使用聚合查询
        if($this->aggregations) {
            $params['body']['aggregations'] = $this->aggregations;
        }
        // 是否开启分页
        if($paging) {
            $params['from'] = $this->offset;
            $params['size'] = $this->limit;
        }
        // 是否开启滚屏或者扫描
        $this->scroll_type   && $params['search_type']  = $this->scroll_type;
        $this->scroll_size   && $params['body']['size'] = $this->scroll_size;
        $this->scroll_expire && $params['scroll']       = $this->scroll_expire;
        // 是否设置排序
        if($this->order) {
            $params['body']['sort'] = $this->order;
        }
        $this->output = self::$client->search($params);
        // 如果是滚屏或扫描，结果再次查询
        $this->searchByScrollId();

        // 聚合查询结果特殊处理
        if($this->aggregations) {
            return $this->output['aggregations']['total']['value'];
        }

        return $this->outputFormat();
    }

    /**
     * 根据 scroll_id 查询数据
     * @param  string $scroll_id
     *
     * @return $this
     */
    public function searchByScrollId($scroll_id = null)
    {
        $scroll_id = $scroll_id ?: (isset($this->output['_scroll_id']) ? $this->output['_scroll_id'] : null);
        if ($scroll_id) {
            $this->output = self::$client->scroll([
                "scroll"    => $this->scroll_expire,
                "scroll_id" => $scroll_id
            ]);
        }

        return $this;
    }

    /**
     * 根据 scroll id 清理缓存数据
     *
     * @param  string $scroll_id
     *
     * @return bool
     */
    public function deleteByScrollId($scroll_id = null)
    {
        $result    = false;
        $scroll_id = $scroll_id ?: (isset($this->output['_scroll_id']) ? $this->output['_scroll_id'] : null);
        if ($scroll_id) {
            $result = self::$client->clearScroll([
                "scroll_id" => $scroll_id,
                'client'    => [
                    'ignore' => 404
                ]
            ]);
        }

        return $result;
    }

    /**
     * 获取查询第一条
     *
     * @return array
     */
    public function first()
    {
        $output = $this->search()->outputFormat();

        return isset($output[0]) ? $output[0] : [];
    }

    /**
     * 查询命中总数量
     *
     * @return int
     */
    public function count()
    {
        // 无结果时，自行查询
        if (!isset($this->output['hits']['total'])) {
            $this->search();
        }
        return isset($this->output['hits']['total']) ? $this->output['hits']['total'] : 0;
    }

    /**
     * 选择展示字段，默认所有
     *
     * @param array $columns
     *
     * @return $this
     */
    public function pluck(array $columns)
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * 构造 query string 查询条件
     *
     * @param  string $string 查询条件如 'package_name:"com.qq"'
     * @param  array  $fields 指定查询域 (字段)
     *
     * @return $this
     */
    public function queryString($string, $fields = [])
    {
        $where['query'] = [
            'query_string' => [
                'query' => $string
            ]
        ];
        $fields && $where['query']['query_string']['fields'] = $fields;
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 构造 match query 查询条件
     *
     * @param $field
     * @param $value
     * @param string $type 类型 (_all, match, multi_match)
     *
     * @return $this
     */
    public function match($field, $value = '', $type = '_all')
    {
        switch ($type) {
            case 'match':
                $where['query'] = [
                    'match' => [
                        $field => $value
                    ]
                ];
                break;

            case 'multi_match':
                $where['query'] = [
                    'multi_match' => [
                        'type'   => 'most_fields',
                        'fields' => $field,
                        'query'  => $value
                    ]
                ];
                break;

            default:
                $where['query'] = [
                    'match' => [
                        '_all' => $field
                    ]
                ];
                break;
        }
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 构造 term query 查询条件
     *
     * @param $field
     * @param $value
     *
     * @return $this
     */
    public function term($field, $value)
    {
        $where['query'] = [
            'term' => [
                $field => $value
            ]
        ];
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 构造 bool query 查询条件
     *
     * @param $field
     * @param $value
     * @param string $type 类型 (must, must_not, should, filter)
     *
     * @return $this
     */
    public function bool($field, $value, $type = 'must')
    {
        $where['query'] = [
            'bool' => [
                $type => [
                    'term' => [$field => $value]
                ]
            ]
        ];
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 构造 range query 查询条件
     * @param string $field
     * @param array  $range          范围
     * @param array  $parameter      范围对应的 query 符号参数
     * @param array  $extraParameter 额外参数, e.g. ["format" =>"dd/MM/yyyy||yyyy"] 或 ["time_zone" => "+01:00"]
     *
     * @return $this
     */
    public function range($field, $range = [0, 100], $parameter = ['gte', 'lte'], $extraParameter = [])
    {
        $where['query'] = [
            'range' => [
                $field => [
                    [$parameter[0] => $range[0]],
                    [$parameter[1] => $range[1]]
                ]
            ]
        ];
        $extraParameter && array_push($where['query']['range'][$field], $extraParameter);
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 包含 ID 查询
     *
     * @param  array $ids
     *
     * @return $this
     */
    public function ids(array $ids)
    {
        $where['query'] = [
            'ids' => [
                'values' => $ids
            ]
        ];
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 包含字段查询
     *
     * @param string $field
     * @param array  $value
     *
     * @return $this
     */
    public function whereIn($field, array $value)
    {
        $where['query']['bool']['must']['bool']['should'] = collect($value)->map(function($item) use ($field) {
            return [
                'match' => [
                    $field => [
                        'query' => $item,
                        'type'  => 'phrase'
                    ]
                ]
            ];
        });
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 构造 where query 条件 (包含操作符 = != <> > >= < <=)
     *
     * @param      $field
     * @param null $operator
     * @param null $value
     *
     * @return $this
     */
    public function where($field, $operator = null, $value = null, $boolean = 'and')
    {
        // 默认操作符为 '='
        if(null === $value) {
            $value    = $operator;
            $operator = '=';
        }
        // and 或 or 条件处理
        $mode = 'and' === $boolean ? 'must' : 'should';

        switch ($operator) {
            case '=':
            default:
                $where['query']['bool']['must']['bool'][$mode]['match'][$field] = [
                    'query' => $value,
                    'type'  => 'phrase'
                ];
                break;

            case '<>':
            case '!=':
                $where['query']['bool']['must']['bool'][$mode]['bool']['must_not']['match'][$field] = [
                    'query' => $value,
                    'type'  => 'phrase'
                ];
                break;

            case '>':
            case '>=':
                $where['query']['bool']['must']['bool'][$mode]['range'][$field] = [
                    'from'          => $value,
                    'to'            => null,
                    'include_lower' => '>=' === $operator,
                    'include_upper' => true
                ];
                break;

            case '<':
            case '<=':
                $where['query']['bool']['must']['bool'][$mode]['range'][$field] = [
                    'from'          => null,
                    'to'            => $value,
                    'include_lower' => true,
                    'include_upper' => '<=' === $operator
                ];
                break;

            case 'like':
                $where['query']['bool']['must']['bool'][$mode]['wildcard'][$field] = strtr($value, ['_' => '?', '%' => '*']);
                break;
        }
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 构造 or where query 条件
     *
     * @param      $field
     * @param null $operator
     * @param null $value
     *
     * @return \chenyuanqi\elasticsearch\Query
     */
    public function orWhere($field, $operator = null, $value = null)
    {
        return $this->where($field, $operator, $value, 'or');
    }

    /**
     * 查询字段为 null
     *
     * @param  string $field
     *
     * @return $this
     */
    public function isNull($field)
    {
        $where['query']['bool']['must']['missing']['field'] = $field;
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 查询字段不为 null
     *
     * @param  string $field
     *
     * @return $this
     */
    public function isNotNull($field)
    {
        $where['query']['bool']['must']['bool']['must_not']['missing']['field'] = $field;
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 统计字段最大值
     *
     * @param  string $field
     *
     * @return array
     */
    public function max($field)
    {
        $this->aggregations['total']['max']['field'] = $field;

        return $this->search();
    }

    /**
     * 统计字段最小值
     *
     * @param  string $field
     *
     * @return array
     */
    public function min($field)
    {
        $this->aggregations['total']['min']['field'] = $field;

        return $this->search();
    }

    /**
     * 统计字段总和
     *
     * @param  string $field
     *
     * @return array
     */
    public function sum($field)
    {
        $this->aggregations['total']['sum']['field'] = $field;

        return $this->search();
    }

    /**
     * 统计字段平均值
     *
     * @param  string $field
     *
     * @return array
     */
    public function avg($field)
    {
        $this->aggregations['total']['avg']['field'] = $field;

        return $this->search();
    }

    /**
     * 构造过滤条件
     *
     * @param $field
     * @param $value
     *
     * @return $this
     */
    public function filter($field, $value)
    {
        $where['filter'] = [
            'term' => [
                $field => $value
            ]
        ];
        $this->where = array_merge_recursive($this->where, $where);

        return $this;
    }

    /**
     * 设置分页参数
     *
     * @param int $limit
     * @param int $offset
     *
     * @return $this
     */
    public function limit($offset = 0, $limit = 10)
    {
        $this->limit  = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * 设置 scroll 参数
     *
     * @param int    $size
     * @param string $expire 过期时间 (e.g. 1min, 2h, 3d)
     * @param string $type   查询类型 (e.t. scan or '')
     *
     * @return $this
     */
    public function scroll($size = 1000, $expire = '30s', $type = '')
    {
        $this->scroll_size   = $size;
        $this->scroll_expire = $expire;
        $this->scroll_type   = $type;

        return $this;
    }

    /**
     * 设置排序参数
     *
     * @param  array $order e.g ['name' => 'asc', 'age' => 'desc']
     *
     * @return $this
     */
    public function orderBy(array $order)
    {
        $this->order = collect($order)->map(function ($item){
            return ['order' => $item];
        })->toArray();

        return $this;
    }

    /**
     * 过滤字段
     *
     * @param array $body
     *
     * @return array
     */
    public function filterFields(array $body)
    {
        if (!$body) {
            return [];
        }

        return collect($body)->only($this->config['fields'])->all();
    }

    /**
     * 格式化输出结果
     *
     * @return array
     */
    protected function outputFormat()
    {
        if (!$this->output['hits']['total']) {
            return [];
        }
        // 格式化处理
        $result = collect($this->output['hits']['hits'])->map(function ($item){
            $item['_source']['_id'] = $item['_id'];

            return $item['_source'];
        })->toArray();
        // 总记录数
        $result['total'] = $this->output['hits']['total'];
        isset($this->output['_scroll_id']) && $result['_scroll_id'] = $this->output['_scroll_id'];

        return $result;
    }
}