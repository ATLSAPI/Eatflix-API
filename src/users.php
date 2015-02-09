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