<?php

/**rick
 * @var Silex\Application $app
 */
$app = require_once __DIR__.'/bootstrap.php';
/*$app->get('/hello/world', function()
{
    return 'Hello World!';
});
$app->get('hello/{name}', function ($name) use($app){
    return 'Hello'.$app->escape($name);
});

$app->get('/albums', function () use($app){
$sql = 'select * from albums';
$albums = $app['db']->fetchAll($sql);
    return $app->json($albums);
});
*/
$app->mount('/v1', include __DIR__.'/albums.php');
$app->mount('/v1', include __DIR__.'/reviews.php');
$app->mount('/v1', include __DIR__.'/restaurants.php');
$app->mount('/v1', include __DIR__.'/users.php');
return $app;