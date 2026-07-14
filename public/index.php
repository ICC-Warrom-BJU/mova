<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../src/Helpers/functions.php';

SessionMiddleware::start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$moduleDirs = [
    'CompanyPanel' => __DIR__ . '/../src/Modules/CompanyPanel/',
    'VehicleRequest' => __DIR__ . '/../src/Modules/VehicleRequest/',
    'TripLog' => __DIR__ . '/../src/Modules/TripLog/',
    'DriverSelfService' => __DIR__ . '/../src/Modules/DriverSelfService/',
    'FuelExpense' => __DIR__ . '/../src/Modules/FuelExpense/',
    'Maintenance' => __DIR__ . '/../src/Modules/Maintenance/',
    'Notifications' => __DIR__ . '/../src/Modules/Notifications/',
    'CustomerPanel' => __DIR__ . '/../src/Modules/CustomerPanel/',
];

$exactRoutes = [
    'GET' => [
        '/'                   => 'DashboardController@home',
        '/login'              => 'AuthController@loginForm',
        '/logout'             => 'AuthController@logout',
        '/dashboard'          => 'DashboardController@index',

        '/regions'            => 'RegionController@index',
        '/regions/create'     => 'RegionController@create',
        '/branches'           => 'BranchController@index',
        '/branches/create'    => 'BranchController@create',
        '/customers'          => 'CustomerController@index',
        '/customers/create'   => 'CustomerController@create',
        '/vehicles'           => 'VehicleController@index',
        '/vehicles/create'    => 'VehicleController@create',
        '/vehicles/import'    => 'VehicleController@import',
        '/vehicles/import/template' => 'VehicleController@downloadTemplate',
        '/users'              => 'UserController@index',
        '/users/create'       => 'UserController@create',
        '/config'             => 'ConfigController@index',
        '/permissions'        => 'PermissionController@index',

        '/customer/dashboard'  => 'CustomerDashboardController@index',
        '/customer/requests'   => 'VehicleRequestController@index',
        '/customer/requests/create' => 'VehicleRequestController@create',
        '/customer/trips'      => 'TripController@index',
        '/customer/trips/create' => 'TripController@create',
        '/customer/issues'     => 'IssueController@index',
        '/customer/issues/create' => 'IssueController@create',
        '/customer/fuel'       => 'FuelController@index',
        '/customer/fuel/create' => 'FuelController@create',
        '/customer/expenses'   => 'ExpenseController@index',
        '/customer/expenses/create' => 'ExpenseController@create',
        '/customer/maintenance' => 'MaintenanceController@index',
        '/customer/maintenance/create' => 'MaintenanceController@create',
        '/customer/vehicles'   => 'CustomerVehicleController@index',
        '/notifications'       => 'NotificationController@index',
    ],
    'POST' => [
        '/login'              => 'AuthController@login',
        '/regions/create'     => 'RegionController@create',
        '/branches/create'    => 'BranchController@create',
        '/customers/create'   => 'CustomerController@create',
        '/vehicles/create'    => 'VehicleController@create',
        '/vehicles/import'    => 'VehicleController@import',
        '/users/create'       => 'UserController@create',
        '/config'             => 'ConfigController@store',
        '/permissions'        => 'PermissionController@update',

        '/customer/requests/create' => 'VehicleRequestController@create',
        '/customer/trips/create'    => 'TripController@create',
        '/customer/issues/create'   => 'IssueController@create',
        '/customer/fuel/create'     => 'FuelController@create',
        '/customer/expenses/create' => 'ExpenseController@create',
        '/customer/maintenance/create' => 'MaintenanceController@create',
    ],
];

$action = $exactRoutes[$method][$uri] ?? null;

if ($action === null) {
    $paramRoutes = [
        'GET' => [
            '#^/regions/(\d+)/edit$#'              => ['RegionController@edit', 'CompanyPanel'],
            '#^/branches/(\d+)/edit$#'             => ['BranchController@edit', 'CompanyPanel'],
            '#^/customers/(\d+)/edit$#'            => ['CustomerController@edit', 'CompanyPanel'],
            '#^/vehicles/(\d+)/edit$#'             => ['VehicleController@edit', 'CompanyPanel'],
            '#^/users/(\d+)/edit$#'                => ['UserController@edit', 'CompanyPanel'],

            '#^/customer/trips/(\d+)/start$#'      => ['TripController@start', 'TripLog'],
            '#^/customer/trips/(\d+)/complete$#'   => ['TripController@complete', 'TripLog'],
            '#^/customer/trips/(\d+)$#'             => ['TripController@detail', 'TripLog'],
            '#^/customer/trips/(\d+)/edit$#'        => ['TripController@edit', 'TripLog'],
            '#^/customer/maintenance/(\d+)/log$#'  => ['MaintenanceController@logService', 'Maintenance'],
            '#^/customer/checklists/(\d+)$#'       => ['ChecklistController@show', 'DriverSelfService'],
            '#^/customer/checklists/(\d+)/create/(pre_trip|post_trip)$#' => ['ChecklistController@createChecklist', 'DriverSelfService'],
            '#^/customer/checklists/(\d+)/photos$#' => ['ChecklistController@photos', 'DriverSelfService'],
            '#^/customer/requests/(\d+)/assign$#'  => ['VehicleRequestController@assign', 'VehicleRequest'],
            '#^/customer/vehicles/(\d+)$#'          => ['CustomerVehicleController@detail', 'CustomerPanel'],
            '#^/customer/module/([a-z_]+)$#'        => ['UnderDevelopmentController@show', 'CustomerPanel'],
            '#^/customer/fuel/(\d+)/edit$#'         => ['FuelController@edit', 'FuelExpense'],
            '#^/customer/expenses/(\d+)/edit$#'     => ['ExpenseController@edit', 'FuelExpense'],
            '#^/customer/issues/(\d+)/edit$#'       => ['IssueController@edit', 'DriverSelfService'],
            '#^/customer/maintenance/(\d+)/edit$#'  => ['MaintenanceController@edit', 'Maintenance'],
        ],
        'POST' => [
            '#^/regions/(\d+)/edit$#'              => ['RegionController@edit', 'CompanyPanel'],
            '#^/regions/(\d+)/delete$#'            => ['RegionController@delete', 'CompanyPanel'],
            '#^/branches/(\d+)/edit$#'             => ['BranchController@edit', 'CompanyPanel'],
            '#^/branches/(\d+)/delete$#'           => ['BranchController@delete', 'CompanyPanel'],
            '#^/customers/(\d+)/edit$#'            => ['CustomerController@edit', 'CompanyPanel'],
            '#^/customers/(\d+)/delete$#'          => ['CustomerController@delete', 'CompanyPanel'],
            '#^/vehicles/(\d+)/edit$#'             => ['VehicleController@edit', 'CompanyPanel'],
            '#^/vehicles/(\d+)/delete$#'           => ['VehicleController@delete', 'CompanyPanel'],
            '#^/users/(\d+)/edit$#'                => ['UserController@edit', 'CompanyPanel'],
            '#^/users/(\d+)/delete$#'              => ['UserController@delete', 'CompanyPanel'],
            '#^/config/(\d+)/toggle$#'            => ['ConfigController@toggle', 'CompanyPanel'],

            '#^/customer/requests/(\d+)/approve$#' => ['VehicleRequestController@approve', 'VehicleRequest'],
            '#^/customer/requests/(\d+)/reject$#'  => ['VehicleRequestController@reject', 'VehicleRequest'],
            '#^/customer/issues/(\d+)/resolve$#'   => ['IssueController@resolve', 'DriverSelfService'],
            '#^/customer/trips/(\d+)/start$#'      => ['TripController@start', 'TripLog'],
            '#^/customer/trips/(\d+)/complete$#'   => ['TripController@complete', 'TripLog'],
            '#^/customer/maintenance/(\d+)/log$#'  => ['MaintenanceController@logService', 'Maintenance'],
            '#^/customer/fuel/(\d+)/approve$#'     => ['FuelController@approve', 'FuelExpense'],
            '#^/customer/fuel/(\d+)/reject$#'      => ['FuelController@reject', 'FuelExpense'],
            '#^/customer/expenses/(\d+)/approve$#' => ['ExpenseController@approve', 'FuelExpense'],
            '#^/customer/expenses/(\d+)/reject$#'  => ['ExpenseController@reject', 'FuelExpense'],
            '#^/customer/requests/(\d+)/assign$#'  => ['VehicleRequestController@assign', 'VehicleRequest'],
            '#^/customer/checklists/(\d+)/create/(pre_trip|post_trip)$#' => ['ChecklistController@createChecklist', 'DriverSelfService'],
            '#^/customer/checklists/(\d+)/photos$#' => ['ChecklistController@photos', 'DriverSelfService'],
            '#^/notifications/mark-read$#'           => ['NotificationController@markRead', 'Notifications'],
            '#^/customer/trips/(\d+)/edit$#'        => ['TripController@edit', 'TripLog'],
            '#^/customer/fuel/(\d+)/edit$#'         => ['FuelController@edit', 'FuelExpense'],
            '#^/customer/expenses/(\d+)/edit$#'     => ['ExpenseController@edit', 'FuelExpense'],
            '#^/customer/issues/(\d+)/edit$#'       => ['IssueController@edit', 'DriverSelfService'],
            '#^/customer/maintenance/(\d+)/edit$#'  => ['MaintenanceController@edit', 'Maintenance'],
        ],
    ];

    foreach ($paramRoutes[$method] ?? [] as $pattern => $handlerInfo) {
        if (preg_match($pattern, $uri, $matches)) {
            [$action, $moduleDirKey] = $handlerInfo;
            $routeParams = array_slice($matches, 1);
            $routeParams = array_map(fn($v) => is_numeric($v) ? (int)$v : $v, $routeParams);
            $overrideModuleDir = $moduleDirs[$moduleDirKey];
            break;
        }
    }
}

if ($action === null) {
    if (preg_match('#^/(css|js|img)/(.+)$#', $uri, $m)) {
        $file = __DIR__ . '/assets/' . $m[1] . '/' . $m[2];
        if (file_exists($file)) {
            $mime = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'img' => 'image/' . (pathinfo($file, PATHINFO_EXTENSION) === 'png' ? 'png' : 'jpeg'),
            ];
            header('Content-Type: ' . ($mime[$m[1]] ?? 'application/octet-stream'));
            readfile($file);
            exit;
        }
    }

    http_response_code(404);
    echo '404 Not Found';
    exit;
}

if (is_string($action)) {
    [$controller, $methodName] = explode('@', $action);

    $searched = [];
    foreach ($moduleDirs as $label => $dir) {
        if (isset($overrideModuleDir) && $dir !== $overrideModuleDir) {
            continue;
        }
        $controllerFile = $dir . $controller . '.php';
        $searched[] = $controllerFile;
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            if (class_exists($controller)) {
                $instance = new $controller();
                $routeParams ??= [];
                $instance->$methodName(...$routeParams);
                exit;
            }
        }
    }

    http_response_code(500);
    echo 'Controller not found. Searched: ' . implode(', ', $searched);
    exit;
}
