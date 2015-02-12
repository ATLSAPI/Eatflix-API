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
//$app['mailer'] = $app->share(function ($app) {
//    return new \Swift_Mailer($app['swiftmailer.transport']);
//});
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
$api->post('/feedback', function () use ($app) {
    //$request = $app['request'];
  $body = 'Hello';
//    $message = \Swift_Message::newInstance()
//        ->setSubject('Feedback')
//        ->setFrom(array('mconi2007@gmail.com'))
//        ->setTo(array('mconi2007@yahoo.com'))
//        ->setBody($body);
//
//    $app['mailer']->send($message);
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
    $userManager = $app['user.manager'];
    $user = $userManager->createUser($request->request->get('email'),
        $request->request->get('password'));
    $userManager->insert($user);

    return new Response(null, 201);
});
$api->post('users/login', function(Request $request) use ($app)
{
    $id = $app['user_current'];
    $user = $app['user'];
    if($user === null) {
        return new Response("You are not authorised", 401);
    }
    else {
        $string = "{ 'id' : "+$id + "}\n}";
        return $string;
    }
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