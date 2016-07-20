<?php

namespace AppBundle\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..')
        ]);
    }

    /**
     * @Route("/ajaxurl", name="ajaxurl")
     */
    public function ajaxUrl(Request $request)
    {
        $client    = new Client();
        $urlsDone  = 0;
        $urls      = $request->request->get('urls');
        $initNum   = $request->request->get('initNum');
        $urlsArray = explode(",", $urls);
        $urlsNum   = count($urlsArray);

        $onRedirect = function (RequestInterface $request,
                                ResponseInterface $response,
                                UriInterface $uri) use ($urlsDone, $urlsArray, $initNum, $urlsNum) {
            $status = $response->getStatusCode();
            $length = $response->getBody()->getSize();
            echo "<p>"."<span class='initNumber'>".'*'.$initNum.'* '."</span>";
            echo "<span class='redirect'><b> Redirecting from: </b></span>".(string)$request->getUri()."<b> to: </b>".$uri." <b> status: </b>".$status." <b> length: </b>".$length."</p>";
            ob_flush();
            flush();
        };

        $requests = function ($urlsArray) use ($onRedirect) {
            foreach ($urlsArray as $url) {
                yield new \GuzzleHttp\Psr7\Request('GET', $url);
            }
        };

        $response = new StreamedResponse();
        $response->headers->set('Content-Type', 'text/event-stream');
        $pool = new Pool($client, $requests($urlsArray), [
            'concurency' => 1000,
            'fulfilled'  => function ($response, $index) use ($urlsDone, $urlsArray, $initNum, $urlsNum) {
                global $urlsDone;
                if ($response->getHeaderLine('X-Guzzle-Redirect-History') != "") {
                    $urlsDone        = $urlsDone + 1;
                    $status          = $response->getStatusCode();
                    $length          = $response->getBody()->getSize();
                    $redirectHistory = explode(',', $response->getHeaderLine('X-Guzzle-Redirect-History'));
                    $redirectHistory = array_slice($redirectHistory, -1);
                    $lastRedirect    = array_pop($redirectHistory);

                    echo "<p>"."<span class='initNumber'>".'*'.$initNum.'* '."</span>";
                    echo "<b> url: </b>".$lastRedirect." <b> status: </b>".$status."<b> length: </b>".$length." <b> urls done: <span class='urlsDone'>".$urlsDone.'/'.$urlsNum."</span></b></p>";
                    ob_flush();
                    flush();
                } else {
                    $urlsDone = $urlsDone + 1;
                    $status   = $response->getStatusCode();
                    $length   = $response->getBody()->getSize();

                    echo "<p>"."<span class='initNumber'>".'*'.$initNum.'* '."</span>";
                    echo $response->getHeaderLine('X-Guzzle-Redirect-History');
                    echo "<b>url: </b>".$urlsArray[$index]."<b> status: </b>".$status." <b> length: </b>".$length." <b> urls done: <span class='urlsDone'>".$urlsDone.'/'.$urlsNum."</span></b></p>";
                    ob_flush();
                    flush();
                }

            },
            'rejected'   => function ($reason, $index) use ($urlsDone, $urlsNum, $urlsArray, $initNum) {
                global $urlsDone;
                $urlsDone = $urlsDone + 1;
                echo "<p>";
                echo "<span class='initNumber'>".'*'.$initNum.'* '."</span>";
                echo "url: ".$urlsArray[$index]."status: <span class='urlError'> ERROR invalid URL </span>".$urlsDone.'/'.$urlsNum."</p>";
                ob_flush();
                flush();

            },
            'options'    => [
                'allow_redirects' => [
                    'max'             => 5,
                    'on_redirect'     => $onRedirect,
                    'track_redirects' => true
                ]
            ]
        ]);

        $response->setCallback(function () {});
        $promise = $pool->promise();
        \GuzzleHttp\Promise\settle($promise)->wait();
        return $response;
    }

}
