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
        'address' => [new Assert\NotBlank(), new Assert\Length(['max'=>2]),new Assert\Type(['type'=>"INTEGER"])],
//        'postcode' => [new Assert\NotBlank(), new Assert\Callback($this, $app['isPostcode']($this))],
        'description' => [new Assert\NotBlank(), new Assert\Length(['min'=>5])],
        'type_id' => [new Assert\NotBlank()],
        'user_id' => [new Assert\NotBlank(), ],
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
/**
 * Get all restaurantss
 */
$api->get('/restaurants', function () use($app){
    $user = $app['user'];
    if($user === null) {
        return new Response("You are not authorised", 401);
    }
    else {
        $sql = 'select restaurants.name AS restaurant,address, postcode, town, type.name AS type,
                    cuisine.name AS cuisine
                    FROM restaurants, cuisine, type
                    WHERE restaurants.cuisine_id = cuisine.id
                    AND restaurants.type_id = type.id';
        $restaurants = $app['db']->fetchAll($sql);
        return $app->json($restaurants);
    }
});
/**
 * Get restaurants by id
 */
$api->get('/restaurants/{id}', function ($id) use($app){
    $user = $app['user'];
    if($user === null) {
        return new Response("You are not authorised", 401);
    }
    else {
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
    }

});
/**
 * Update restaurants
 */
$api->put('/restaurants/{id}', function($id, Request $request) use($app) {
    $user = $app['user_current'];
    if ($user === $id)
    {
        $app->abort(401, 'You are not authorised');
    }
    $restaurants = $app['db']->fetchAssoc('select * from restaurants WHERE id = ?' ,[(int)$id]);
    $data = $request->request->all();
    if($restaurants == false){
        return $app->abort(404,'Restaurant not found');
    }
    $restaurantValidator = $app['validate_restaurants'];
    $errors = $app['validator']->validateValue($data,$restaurantValidator);
    if(count($errors)>0) {
        $errorList = [];
        foreach($errors as $error){
            $errorList[$error->getPropertyPath()] = $error->getMessage();
        }
        return $app->json($errorList, 400);
    }
    else{
        $app['db']->update('restaurants',
            [
                'description'    => $data['description'],
                'address'    => $data['address'],
                'town'    => $data['town'],
                'cuisine' => $data['cuisine_id'],
                'type' => $data['type_id']
            ],
            ['id'=>(int)$id]
        );
        return new Response(null,204);
    }

});
/**
 * Delete restaurants by id. Return to this
 */
$api->delete('restaurants/{id}', function($id) use($app) {
    $user = $app['user_current'];
    if ($user === $id)
    {
        return $app->abort(401, 'You are not authorised');
    }
    else {
        $sql = 'SELECT * FROM restaurants WHERE id = ?';
        $result = $app['db']->fetchAssoc($sql, array((int)$id));

        if ($result === false) {
            return $app->abort(418, "Album not Found");
        } else {
            $app['db']->delete('restaurants', array('id' => $id));
        }

        return new Response(null, 204);
    }
});


return $api;