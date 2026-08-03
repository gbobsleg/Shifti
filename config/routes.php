<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);
    $routes->setExtensions(['json']);

    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Grids', 'action' => 'index']);
        $builder->connect('/pages/*', ['controller' => 'Pages', 'action' => 'display']);
        
        $builder->scope('/excel-uploads', function (RouteBuilder $builder) {
            $builder->connect('/upload', ['controller' => 'ExcelUploads', 'action' => 'upload']);
            $builder->connect('/preview', ['controller' => 'ExcelUploads', 'action' => 'preview']);
            $builder->connect('/process', ['controller' => 'ExcelUploads', 'action' => 'process']);
        });
        
        $builder->scope('/rotation-rules', function (RouteBuilder $builder) {
            $builder->connect('/', ['controller' => 'RotationRules', 'action' => 'index']);
            $builder->connect('/{action}', ['controller' => 'RotationRules']);
            $builder->connect('/{action}/{id}', ['controller' => 'RotationRules'], ['id' => '[a-f0-9\-]+', 'pass' => ['id']]);
        });
        
        $builder->fallbacks();
    });
};
