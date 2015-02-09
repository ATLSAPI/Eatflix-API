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
$api->before(
    function (Request $request){
        if(0===strpos($request->headers->get('Content-Type'),('application/json'))){
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data)?$data:array());
        }
    }
);

$app['validate_reviews'] = function(){
    $validator = new Assert\Collection([
        'description' => [new Assert\NotBlank(), new Assert\Length(['min'=>3])],
        'rating' => [new Assert\NotBlank(), new Assert\Length(['max'=>2]),/*new Assert\Type(['type'=>"INTEGER"])*/],
        'restaurant_id' => [new Assert\NotBlank(),/*new Assert\Type(['type'=>"INTEGER"])*/],
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
 * Get all reviews
 */
$api->get('/reviews', function () use($app){
    $user = $app['user'];
    if($user === null) {
        return new Response("You are not authorised", 401);
    }
    else {
        $sql = 'select reviews.id, reviews.description, reviews.user_id, restaurants.user_id, restaurants.name as restaurant, type.name AS type,
                    rating, created, modified, cuisine.name AS cuisine
                    FROM reviews, restaurants,cuisine, type
                    WHERE restaurant_id = restaurants.id AND restaurants.cuisine_id = cuisine.id
                    AND restaurants.type_id = type.id ORDER BY date(created) DESC';
        $reviews = $app['db']->fetchAll($sql);
        return $app->json($reviews);
    }
});
/**
 * Get reviews by id
 */
$api->get('/reviews/{id}', function ($id) use($app){
    $user = $app['user'];
    if($user === null) {
        return new Response("You are not authorised", 401);
    }
    else {
        $db = $app['db'];
        $sql = 'select reviews.id, reviews.description, reviews.user_id, restaurants.name as restaurant, type.name AS type,
                    rating, created, modified, cuisine.name AS cuisine
                    FROM reviews, restaurants,cuisine, type
                    WHERE restaurant_id = restaurants.id AND restaurants.cuisine_id = cuisine.id
                    AND restaurants.type_id = type.id AND reviews.id = ?';
        $reviews = $db->fetchAssoc($sql, [(int)$id]);
        if ($reviews == false) {
            return $app->abort(404, 'Review not found');
        }
        return $app->json($reviews);
    }

});
/**
 * Post restaurant
 */
$api->post('/reviews', function(Request $request) use($app){

    $user = $app['user'];
    $date= new \DateTime('now');
    $date = $date->format('d/m/Y');
    if($user === null) {
        return new Response("You are not authorised", 401);
    }
    else {
        $data = $request->request->all();
        $restaurant_id = $data['restaurant_id'];
        $sql = 'select * from restaurants WHERE id = ?';
        $restaurant = $app['db']->fetchAssoc($sql, [(int)$restaurant_id]);
        if ($restaurant == false) {
            return $app->abort(400, "Bad Request");
        }

        $reviewValidator = $app['validate_reviews'];
        $errors = $app['validator']->validateValue($data, $reviewValidator);
        $user_id = $app['user_current'];
        if (count($errors) > 0) {
            $errorList = [];
            foreach ($errors as $error) {
                $errorList[$error->getPropertyPath()] = $error->getMessage();
            }
            return $app->json($errorList, 400);
        } else {
            $app['db']->insert('reviews',
                [
                    'description' => $data['description'],
                    'created' => $date,
                    'rating' => $data['rating'],
                    'restaurant_id' => $data['restaurant_id'],
                    'user_id' => $user_id,
                    'modified' => $date
                ]
            );
            $id = $app['db']->lastInsertId();
            return new Response(null, 201, ['Location' => '/api/reviews/' . $id]);
        }
    }

});


/**
 * Update reviews by id if current user created it
 */
$api->put('/reviews/{id}', function($id, Request $request) use($app) {
    $date= new \DateTime('now');
    $date = $date->format('d/m/Y');
    $user = $app['user_current'];
    $reviews = $app['db']->fetchAssoc('select * from reviews WHERE id = ?' ,[(int)$id]);
    $data = $request->request->all();
    if($reviews == false){
        return $app->abort(404,'Review not found');
    }
    if ($user === $id)
    {
        return $app->abort(401, 'You are not authorised');
    }
    else {
        $reviewValidator = $app['validate_reviews'];
        $errors = $app['validator']->validateValue($data, $reviewValidator);
        if (count($errors) > 0) {
            $errorList = [];
            foreach ($errors as $error) {
                $errorList[$error->getPropertyPath()] = $error->getMessage();
            }
            return $app->json($errorList, 400);
        } else {
            $app['db']->update('reviews',
                [
                    'description' => $data['description'],
                    'modified' => $date,
                    'rating' => $data['rating'],
                ],
                ['id' => (int)$id]
            );
            return new Response(null, 204);
        }
    }

});
/**
 * Delete reviews by id if current user created it.Else return HTTP 401 unauthorised
 */
$api->delete('reviews/{id}', function($id) use($app) {
    $user = $app['user_current'];
    if ($user === $id)
    {
        return $app->abort(401, 'You are not authorised');
    }
    else {
        $sql = 'SELECT user_id, id FROM reviews WHERE id = ?';
        $result = $app['db']->fetchAssoc($sql, array((int)$id));

        if ($result === false) {
            $app->abort(401, 'Review not Found');
        } else {
            $app['db']->delete('reviews', array('id' => $id));
        }

        return new Response(null, 204);
    }
});

//$api->get('/reviews/{name}', function(Request $request) use ($app)
//{
//    $user = $app['user'];
//    if($user === null) {
//        return new Response("You are not authorised", 401);
//    }
//    else {
//        $name = $request->attributes->get('name');
//        return new Response('Hello '.$app->escape($name));
//    }
//});

return $api;