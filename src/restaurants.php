<?php
/**
 * Created by PhpStorm.
 * User: mconi2007
 * Date: 30/01/15
 * Time: 14:38
 */
use Symfony\Component\HttpFoundation\Request;
Use Symfony\Component\HttpFoundation\Response;
Use Symfony\Component\Validator\Constraints as Assert;
/** @var \Silex\ControllerCollection */
$app;

/** @var \Silex\ControllerCollection £api  */
$api = $app['controllers_factory'];
/**
 * Return response in json
 */
$api->before(
    function (Request $request){
        if(0===strpos($request->headers->get('Content-Type'),('application/json'))){
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data)?$data:array());
        }
        elseif(0===strpos($request->headers->get('Content-Type'),('application/xml')))
        {
            $data = xml_parse($request->getContent(), true);
            $request->request->replace(is_array($data)?$data:array());
        }
    }
);
/**
 * @return Assert\Collection
 * Validate records for restaurant table
 */

$app['validate_restaurants'] = function(){
    $validator = new Assert\Collection([
        'name' => [new Assert\NotBlank(), new Assert\Length(['min'=>3])],
        'address' => [new Assert\NotBlank(), /*,new Assert\Type(['type'=>"INTEGER"])*/],
        'postcode' => [new Assert\NotBlank()/*, new Assert\Callback($this, $app['isPostcode']($this))*/],
        'description' => [new Assert\NotBlank(), new Assert\Length(['min'=>5])],
        'type_id' => [new Assert\NotBlank()],
        'cuisine_id' => [new Assert\NotBlank()],
        'town' => [new Assert\NotBlank()]
    ]);

    return $validator;
};
/**retrieve
 * @return mixed
 * To retrieve the current user id
 */
$app['user_current'] = function() use($app){
    $token = $app['security']->getToken();
    if(null !== $token){
        $user = $token->getUser();
    }
    $sql = 'SELECT id FROM users WHERE email = ?';
    $user_id = $app['db']->fetchAssoc($sql,array($user->getUsername()));
    return $user_id['id'];
};
/**
 * @param $postcode
 * @return bool
 * To validate postcode
 */
$app['IsPostcode'] = function ($postcode)
{
    $postcode = strtoupper(str_replace(' ','',$postcode));
    if(preg_match("/^[A-Z]{1,2}[0-9]{2,3}[A-Z]{2}$/",$postcode) || preg_match("/^[A-Z]{1,2}[0-9]{1}[A-Z]{1}[0-9]{1}[A-Z]{2}$/",$postcode) || preg_match("/^GIR0[A-Z]{2}$/",$postcode))
    {
        return true;
    }
    else
    {
        return false;
    }
};
//$app['']('/token', function(Request $request) use ($app)
//{
//    $token = $request->headers->get('token');
//    return $token;
//});
/**
 * Get all restaurants
 */
$api->post('/image', function (Request $request) use ($app) {
    $file_bag = $request->files;
    $location =__DIR__ .'/../public_html/upload';
    $i=0;
    if ($file_bag->count() > 0)
    {
        foreach($file_bag as $file) {
            $file->move($location, '_img'.$i);
            $i++;
        }
    }
    return $file_bag->count();

});
$api->get('restaurants/{id}/image', function($id) use ($app)
{
    $sql = 'select image FROM restaurants WHERE id = ?';
    $image = $app['db']->fetchColumn($sql, [(int)$id], 0);
    if($image == false)
    {
        return new Response('Not found', 404);
    }
    //$path = '_img0';
    $path = $image;
    $location =__DIR__ .'/../public_html/upload/';
    if (!file_exists($location.$path)) {
        return $app->abort(404);
    }
    return $app->sendFile($location.$path,200, array('Content-type' => 'text/jpg'), 'attachment');
    //return "Image".$image;


});
$api->get('/restaurants', function () use($app){
    $sql = 'select restaurants.id, restaurants.name AS restaurant,address, restaurants.description, postcode, town, type.name AS type,
  cuisine.name AS cuisine, image, COUNT(reviews.restaurant_id) as reviewed, AVG(COALESCE(rating, 0)) as average
FROM restaurants
INNER JOIN cuisine ON restaurants.cuisine_id = cuisine.id
INNER JOIN type ON restaurants.type_id = type.id
LEFT JOIN reviews ON restaurants.id = reviews.restaurant_id
GROUP BY restaurants.id
ORDER BY average DESC';
    $restaurants = $app['db']->fetchAll($sql);
    return $app->json($restaurants);

});
$api->get('/bars', function () use($app){
    $sql = 'select restaurants.id, restaurants.name AS restaurant,address, description, postcode, town, type.name AS type,
                cuisine.name AS cuisine
                FROM restaurants, cuisine, type
                WHERE restaurants.cuisine_id = cuisine.id
                AND restaurants.type_id = type.id
                AND LOWER(type.name) = ?';
    $restaurants = $app['db']->fetchAll($sql, ['bar']);
    return $app->json($restaurants);

});

$api->get('/pubs', function () use($app){
    $sql = 'select restaurants.id, restaurants.name AS restaurant,address,description, postcode, town, type.name AS type,
                cuisine.name AS cuisine
                FROM restaurants, cuisine, type
                WHERE restaurants.cuisine_id = cuisine.id
                AND restaurants.type_id = type.id
                AND LOWER(type.name) = ?';
    $restaurants = $app['db']->fetchAll($sql, ['pub']);
    return $app->json($restaurants);

});
/**
 * Get restaurants by id
 */
$api->get('/restaurants/{id}', function ($id) use($app){
    $db = $app['db'];
    $sql = 'select restaurants.id, restaurants.name AS restaurant,address, restaurants.description, postcode, town, type.name AS type,
                       cuisine.name AS cuisine, image, COUNT(reviews.restaurant_id) as reviewed, COALESCE(AVG(rating),0) as average
FROM restaurants
INNER JOIN cuisine ON restaurants.cuisine_id = cuisine.id
INNER JOIN type ON restaurants.type_id = type.id
LEFT JOIN reviews ON reviews.restaurant_id = restaurants.id
WHERE restaurants.id = ?';
    $restaurants = $db->fetchAssoc($sql, [(int)$id]);
    if ($restaurants == false) {
        return $app->abort(404, 'Restaurant Not found');
    }
    elseif($restaurants['id'] == null)
    {
        return $app->abort(404, 'Restaurant Not found');
    }
    return $app->json($restaurants);
});
$api->post('/restaurants', function(Request $request) use($app){
    $file_bag = $request->files;
    $location =__DIR__ .'/../public_html/upload';
    $path = "";
    $filename = "";
    $i=0;
    $user = $app['user'];
    $date= new \DateTime('now');
    $date = $date->format('d/m/Y');
    $token = $request->headers->get('token');
    $sql = 'select * from token WHERE token.token = ?';
    $valid =  $app['db']->fetchAssoc($sql, [$token]);
    if ($valid === false) {
        return new Response("You are not authorised", 401);
    }
    else {
        $data = $request->request->all();
        $reviewValidator = $app['validate_restaurants'];
        $errors = $app['validator']->validateValue($data, $reviewValidator);
        $user_id = $valid['user_id'];
        if (count($errors) > 0) {
            $errorList = [];
            foreach ($errors as $error) {
                $errorList[$error->getPropertyPath()] = $error->getMessage();
            }
            return $app->json($errorList, 400);
        } else {

            if ($file_bag->count() > 0)
            {
                $filename = '_img'.md5(rand(10000,99999));
                foreach($file_bag as $file) {
                    $file->move($location, $filename);
                    $i++;
                }
                $location = $filename;
            }
            $app['db']->insert('restaurants',
                [
                    'description' => $data['description'],
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'cuisine_id' => $data['cuisine_id'],
                    'user_id' => $user_id,
                    'town' => $data['town'],
                    'postcode' => $data['postcode'],
                    'image' => $location,
                    'type_id' => $data['type_id']
                ]
            );
            $id = $app['db']->lastInsertId();
            return new Response(null, 201, ['Location' => '/v1/restaurants/' . $id]);
        }
    }

});
/**
 * Update restaurants
 */
$api->put('/restaurants/{id}', function($id, Request $request) use($app) {
    $user = $app['user_current'];
    $file_bag = $request->files;
    $location =__DIR__ .'/../public_html/upload';
    $i=0;
    $token = $request->headers->get('token');
    $sql = 'select * from token WHERE token.token = ?';
    $valid =  $app['db']->fetchAssoc($sql, [$token]);
    if ($valid === false) {
        return new Response("You are not authorised", 401);
    }
    else {
        $isCreated = $app['db']->fetchAssoc('select * from restaurants WHERE id = ? AND user_id = ?', [(int)$id, (int)$user]);
        if ($isCreated === false) {
            return new Response('You did not create this', 401);
        } elseif ($app['security']->isGranted('ROLE_ADMIN')) {
            $user_id = $valid['user_id'];
            $restaurant = $app['db']->fetchAssoc('select * from restaurants WHERE id = ?', [(int)$id]);
            $data = $request->request->all();
            if ($restaurant === false) {
                return new Response('Restaurant not found', 404);
            }
            $restaurantValidator = $app['validate_restaurants'];
            $errors = $app['validator']->validateValue($data, $restaurantValidator);
            if (count($errors) > 0) {
                $errorList = [];
                foreach ($errors as $error) {
                    $errorList[$error->getPropertyPath()] = $error->getMessage();
                }
                return $app->json($errorList, 400);
            } else {
                if ($file_bag->count() > 0)
                {
                    $filename = '_img'.md5(rand(10000,99999));
                    foreach($file_bag as $file) {
                        $file->move($location, $filename);
                        $i++;
                    }
                    $location = $filename;
                }
                $app['db']->update('restaurants',
                    [
                        'description' => $data['description'],
                        'name' => $data['name'],
                        'address' => $data['address'],
                        'cuisine_id' => $data['cuisine_id'],
                        'user_id' => $user_id,
                        'town' => $data['town'],
                        'postcode' => $data['postcode'],
                        'image' => $location,
                        'type_id' => $data['type_id']
                    ],
                    ['id' => (int)$id]
                );
                return new Response(null, 204);
            }
        } else {
            return new Response('Admin task only', 401);
        }
    }

});
/**
 * Delete restaurants by id. Return to this
 */
$api->delete('restaurants/{id}', function($id) use($app) {
    //$user = $app['user_current'];
    if (!$app['security']->isGranted('ROLE_ADMIN'))
    {
        return new Response('You are not authorised', 401);
    }
    else {
        $sql = 'SELECT * FROM restaurants WHERE id = ?';
        $result = $app['db']->fetchAssoc($sql, array((int)$id));

        if ($result === false) {
            return new Response('Restaurant not Found', 404);
        } else {
            $app['db']->delete('restaurants', array('id' => $id));
            $app['db']->delete('reviews', array('resturant_id' => $id));
        }

        return new Response(null, 204);
    }
});
$api->get('restaurants/{id}/delete', function($id) use($app) {
    //$user = $app['user_current'];
    if (!$app['security']->isGranted('ROLE_ADMIN'))
    {
        return new Response('You are not authorised', 401);
    }
    else {
        $sql = 'SELECT * FROM restaurants WHERE id = ?';
        $result = $app['db']->fetchAssoc($sql, array((int)$id));

        if ($result === false) {
            return new Response('Restaurant not Found', 404);
        } else {
            $app['db']->delete('restaurants', array('id' => $id));
        }

        return new Response(null, 204);
    }
});


return $api;