<?php

namespace App\Services;
use Illuminate\Http\Request;
//use App\Services\Http;
use Illuminate\Support\Facades\Http;


use GuzzleHttp\Client;

class MantisService
{
    protected $url;
    protected $http;
    protected $headers;

    // public function __construct(Client $client)
    // {
    //     $this->url = 'https://api.iamgds.com/ota/Auth';
    //     $this->http = $client;
    //     $this->headers = [
    //         'cache-control' => 'no-cache',
    //         'content-type' => 'application/x-www-form-urlencoded',
    //     ];
    // }
    
    public function getToken(string $uri = null)
    {
        $full_path = $this->url;
        $full_path .= $uri;
        $request = $this->http->post($full_path, [
            'ClientId' => 50,
            'ClientSecret'=> 'd66de12fa3473a93415b02494253f088',
            'timeout'         => 30,
            'connect_timeout' => true,
            'http_errors'     => true,
            'verify' => false
        ]);

        $response = $request ? $request->getBody()->getContents() : null;
        $status = $request ? $request->getStatusCode() : 500;

        if ($response && $status === 200 && $response !== 'null') {
            return (object) json_decode($response);
        }

        return null;
    }
    private function getResponse(string $uri = null)
    {
        $full_path = $this->url;
        $full_path .= $uri;

        $request = $this->http->get($full_path, [
            'headers'         => $this->headers,
            'timeout'         => 30,
            'connect_timeout' => true,
            'http_errors'     => true,
        ]);

        $response = $request ? $request->getBody()->getContents() : null;
        $status = $request ? $request->getStatusCode() : 500;

        if ($response && $status === 200 && $response !== 'null') {
            return (object) json_decode($response);
        }

        return null;
    }

    private function postResponse(string $uri = null, array $post_params = [])
    {
        $full_path = $this->url;
        $full_path .= $uri;

        $request = $this->http->post($full_path, [
            'headers'         => $this->headers,
            'timeout'         => 30,
            'connect_timeout' => true,
            'http_errors'     => true,
            'form_params'     => $post_params,
        ]);

        $response = $request ? $request->getBody()->getContents() : null;
        $status = $request ? $request->getStatusCode() : 500;

        if ($response && $status === 200 && $response !== 'null') {
            return (object) json_decode($response);
        }

        return null;
    }
}