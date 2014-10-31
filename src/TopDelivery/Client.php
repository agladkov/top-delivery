<?php

namespace TopDelivery;

class Client {
    /**
     * @var \Guzzle\Http\Client
     */
    private $client;

    private $token;

    private $apiURL = 'http://is.topdelivery.ru/tests/xmlGate/index.php';

    public function __construct($login, $password, $debug = true) {
        if ($debug !== false) {
            $this->apiURL = 'http://production.is.topdelivery.ru/tests/xmlGate/index.php';
            list($login, $password) = array('user', 'pass');
        }
        $this->token = array(
            'login' => $login,
            'password' => $password,
        );
        $this->client = new \Guzzle\Http\Client();
        return $this;
    }

    private function getChild(&$node, $name) {
        if (!is_array($node) || empty($node['value']))
            return null;
        foreach ($node['value'] as &$value) {
            if ($value['name'] == $name)
                return $value;
        }
    }

    private function getChildValue(&$node, $name) {
        if (!is_array($node) || empty($node['value']))
            return null;
        foreach ($node['value'] as &$value) {
            if ($value['name'] == $name)
                return $value['value'];
        }
    }

    public function __call($method, $arguments) {
        try {
            $xmlParams = array();
            if (count($arguments) == 1 && is_array($arguments[0]))
                $xmlParams = array_merge($xmlParams, $arguments[0]);
            else
                $xmlParams = array_merge($xmlParams, $arguments);
            $body = array(
                array(
                    'name' => 'response_type',
                    'value' => 'json',
                ),
                array(
                    'name' => 'reqname',
                    'value' => $method,
                ),
                array(
                    'name' => 'params',
                    'value' => $xmlParams,
                ),
            );
            $writer = new \Sabre\XML\Writer();
            $writer->openMemory();
            $writer->setIndent(true);
            $writer->write(array(
                'xml' => array(
                    'request' => array(
                        'auth' => $this->token,
                        'body' => $body,
                    ),
                ),
            ));

            $post = $writer->outputMemory();
            $result = $this->client->post($this->apiURL, null, $post, array(
                'auth' => array(
                    'user', 'pass',
                )
            ))->send()->getBody(true);
            $reader = new \Sabre\XML\Reader();
            $reader->xml($result);
            $data = $reader->parse();
            if (empty($data) || empty($data['name']) || $data['name'] != 'xml') {
                var_dump($result, $data);
                throw new \Exception("[ERROR][UNKNOWN] Incorrect answer");
            }
            $response = $this->getChild($data, 'response');
            if (empty($response)) {
                var_dump($result, $data);
                throw new \Exception("[ERROR][UNKNOWN] Incorrect answer");
            }
            $status = $this->getChildValue($response, 'status');
            if (empty($status) || intval($status) != 1) {
                $errors = $this->getChildValue($response, 'errors');
                if (empty($errors)) {
                    var_dump($response, $errors);
                    throw new \Exception("[ERROR][UNKNOWN] Incorrect answer");
                }
                foreach ($errors as &$error) {
                    if ($error['name'] == 'error')
                        throw new \Exception("[ERROR][{$error['attributes']['code']}] {$this->getChildValue($error, 'title')}: {$this->getChildValue($error, 'description')}");
                }
                var_dump($response);
                throw new \Exception("[ERROR][UNKNOWN] Incorrect answer");
            }
            $result = $this->getChildValue($response, 'result');
            if (!empty($result))
                return json_decode($result);
            else {
                var_dump($response);
                throw new \Exception("[ERROR][UNKNOWN] Empty result");
            }
        } catch (RequestException $e) {
            $errorStr = "message: {$e->getMessage()}\n";
            $errorStr .= "request: {$e->getRequest()}\n";
            if ($e->hasResponse()) {
                $errorStr .= "response: {$e->getResponse()}\n";
            }
            throw new Exception($errorStr);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
