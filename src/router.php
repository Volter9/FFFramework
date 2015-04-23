<?php

/**
 * Router storage
 * 
 * @param string $key
 * @param mixed $value
 * @return mixed
 */
function router ($key = null, $value = null) {
    static $repo = null;
    
    $repo or $repo = repo(array(
        'routes' => array()
    ));
    
    return $repo($key, $value);
}

/**
 * Route a url to action
 * 
 * @param string $url
 * @param string $action
 */
function route ($url, $action) {
    $route = parse_route($url, $action);
    
    router("routes.{$route['id']}", $route);
}

/**
 * Find route
 * 
 * @param string $url
 * @return array|bool
 */
function router_find ($url, $method) {
    $routes = router('routes');
    
    foreach ($routes as $route) {
        $routeUrl = route_process($route['url']);
        $pattern = "#^{$routeUrl}\$#i";
        
        if (
            in_array($route['method'], array('*', $method)) &&
            preg_match($pattern, $url, $matches)
        ) {
            array_shift($matches);
            
            $matches = array_map(function ($v) {
                return is_numeric($v) ? (int)$v : $v;
            }, $matches);
            
            return compact('route', 'matches');
        }
    }
    
    return false;
}

/**
 * Process a route
 * 
 * @param string $url
 * @return string
 */
function route_process ($url) {
    static $symbols = null;
    $symbols or $symbols = router('settings.symbols');
    
    $find    = array_keys($symbols);
    $replace = array_values($symbols);
    
    return str_replace($find, $replace, $url);
}

/**
 * Parse a full route from url and action strings
 * 
 * @param string $url
 * @param string $action
 * @return array
 */
function parse_route ($url, $action) {
    $name = 'index';
    
    list($method, $id, $url) = explode(' ', $url);
    
    if (strpos($action, ':') !== false) {
        list($action, $name) = explode(':', $action);
    }
    
    $url = trim($url, '/ ');
    
    return compact('method', 'id', 'url', 'action', 'name');
}

/**
 * Replace route url with parameters
 * 
 * @param string $url
 * @param array $params
 */
function route_replace ($url, $params) {
    $regex = '/:(\w+)\??/';
    
    if (count($params) !== 0) {
        $regex = array_fill(0, count($params), $regex);
    }
    else {
        $params = '';
    }
    
    return route_cleanup(trim(preg_replace($regex, $params, $url, 1), '/ '));
}

/**
 * Clean up the route's URL
 * 
 * @param string $url
 * @return string
 */
function route_cleanup ($url) {
    return chop(deduplicate($url, '/'), '/');
};

/**
 * Execute order 66
 * 
 * @param string $url
 * @param string $method
 * @return array
 */
function fetch_route ($url, $method) {
    if (!$found = router_find($url, $method)) {
        return show_404();
    }
    
    emit('router:found', $found['route']);
    
    return $found;
}

/**
 * Dispatch routing
 * 
 * @param array $found
 */
function dispatch ($found) {
    if (!$found) {
        return show_404();
    }
    
    $route  = $found['route'];
    $action = "action_{$route['name']}";
    
    router('route', $found);
    load_php($route['action']);
    
    if (
        function_exists('actions_init')                  === false ||
        function_exists($action)                         === false ||
        actions_init()                                   === false ||
        call_user_func_array($action, $found['matches']) === false
    ) {
        return show_404();
    }
}

/**
 * Get request url
 * 
 * @return string
 */
function get_url () {
    $root = router('settings.root');
    
    $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    if ($root !== '' && strpos($url, $root) !== false) {
        $url = explode($root, $url);
        $url = end($url);
    }
    
    $url = trim($url, ' /');
    $url = strpos($url, 'index.php') === 0 ? substr($url, 9) : $url;
    
    return $url;
}

/**
 * Get base URL
 * 
 * @param string $base
 * @param string $root
 * @return string
 */
function get_baseurl ($base, $root) {
    $base   = trim($base, '/');
    $root   = trim($root, '/');
    $lenght = strlen($root);

    return $base === $root ? '' : trim(substr($base, $lenght), '/');
}

/**
 * Show page 404
 */
function show_404 () {
    if (router('supress')) {
        return;
    }
    
    emit('router:not_found');
    
    view('404') xor exit;
}

/**
 * Show an error
 * 
 * @param Exception $exception
 */
function show_error (Exception $exception) {
    $data = array('exception' => $exception);
    
    view('error', $data) xor exit;
}

/**
 * Create a full link to route 
 * 
 * @param string $id
 * @param array $params
 * @param bool $absolute
 * @return string
 */
function url ($id, $params = array(), $absolute = false) {
    $root = router('settings.root');
    $base = router('settings.base_url');
    
    if ($absolute) {
        $basepath = "{$base}{$root}/";
    }
    else {
        $root = $root ? $root : '';
        
        $basepath = chop("/$root", '/');
    }
    
    if ($route = router("routes.$id")) {
        $url = route_replace($route['url'], $params);
        
        return "$basepath/$url";
    }
    
    return false;
}

/**
 * Get URL path from root to file
 * 
 * @param string $path
 */
function path ($path = '') {
    $root = router('settings.root');
    $root = $root ? $root : '';
    
    $basepath = chop("/$root", '/'); 
    
    return "$basepath/$path";
}

/**
 * Redirect to route
 * 
 * @see url
 */
function redirect ($id, $params = array(), $absolute = false) {
    $url = url($id, $params, $absolute);
    
    header("Location: $url") xor exit;
}

/**
 * Redirect to URL relative to the website location
 * 
 * @see path
 * @param string $path
 */
function redirect_url ($path) {
    $url = path($path);
    
    header("Location: $path") xor exit;
}