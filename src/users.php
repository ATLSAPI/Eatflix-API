<?php
/**
 * Created by PhpStorm.
 * User: mconi2007
 * Date: 03/02/15
 * Time: 22:24
 */
use Symfony\Component\HttpFoundation\Request;
Use Symfony\Component\HttpFoundation\Response;
Use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
/** @var \Silex\ControllerCollection */
$app;
$api = $app['controllers_factory'];

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
$app['validate_register'] = function(){
    $validator = new Assert\Collection([
        'email' => [new Assert\NotBlank(), new Assert\Email()],
        'password' => [new Assert\NotBlank()],
        'first_name' => [new Assert\NotBlank()],
        'last_name' => [new Assert\NotBlank()]
    ]);

    return $validator;
};
$app['validate_users'] = function(){
    $validator = new Assert\Collection([
        'device_name' => [new Assert\NotBlank()]
    ]);

    return $validator;
};
$app['token'] = function(){
    $i=0;
    $prehash = rand(5677, 7487);
    $token = crypt($prehash);
    while($i<10)
    {
        $token = crypt($token);
        $i++;
    }
    return $token;
};
$api->get('/tokened', function(Request $request) use ($app)
{
    $token = $request->headers->get('token');
    $sql = 'select * from token WHERE token.token = ?';
    $valid =  $app['db']->fetchAssoc($sql, [$token]);
    if ($valid !== false) {
        //Authentication successful
        return $valid['user_id'];
    }
    else {
        return new Response("You are not authorised", 401);
    }
});
$api->post('/feedback', function () use ($app) {
    $body = 'Hello';
    $messagebody = $body;
    $name = 'Melvin';
    $subject = "Message from ".$name;
    $app['mailer']->send(\Swift_Message::newInstance()
        ->setSubject($subject)
        ->setFrom(array('mconi2007@gmail.com')) // replace with your own
        ->setTo(array('mconi2007@yahoo.com')) // replace with email recipient
        ->setBody($messagebody));
    return new Response('Thank you for your feedback!', 201);
});
$api->post('/users',function(Request $request) use ($app) {
    /** @var \SimpleUser\UserManager £userManager */
    $i =0;
    $userValidator = $app['validate_register'];
    $file_bag = $request->files;
    $location =__DIR__ .'/../public_html/upload';
    $data = $request->request->all();
    $errors = $app['validator']->validateValue($data, $userValidator);
    if (count($errors) > 0) {
        $errorList = [];
        foreach ($errors as $error) {
            $errorList[$error->getPropertyPath()] = $error->getMessage();
        }
        return $app->json($errorList, 400);
    }
    else {
        $sql = 'SELECT * FROM users WHERE email = ?';
        $exists = $app['db']->fetchAssoc($sql, [$app->escape($data['email'])]);
        if ($exists)
        {
            return new Response('Email already exists', 418);
        }
        $userManager = $app['user.manager'];
        $user = $userManager->createUser($request->request->get('email'),
            $request->request->get('password'));
        $userManager->insert($user);
        $id = $app['db']->lastInsertId();
        if ($file_bag->count() > 0)
        {
            $filename = '_img'.md5(rand(10000,99999));
            foreach($file_bag as $file) {
                $file->move($location, $filename);
                $i++;
            }
            $location = $filename;
        }
        $app['db']->insert('user_info',
            [
                'user_id' => $id,
                'first_name' => $app->escape($data['first_name']),
                'last_name' => $app->escape($data['last_name']),
                'image' => $location
            ]);
    }

    return new Response(null, 201);
});
$api->post('/users/login', function(Request $request) use ($app)
{
    $auth = $request->headers->get('Authorization');
    if (!$auth)
    {
        return new Response("You are not authorised", 401);
    }
    $data = $request->request->all();
    $id = $app['user_current'];
    $user = $app['user'];
    if($user === null) {
        return new Response("You are not authorised", 401);
    }
    else {
        $userValidator = $app['validate_users'];
        $errors = $app['validator']->validateValue($data, $userValidator);
        if (count($errors) > 0) {
            $errorList = [];
            foreach ($errors as $error) {
                $errorList[$error->getPropertyPath()] = $error->getMessage();
            }
            return $app->json($errorList, 400);
        } else {
            $date= new \DateTime('now');
            $date = $date->format('d/m/Y');
            $device_id = md5(rand(50000, 99999));
            $token = $app['token'];
            $json = array('id' => $id, 'token' => $token, 'device_id' => $device_id);
            $app['db']->insert('token',
                [
                    'user_id' => $id,
                    'token' => $token,
                    'device_id' => $device_id,
                    'device_name' => $data['device_name'],
                    'date' => $date
                ]);
            return $app->json($json);
        }
    }
});
$api->get('/users/{id}/validate/{token}', function($id, $token) use ($app) {
    $sql = 'select * from users WHERE id = ?';
    $user = $app['db']->fetchAssoc($sql, [(int)$id]);
    if ($user === false) {
        return $app->abort(401, "Not authorised");
    }
    else
    {
        $sql = 'select * from token WHERE user_id = ? AND token = ?';
        $valid = $app['db']->fetchAssoc($sql, [(int)$id, $token]);
        if ($valid !== false) {
            return new Response("Valid", 200);
        }
        else {
            return new Response("Not authorised", 401);
        }
    }
});
$api->post('/users/logout/{id}', function(Request $request, $id) use ($app) {
    $token = $request->headers->get('token');
    $sql = 'select * from token WHERE token.token = ?';
    $valid =  $app['db']->fetchAssoc($sql, [$token]);
    if ($valid === false) {
        return new Response("You are not authorised", 401);
    }
    else {
        $sql = 'select * from token WHERE token.device_id = ?';
        $device_id =  $app['db']->fetchAssoc($sql, [$id]);
        if ($device_id === false) {
            return new Response("Device not found or already removed", 404);
        }
        else {
            $app['db']->delete('token', array('device_id' => $id));
        }
    }
    return new Response('Device removed', 204);

});

//$app->error(function (\Exception $e, $code) use ($app) {
//    if ($app['debug']) {
//        return;
//    }
//    switch ($code) {
//        case 404:
//            $message = 'The requested page could not be found.';
//            break;
//        default:
//            $message = 'We are sorry, but something went terribly wrong.';
//    }
//    return new Response($message, $code);
//});
return $api;