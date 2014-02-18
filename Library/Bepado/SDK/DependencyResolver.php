<?php
/**
 * This file is part of the Bepado SDK Component.
 *
 * @version $Revision$
 */

namespace Bepado\SDK;

use Bepado\Common\Rpc;
use Bepado\Common\Struct\RpcCall;

/**
 * SDK Dependency Resolver
 *
 * Resolves the dependencies of the SDK components.
 */
class DependencyResolver
{
    /**
     * @var string
     */
    protected $apiKey;

    /**
     * Gateway to custom storage
     *
     * @var Gateway
     */
    protected $gateway;

    /**
     * Product toShop
     *
     * @var ProductToShop
     */
    protected $toShop;

    /**
     * Product fromShop
     *
     * @var ProductFromShop
     */
    protected $fromShop;

    /**
     * Error handler
     *
     * @var ErrorHandler
     */
    protected $errorHandler;

    /**
     * Service registry
     *
     * @var Rpc\ServiceRegistry
     */
    protected $registry;

    /**
     * Call marshaller
     *
     * @var Rpc\Marshaller\CallMarshaller
     */
    protected $marshaller;

    /**
     * Call unmarshaller
     *
     * @var Rpc\Marshaller\CallUnmarshaller
     */
    protected $unmarshaller;

    /**
     * Verificator dispatcher
     *
     * @var Struct\VerificatorDispatcher
     */
    protected $verificator;

    /**
     * Shopping service
     *
     * @var Service\Shopping
     */
    protected $shoppingService;

    /**
     * Verification service
     *
     * @var Service\Verification
     */
    protected $verificationService;

    /**
     * Search service
     *
     * @var Service\Search
     */
    protected $searchService;

    /**
     * Sync service
     *
     * @var Service\Syncer
     */
    protected $syncService;

    /**
     * Metric service
     *
     * @var Service\Metric
     */
    protected $metricService;

    /**
     * Product hasher
     *
     * @var ProductHasher
     */
    protected $productHasher;

    /**
     * Revision fromShop
     *
     * @var RevisionProvider
     */
    protected $revisionFromShop;

    /**
     * SocialNetwork service
     *
     * @var Service\SocialNetwork
     */
    protected $socialNetwork;

    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $socialNetworkHost = 'https://sn.bepado.de';

    /**
     * @var string
     */
    protected $transactionHost = 'https://transaction.bepado.de';

    /**
     * @var string
     */
    protected $searchHost = 'https://search.bepado.de';

    /**
     * @var ChangeVisitor\Message
     */
    protected $changeVisitor;

    /**
     * @var HttpClient\RequestSigner
     */
    protected $requestSigner;

    /**
     * @var string
     */
    protected $pluginSoftwareVersion;

    /**
     * @param \Bepado\SDK\Gateway $gateway
     * @param \Bepado\SDK\ProductToShop $toShop
     * @param \Bepado\SDK\ProductFromShop $fromShop
     * @param string $apiKey
     */
    public function __construct(
        Gateway $gateway,
        ProductToShop $toShop,
        ProductFromShop $fromShop,
        ErrorHandler $errorHandler,
        $apiKey,
        HttpClient\RequestSigner $requestSigner = null,
        $pluginSoftwareVersion = null
    ) {
        $this->gateway = $gateway;
        $this->toShop = $toShop;
        $this->fromShop = $fromShop;
        $this->errorHandler = $errorHandler;
        $this->apiKey = $apiKey;
        $this->requestSigner = $requestSigner;

        if ($host = getenv('_SOCIALNETWORK_HOST')) {
            $this->socialNetworkHost = "http://{$host}";
        }
        if ($host = getenv('_TRANSACTION_HOST')) {
            $this->transactionHost = "http://{$host}";
        }
        if ($host = getenv('_SEARCH_HOST')) {
            $this->searchHost = "http://{$host}";
        }

        $this->apiKey = $apiKey;
        $this->pluginSoftwareVersion = $pluginSoftwareVersion;
    }

    /**
     * Get from shop gateway
     *
     * @return ProductFromShop
     */
    public function getFromShop()
    {
        return $this->fromShop;
    }

    /**
     * Get to shop gateway
     *
     * @return ProductToShop
     */
    public function getToShop()
    {
        return $this->toShop;
    }

    /**
     * Get gateway
     *
     * Access to the gateway implementation
     *
     * @return Gateway
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * Get service registry
     *
     * Direct access to this class is provided for testing and verification.
     * Use this to issue a call without the need to craft the corresponding
     * XML.
     *
     * @return Rpc\ServiceRegistry
     */
    public function getServiceRegistry()
    {
        if ($this->registry === null) {
            $this->registry = new ServiceRegistry\Metric(
                new Rpc\ServiceRegistry(
                    new ServiceRegistry\RpcErrorWrapper(
                        $this->errorHandler,
                        new Rpc\ErrorHandler\XmlErrorHandler()
                    )
                ),
                $this->pluginSoftwareVersion
            );

            $this->registry->registerMetric(
                'products',
                'fromShop',
                $this->getMetricService()
            );

            $this->registry->registerService(
                'configuration',
                array('update', 'getCategoriesLastRevision', 'updateCategories'),
                new Service\Configuration(
                    $this->gateway
                )
            );

            $this->registry->registerService(
                'products',
                array('fromShop', 'peakFromShop', 'peakProducts', 'toShop', 'getLastRevision'),
                new Service\ProductService(
                    $this->gateway,
                    $this->gateway,
                    $this->gateway,
                    $this->toShop,
                    $this->fromShop
                )
            );

            $this->registry->registerService(
                'transaction',
                array('checkProducts', 'reserveProducts', 'buy', 'confirm'),
                new Service\Transaction(
                    $this->fromShop,
                    $this->gateway,
                    $this->getLogger(),
                    $this->gateway
                )
            );
        }

        return $this->registry;
    }

    /**
     * Get verificator
     *
     * Direct access to this class is provided for testing and verification.
     * Use this class to verify the structs you pass to the Bepado SDK. Bepado
     * will do this itself, but it might be useful to also do this yourself.
     *
     * @return \Bepado\SDK\Struct\VerificatorDispatcher
     */
    public function getVerificator()
    {
        if ($this->verificator === null) {
            $this->verificator = new Struct\VerificatorDispatcher(
                array(
                    'Bepado\\SDK\\Struct\\Order' =>
                        new Struct\Verificator\Order(),
                    'Bepado\\SDK\\Struct\\OrderItem' =>
                        new Struct\Verificator\OrderItem(),
                    'Bepado\\SDK\\Struct\\Product' =>
                        new Struct\Verificator\Product(),
                    'Bepado\\SDK\\Struct\\Change\\FromShop\\Insert' =>
                        new Struct\Verificator\Change\InsertOrUpdate(),
                    'Bepado\\SDK\\Struct\\Change\\FromShop\\Update' =>
                        new Struct\Verificator\Change\InsertOrUpdate(),
                    'Bepado\\SDK\\Struct\\Change\\FromShop\\Delete' =>
                        new Struct\Verificator\Change(),
                    'Bepado\\SDK\\Struct\\Change\\ToShop\\InsertOrUpdate' =>
                        new Struct\Verificator\Change\InsertOrUpdate(),
                    'Bepado\\SDK\\Struct\\Change\\ToShop\\Delete' =>
                        new Struct\Verificator\Change\Delete(),
                    'Bepado\\SDK\\Struct\\Change\\InterShop\\Update' =>
                        new Struct\Verificator\Change\InterShopUpdate(),
                    'Bepado\\SDK\\Struct\\Change\\InterShop\\Delete' =>
                        new Struct\Verificator\Change\InterShopDelete(),
                    'Bepado\\SDK\\Struct\\ShopConfiguration' =>
                        new Struct\Verificator\ShopConfiguration(),
                    'Bepado\\SDK\\Struct\\Reservation' =>
                        new Struct\Verificator\Reservation(),
                    'Bepado\\SDK\\Struct\\Message' =>
                        new Struct\Verificator\Message(),
                    'Bepado\\SDK\\Struct\\Address' =>
                        new Struct\Verificator\Address(),
                    'Bepado\\SDK\\Struct\\ProductList' =>
                        new Struct\Verificator\ProductList(),
                    'Bepado\\SDK\\Struct\\Tracking' =>
                        new Struct\Verificator\Tracking(),
                    'Bepado\\SDK\\Struct\\OrderStatus' =>
                        new Struct\Verificator\OrderStatus(),
                )
            );
        }

        return $this->verificator;
    }

    /**
     * @return Rpc\Marshaller\CallUnmarshaller
     */
    public function getUnmarshaller()
    {
        if ($this->unmarshaller === null) {
            $this->unmarshaller = new Rpc\Marshaller\CallUnmarshaller\XmlCallUnmarshaller(
                new Rpc\Marshaller\Converter\ErrorToExceptionConverter()
            );
        }

        return $this->unmarshaller;
    }

    /**
     * @return Rpc\Marshaller\CallMarshaller
     */
    public function getMarshaller()
    {
        if ($this->marshaller === null) {
            $this->marshaller = new Rpc\Marshaller\CallMarshaller\XmlCallMarshaller(
                new \Bepado\Common\XmlHelper(),
                new Rpc\Marshaller\Converter\ExceptionToErrorConverter()
            );
        }

        return $this->marshaller;
    }

    /**
     * @return Service\Shopping
     */
    public function getShoppingService()
    {
        if ($this->shoppingService === null) {
            $this->shoppingService = new Service\Shopping(
                new ShopFactory\Http(
                    $this,
                    $this->gateway
                ),
                $this->getChangeVisitor(),
                $this->toShop,
                $this->getLogger(),
                $this->errorHandler,
                new ShippingCostCalculator($this->gateway),
                $this->gateway
            );
        }

        return $this->shoppingService;
    }

    /**
     * @return Service\Verification
     */
    public function getVerificationService()
    {
        if ($this->verificationService === null) {
            $this->verificationService = new Service\Verification(
                $this->getHttpClient($this->socialNetworkHost),
                $this->gateway
            );
        }

        return $this->verificationService;
    }

    /**
     * @return Service\Search
     */
    public function getSearchService()
    {
        if ($this->searchService === null) {
            $this->searchService = new Service\Search(
                $this->getHttpClient($this->searchHost),
                $this->apiKey,
                $this->gateway->getShopId()
            );
        }

        return $this->searchService;
    }

    /**
     * @return Service\Syncer
     */
    public function getSyncService()
    {
        if ($this->syncService === null) {
            $this->syncService = new Service\Syncer(
                $this->gateway,
                $this->gateway,
                $this->fromShop,
                $this->getRevisionProvider(),
                $this->getProductHasher()
            );
        }

        return $this->syncService;
    }

    /**
     * @return Service\Metric
     */
    public function getMetricService()
    {
        if ($this->metricService === null) {
            $this->metricService = new Service\Metric(
                $this->gateway
            );
        }

        return $this->metricService;
    }

    /**
     * @return ProductHasher
     */
    public function getProductHasher()
    {
        if ($this->productHasher === null) {
            $this->productHasher = new ProductHasher\Simple();
        }

        return $this->productHasher;
    }

    /**
     * @return RevisionProvider
     */
    public function getRevisionProvider()
    {
        if ($this->revisionFromShop === null) {
            $this->revisionFromShop = new RevisionProvider\Time();
        }

        return $this->revisionFromShop;
    }

    /**
     * @return ChangeVisitor
     */
    public function getChangeVisitor()
    {
        if ($this->changeVisitor === null) {
            $this->changeVisitor = new ChangeVisitor\Message(
                $this->getVerificator()
            );
        }

        return $this->changeVisitor;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = new Logger\Http(
                $this->getHttpClient($this->transactionHost),
                $this->apiKey
            );
        }

        return $this->logger;
    }

    /**
     * @param string $server
     *
     * @return \Bepado\SDK\HttpClient
     */
    public function getHttpClient($server)
    {
        $version = SDK::VERSION === '$Revision$' ? 'dev' : SDK::VERSION;

        $headers = array(
            'X-Bepado-SDK-Version: ' . $version,
            'Accept: applications/x-bepado-json-' . $version,
        );

        $client = new HttpClient\Stream($server);
        $client->addDefaultHeaders($headers);

        return $client;
    }

    /**
     * @return HttpClient\RequestSigner
     */
    public function getRequestSigner()
    {
        if ($this->requestSigner === null) {
            $this->requestSigner = new HttpClient\SharedKeyRequestSigner(
                $this->getGateway(),
                new Service\Clock(),
                $this->apiKey
            );
        }

        return $this->requestSigner;
    }

    /**
     * @return Service\SocialNetwork
     */
    public function getSocialNetworkService()
    {
        if ($this->socialNetwork === null) {
            $this->socialNetwork = new Service\SocialNetwork(
                $this->getHttpClient($this->socialNetworkHost),
                $this->getVerificator(),
                $this->gateway->getShopId(),
                $this->apiKey
            );
        }

        return $this->socialNetwork;
    }
}
