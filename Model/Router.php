<?php

/**
 * API router.
 */
class Bold_CheckoutPaymentBooster_Model_Router extends Mage_Core_Controller_Varien_Router_Abstract
{
    /**
     * @var Mage_Core_Controller_Varien_Front
     */
    private $front;

    /**
     * @var array
     */
    private $routes = [];

    /**
     * Initialize router with configured routes.
     */
    public function __construct()
    {
        $this->routes['rest'] = Mage::getConfig()->getNode('rest')
            ? Mage::getConfig()->getNode('rest')->asArray()
            : [];
    }

    /**
     * @inheritDoc
     */
    public function setFront($front)
    {
        $this->front = $front;
    }

    /**
     * @inheritDoc
     */
    public function getFront()
    {
        return $this->front;
    }

    /**
     * Try to match request with configured routes.
     *
     * @param string $path
     * @param string $method
     * @param array $routes
     * @param string $currPath
     * @return array|null
     */
    private function matchSegment($path, $method, array $routes, $currPath = '')
    {
        krsort($routes);
        foreach ($routes as $segment => $subRoutes) {
            $segmentAsRegEx = preg_replace('/\_([a-zA-z0-9\-_]*)/', '(?<$1>[a-zA-Z0-9\-_]*)', $segment);
            $prefix = $currPath . '/' . $segmentAsRegEx;
            if (preg_match('!^' . $prefix . '$!', $path, $matches)) {
                if (isset($subRoutes[$method])) {
                    unset($matches[0]);
                    return [$subRoutes[$method], $matches];
                } else {
                    return null;
                }
            } elseif (preg_match('!^' . $prefix . '!', $path)) {
                return $this->matchSegment($path, $method, $subRoutes, $prefix);
            }
        }
        return null;
    }

    /**
     * Build handler function arguments.
     *
     * @param ReflectionFunction|ReflectionMethod $function
     * @param array $matchedArguments
     * @return array
     */
    private function buildCallArguments(ReflectionMethod $function, array $matchedArguments)
    {
        $arguments = [];
        foreach ($function->getParameters() as $parameter) {
            if (isset($matchedArguments[$parameter->getName()])) {
                $arguments[$parameter->getName()] = $matchedArguments[$parameter->getName()];
            }
        }
        return $arguments;
    }

    /**
     * Call configured handler with params.
     *
     * @param string $name
     * @param array $matchedArguments
     * @param Zend_Controller_Request_Http $request
     * @return mixed
     * @throws ReflectionException
     */
    private function invokeHandler(
        $name,
        array $matchedArguments,
        Zend_Controller_Request_Http $request
    ) {
        if (strpos($name, '::') !== -1) {
            list($className, $methodName) = explode('::', $name);
            if (!is_callable([$className, $methodName])) {
                throw new BadFunctionCallException();
            }
            $reflectionClass = new ReflectionClass($className);
            $function = $reflectionClass->getMethod($methodName);
            try {
                $requestData = json_decode($request->getRawBody(), true);
            } catch (Exception $e) {
                $requestData = [];
            }
            $matchedArguments['data'] = $requestData;
            return $function->invokeArgs(null, $this->buildCallArguments($function, $matchedArguments));
        } else {
            if (!is_callable($name)) {
                throw new InvalidArgumentException();
            }
            $function = new ReflectionFunction($name);
            return $function->invokeArgs($this->buildCallArguments($function, $matchedArguments));
        }
    }

    /**
     * Verify, authorize Bold api request and call handler.
     *
     * @param Zend_Controller_Request_Http $request
     * @return bool|Zend_Controller_Response_Http
     * @throws Exception
     */
    public function match(Zend_Controller_Request_Http $request)
    {
        $tracingId = sha1(microtime());
        $handlerFunction = $this->matchSegment(
            $request->getPathInfo(),
            $request->getMethod(),
            $this->routes
        );
        if (!$handlerFunction) {
            return false;
        }
        $websiteId = Mage::app()->getWebsite()->getId();
        $this->logRequest(
            $tracingId,
            $request->getMethod() . ' ' . $request->getHttpHost() . $request->getRequestUri(),
            $websiteId
        );
        $this->logRequest(
            $tracingId,
            'Body: ' . $request->getRawBody(),
            $websiteId
        );
        $response = $this->getFront()->getResponse();
        list($service, $matchedArguments) = $handlerFunction;
        try {
            $authorized = $this->authorize($request, $websiteId);
        } catch (Exception $e) {
            $authorized = false;
        }
        if (!$authorized) {
            $this->logRequest($tracingId, 'Unauthorized.', $websiteId);
            $response->setBody(json_encode(['errors' => ['Unauthorized.']]));
            $response->setHttpResponseCode(401);
            $request->setDispatched();
            return $response;
        }
        try {
            $result = $this->invokeHandler(
                isset($service['handler']) ? $service['handler'] : '::',
                $matchedArguments,
                $request
            );
            $this->prepareResponse($response, json_encode($result));
        } catch (Exception $e) {
            $this->prepareResponse(
                $response,
                json_encode(['errors' => [$e->getMessage()]]),
                500
            );
        }
        $request->setDispatched();
        $this->logRequest($tracingId, 'Result Code: ' . $response->getHttpResponseCode(), $websiteId);
        $body = 'Result Body: ' . $response->getBody();
        $logMessage = strlen($body) > 500
            ? substr($body, 0, 200) . ' ... ' . substr($body, -200, 200)
            : $body;
        $this->logRequest($tracingId, $logMessage, $websiteId);
        return $response;
    }

    /**
     * Fill response with data.
     *
     * @param Mage_Core_Controller_Response_Http $response
     * @param string|null $body
     * @param int $code
     */
    private function prepareResponse(
        Mage_Core_Controller_Response_Http $response,
        $body = null,
        $code = 200
    ) {
        $response->setHttpResponseCode((int)$code);
        $response->setHeader('Content-Type', 'application/json', true);
        $response->setBody($body);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Log Bold requests.
     *
     * @param string $tracingId
     * @param string $message
     * @param int $websiteId
     * @return void
     */
    private function logRequest($tracingId, $message, $websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        if (!$config->isLogEnabled($websiteId)) {
            return;
        }
        Mage::log(
            $tracingId . ': Incoming Call: ' . $message,
            Zend_Log::DEBUG,
            'bold_checkout_payment_booster.log',
            true
        );
    }

    /**
     * Authorize Bold requests.
     *
     * @param Zend_Controller_Request_Http $request
     * @param int $websiteId
     * @return bool
     */
    private function authorize(Zend_Controller_Request_Http $request, $websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $sharedSecret = $config->getSharedSecret($websiteId);
        preg_match('/signature="(\S*?)"/', $request->getHeader('Signature'), $matches);
        $signature = isset($matches[1]) ? $matches[1] : null;
        if (!$signature) {
            return false;
        }
        return hash_equals(
            base64_encode(
                hash_hmac(
                    'sha256',
                    'x-hmac-timestamp: ' . $request->getHeader('X-HMAC-Timestamp'),
                    $sharedSecret,
                    true
                )
            ),
            $signature
        );
    }
}
