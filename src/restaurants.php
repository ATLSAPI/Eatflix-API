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
//        elseif(0===strpos($request->headers->get('Content-Type'),('application/xml')))
//        {
//            $data = xml_parse($request->getContent(), true);
//            $request->request->replace(is_array($data)?$data:array());
//        }
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
        'town' => [new Assert\NotBlank()],
        'image' => [new Assert\NotBlank()]
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
/**
 * Get all restaurants
 */
$api->get('/restaurants', function () use($app){
    $sql = 'select restaurants.name AS restaurant,address, postcode, town, type.name AS type,
                cuisine.name AS cuisine
                FROM restaurants, cuisine, type
                WHERE restaurants.cuisine_id = cuisine.id
                AND restaurants.type_id = type.id';
    $restaurants = $app['db']->fetchAll($sql);
    return $app->json($restaurants);

});
$api->get('/bars', function () use($app){
    $sql = 'select restaurants.name AS restaurant,address, postcode, town, type.name AS type,
                cuisine.name AS cuisine
                FROM restaurants, cuisine, type
                WHERE restaurants.cuisine_id = cuisine.id
                AND restaurants.type_id = type.id
                AND LOWER(type.name) = ?';
    $restaurants = $app['db']->fetchAll($sql, ['bar']);
    return $app->json($restaurants);

});

$api->get('/pubs', function () use($app){
    $sql = 'select restaurants.name AS restaurant,address, postcode, town, type.name AS type,
                cuisine.name AS cuisine
                FROM restaurants, cuisine, type
                WHERE restaurants.cuisine_id = cuisine.id
                AND restaurants.type_id = type.id
                AND LOWER(type.name) = ?';
    $restaurants = $app['db']->fetchAll($sql, ['pub']);
    return $app->json($restaurants);

});
$api->get('/create', function () use($app)
{
    function nextId($id)
    {
        $id += $id;
    }
    //sqlite_create_function($app['db'], 'rev', 'nextId', 1);
    $app['db']->sqliteCreateFunction('rev', 'nextId', 1);
    return $app->json('Done');

});
/**
 * Get restaurants by id
 */
$api->get('/restaurants/{id}', function ($id) use($app){
    $db = $app['db'];
    $sql = 'select restaurants.name AS restaurant,address, postcode, town, type.name AS type,
                cuisine.name AS cuisine
                FROM restaurants, cuisine, type
                WHERE restaurants.cuisine_id = cuisine.id
                AND restaurants.type_id = type.id AND restaurants.id = ?';
    $restaurants = $db->fetchAssoc($sql, [(int)$id]);
    if ($restaurants == false) {
        return $app->abort(404, 'Restaurant Not found');
    }
    return $app->json($restaurants);
});
$api->post('/restaurants', function(Request $request) use($app){

    $user = $app['user'];
    $date= new \DateTime('now');
    $date = $date->format('d/m/Y');
    if($user === null) {
        return new Response("You are not authorised", 401);
    }
    else {
        $data = $request->request->all();
        $reviewValidator = $app['validate_restaurants'];
        $errors = $app['validator']->validateValue($data, $reviewValidator);
        $user_id = $app['user_current'];
        if (count($errors) > 0) {
            $errorList = [];
            foreach ($errors as $error) {
                $errorList[$error->getPropertyPath()] = $error->getMessage();
            }
            return $app->json($errorList, 400);
        } else {
            $app['db']->insert('restaurants',
                [
                    'description' => $data['description'],
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'cuisine_id' => $data['cuisine_id'],
                    'user_id' => $user_id,
                    'town' => $data['town'],
                    'postcode' => $data['postcode'],
                    'image' => $data['image'],
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
    $isCreated = $app['db']->fetchAssoc('select * from restaurants WHERE id = ? AND user_id = ?', [(int)$id,(int)$user]);
    if ($isCreated === false)
    {
        return new Response('You did not create this', 401);
    }
    elseif($app['security']->isGranted('ROLE_ADMIN')) {
        $user_id = $app['user_current'];
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
            $app['db']->update('restaurants',
                [
                    'description' => $data['description'],
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'cuisine_id' => $data['cuisine_id'],
                    'user_id' => $user_id,
                    'town' => $data['town'],
                    'postcode' => $data['postcode'],
                    'image' => $data['image'],
                    'type_id' => $data['type_id']
                ],
                ['id' => (int)$id]
            );
            return new Response(null, 204);
        }
    }
    else{
        return new Response('Admin task only', 401);
    }

});
/**
 * Delete restaurants by id. Return to this
 */
$api->delete('restaurants/{id}', function($id) use($app) {
    $user = $app['user_current'];
    if ($user === $id)
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