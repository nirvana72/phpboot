<?php
namespace PhpBoot\DB;

class SqlHelper
{
    /**
     * @var string
     */
    private $where;

    /**
     * @var array
     */
    private $params;

    public function getWhere() {
        $this->where = trim($this->where);
        if (strpos($this->where, 'and') === 0) {
          $this->where = 'where' . substr($this->where, 3);
        }
        return $this->where;
    }

    public function getParams() {
      return $this->params;
    }

    public static function create($where = '') {
        $helper = new SqlHelper();
        $helper->where = $where;
        $helper->params = [];
        return $helper;
    }

    /**
     * make
     * @param string $symbol =|!=|>|<=...
     * @param array $array ['a' => 1, 't2.b' => 'b' ...]
     * @return self
     */
    public function makeWhere($symbol, $array) {
        foreach ($array as $k => $v) {
          if ($v !== '') {
            $ary = explode('.', $k);
            $p = end($ary);
            $p = $this->camelizeStr($p);
            $this->where .= " and ({$k} {$symbol} :{$p})";
            $this->params[$p] = $v;
          }
        }
        return $this;
    }

    /**
     * make
     * @param int $page
     * @param int $limit
     * @return string
     */
    public function makeLimit($page, $limit) {
      $start = ($page-1) * $limit;
      $limit = "limit {$start},{$limit}";
      return $limit;
    }

    // 下划线转驼峰
    private function camelizeStr($uncamelized_words, $separator = '_') {
      $uncamelized_words = $separator. str_replace($separator, " ", strtolower($uncamelized_words));
      return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator );
    }
}