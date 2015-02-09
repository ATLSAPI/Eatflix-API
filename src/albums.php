<?php
/**
 * Created by PhpStorm.
 * User: sjp284
 * Date: 26/11/2014
 * Time: 15:54
 */
Use Symfony\Component\HttpFoundation\Request;
Use Symfony\Component\HttpFoundation\Response;
Use Symfony\Component\Validator\Constraints as Assert;
/** @var \Silex\ControllerCollection */
$app;

/** @var \Silex\ControllerCollection £api  */
$api = $app['controllers_factory'];
$api->get('/albums', function () use($app){
    $sql = 'select * from albums';
    $albums = $app['db']->fetchAll($sql);
    return $app->json($albums);
});

$api->get('/albums{id}', function ($id) use($app){
    $db = $app['db'];
    $sql = 'select * from albums where $id = ?';
    $result = $db->fetchAssoc($sql, [(int)$id]);
    if($result == false)
    {
        return $app->abort(404, 'Album Not found');
    }
    //$albums = $app['db']->fetchAll($sql);
    return $app->json($result);
});

$api->post('/albums', function(Request $request)
{

});
$app['album_validator'] = function()
{
    $validator = new Assert\Collection([
        'artist' => [new Assert\NotBlank(), new Assert\Length(['max' => 225])],
        'title' => [new Assert\NotBlank(), new Assert\Length(['max' => 225])]]);
    return $validator;
};
/*$api -> before(function(Requset $request)
{

});*/

$api->put('/albums/{id}', function(Request $request, $id) use($app)
{
$album = $app['db']->fetchAssoc('select * from albums where id = ?', [(int)$id]);
    $data = $request->request->all();
    if($album == false)
    {
        return $app->abort(404, 'not found');
    }
    $alumValidator = $app['album_validator'];
    $error = $app['validator']->validateValue($data, $alumValidator);
    if(count($error) > 0)
    {}
    else{
        $app['db']->update('albums',['artist' => $data['artist'], 'title' => $date['title']], ['id' => (int)$id]);
    return new Response(null, 204);
    }
});

return $api;