<?php

spl_autoload_register(function (string $class) {
    $paths = [
        __DIR__ . '/Core/',
        __DIR__ . '/Middleware/',
        __DIR__ . '/Helpers/',
        __DIR__ . '/Modules/VehicleRequest/',
        __DIR__ . '/Modules/TripLog/',
        __DIR__ . '/Modules/DriverSelfService/',
        __DIR__ . '/Modules/Maintenance/',
        __DIR__ . '/Modules/FuelExpense/',
        __DIR__ . '/Modules/CustomerPanel/',
        __DIR__ . '/Modules/Analytics/',
        __DIR__ . '/Modules/Notifications/',
        __DIR__ . '/Modules/CompanyPanel/',
        __DIR__ . '/Modules/CompanyPanel/Repositories/',
        __DIR__ . '/../config/',
    ];

    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
