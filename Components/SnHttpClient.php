<?php


namespace ShopwarePlugins\Connect\Components;

use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use Shopware\Connect\Gateway;

class SnHttpClient
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $httpClient;

    /**
     * @var \Shopware\Connect\Gateway
     */
    private $gateway;

    /**
     * @var \ShopwarePlugins\Connect\Components\Config
     */
    private $configComponent;

    /**
     * @var \Firebase\JWT\JWT
     */
    private $jwt;

    public function __construct(
        ClientInterface $httpClient,
        Gateway $gateway,
        Config $config,
        JWT $jwt
    )
    {
        $this->httpClient = $httpClient;
        $this->gateway = $gateway;
        $this->configComponent = $config;
        $this->jwt = $jwt;
    }

    /**
     * Call SocialNetwork REST API
     *
     * @param array $data
     * @param string $path
     * @return \Shopware\Components\HttpClient\Response
     */
    public function sendRequestToConnect(array $data, $path)
    {
        $host = $this->configComponent->getConfig('connectDebugHost');
        if ($host) {
            $host = 'sn.' . $host;
        } else {
            $host = 'sn.' . $this->configComponent->getMarketplaceUrl();
        }

        $shopId = $this->gateway->getShopId();
        $key = $this->configComponent->getConfig('apiKey');
        $token = array(
            "iss" => $shopId,
            "aud" => "SocialNetwork",
            "iat" => time(),
            "nbf" => time(),
            "exp" => time() + (60),
            "content" => $data
        );
        $connectAuthKey = $this->jwt->encode($token, $key);
        $url = $host . '/rest/' . $path;

        $response = $this->httpClient->post(
            $url,
            array(
                'content-type' => 'application/json',
                'X-Shopware-Connect-Shop' => $shopId,
                'X-Shopware-Connect-Key' => $connectAuthKey
            )
        );

        return $response;
    }
}