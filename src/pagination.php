<?php

/**
 * Clamp a integer $int between $min and $max
 * 
 * @param int $x
 * @param int $min
 * @param int $max
 * @return int
 */
function clamp ($int, $min, $max) {
    if ($int < $min) {
        return $min;
    }
    
    if ($int > $max) {
        return $max;
    }
    
    return $int;
}

/**
 * Create a limited sequence
 * 
 * @param int $center
 * @param int $limit
 * @param int $min
 * @param int $max
 * @return int
 */
function limited_range ($center, $limit, $min, $max) {
    $range = array();
    $half  = intval($limit / 2);
    
    $start = $center - $half;
    $end   = $center + $half;
    
    for ($i = $start; $i < $end; $i ++) {
        if ($i >= $min && $i <= $max) {
            $range[] = $i;
        }
    }
    
    !in_array($min, $range) and array_unshift($range, $min);
    !in_array($max, $range) and array_push($range, $max);
    
    return $range;
}

/**
 * Generates pagination array
 * 
 * @param int $total - Total of rows/items
 * @param int $items - Items Per Page
 * @param int $page  - Page
 * @return array
 */
function pagination_generate ($total, $items, $page) {
    $offset = 0;
    
    if ($total > $items) {
        $offset = ($page - 1) * $items;
    }
    
    $pages = ceil($total / $items);
    $page  = clamp($page, 1, $pages);
    
    $pagination = array();
    
    if ($pages > 1) {
        $limit      = clamp($pages, 1, 9);
        $pagination = limited_range($page, $limit, 1, $pages);
    }
    
    $limit = intval($items - ($offset % $items));
    
    return compact('offset', 'pages', 'page', 'pagination', 'limit');
}

/**
 * Paginate a query
 * 
 * @param string $query
 * @param array $data
 * @param int $limit
 * @param int $page
 * @return array
 */
function paginate_query ($query, array $data, $limit, $page) {
    $countQuery = paginate_query_replace_select($query);
    $countQuery = pagiante_query_remove_joins($countQuery);
    
    $count = current(db_select($countQuery, $data, true));
    
    $pages = pagination_generate($count, $limit, $page);
    $query .= ' LIMIT ? OFFSET ?';
    
    $data[] = $pages['limit'];
    $data[] = $pages['offset'];
    
    return array(
        'items' => db_select($query, $data),
        'pages' => $pages,
    );
}

/**
 * Replace selects by COUNT(*) 
 * 
 * @param string $query
 * @return $query
 */
function paginate_query_replace_select ($query) {
    $from = stripos($query, 'from');
    $rest = substr($query, $from);
    
    return "SELECT COUNT(*) $rest";
}

/**
 * Remove joins
 * 
 * @param string $query
 * @return $query
 */
function pagiante_query_remove_joins ($query) {
    static $regex = '/(left|inner|outer|right) join [\w\d\s]+ ON \([^\)]+\)/i';
    
    return preg_replace($regex, ' ', $query);
}