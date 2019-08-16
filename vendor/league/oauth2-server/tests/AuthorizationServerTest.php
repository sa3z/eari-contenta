<?php

namespace LeagueTests;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use LeagueTests\Stubs\AccessTokenEntity;
use LeagueTests\Stubs\AuthCodeEntity;
use LeagueTests\Stubs\ClientEntity;
use LeagueTests\Stubs\ScopeEntity;
use LeagueTests\Stubs\StubResponseType;
use LeagueTests\Stubs\UserEntity;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;

class AuthorizationServerTest extends TestCase
{
    const DEFAULT_SCOPE = 'basic';

    public function setUp()
    {
        // Make sure the keys have the correct permissions.
        chmod(__DIR__ . '/Stubs/private.key', 0600);
        chmod(__DIR__ . '/Stubs/public.key', 0600);
        chmod(__DIR__ . '/Stubs/private.key.crlf', 0600);
    }

    public function testRespondToRequestInvalidGrantType()
    {
        $server = new AuthorizationServer(
            $this->getMockBuilder(ClientRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock(),
            'file://' . __DIR__ . '/Stubs/private.key',
            base64_encode(random_bytes(36)),
            new StubResponseType()
        );

        $server->enableGrantType(new ClientCredentialsGrant(), new \DateInterval('PT1M'));

        try {
            $server->respondToAccessTokenRequest(ServerRequestFactory::fromGlobals(), new Response);
        } catch (OAuthServerException $e) {
            $this->assertEquals('unsupported_grant_type', $e->getErrorType());
            $this->assertEquals(400, $e->getHttpStatusCode());
        }
    }

    public function testRespondToRequest()
    {
        $clientRepository = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $clientRepository->method('getClientEntity')->willReturn(new ClientEntity());

        $scope = new ScopeEntity();
        $scopeRepositoryMock = $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock();
        $scopeRepositoryMock->method('getScopeEntityByIdentifier')->willReturn($scope);
        $scopeRepositoryMock->method('finalizeScopes')->willReturnArgument(0);

        $accessTokenRepositoryMock = $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock();
        $accessTokenRepositoryMock->method('getNewToken')->willReturn(new AccessTokenEntity());

        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepositoryMock,
            $scopeRepositoryMock,
            'file://' . __DIR__ . '/Stubs/private.key',
            base64_encode(random_bytes(36)),
            new StubResponseType()
        );

        $server->setDefaultScope(self::DEFAULT_SCOPE);
        $server->enableGrantType(new ClientCredentialsGrant(), new \DateInterval('PT1M'));

        $_POST['grant_type'] = 'client_credentials';
        $_POST['client_id'] = 'foo';
        $_POST['client_secret'] = 'bar';
        $response = $server->respondToAccessTokenRequest(ServerRequestFactory::fromGlobals(), new Response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetResponseType()
    {
        $clientRepository = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();

        $server = new AuthorizationServer(
            $clientRepository,
            $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock(),
            'file://' . __DIR__ . '/Stubs/private.key',
            'file://' . __DIR__ . '/Stubs/public.key'
        );

        $abstractGrantReflection = new \ReflectionClass($server);
        $method = $abstractGrantReflection->getMethod('getResponseType');
        $method->setAccessible(true);

        $this->assertInstanceOf(BearerTokenResponse::class, $method->invoke($server));
    }

    public function testGetResponseTypeExtended()
    {
        $clientRepository = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $privateKey = 'file://' . __DIR__ . '/Stubs/private.key';
        $encryptionKey = 'file://' . __DIR__ . '/Stubs/public.key';

        $server = new class($clientRepository, $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock(), $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock(), $privateKey, $encryptionKey) extends AuthorizationServer {
            protected function getResponseType()
            {
                $this->responseType = new class extends BearerTokenResponse {
                    /* @return null|CryptKey */
                    public function getPrivateKey()
                    {
                        return $this->privateKey;
                    }

                    public function getEncryptionKey()
                    {
                        return $this->encryptionKey;
                    }
                };

                return parent::getResponseType();
            }
        };

        $abstractGrantReflection = new \ReflectionClass($server);
        $method = $abstractGrantReflection->getMethod('getResponseType');
        $method->setAccessible(true);
        $responseType = $method->invoke($server);

        $this->assertInstanceOf(BearerTokenResponse::class, $responseType);
        // generated instances should have keys setup
        $this->assertSame($privateKey, $responseType->getPrivateKey()->getKeyPath());
        $this->assertSame($encryptionKey, $responseType->getEncryptionKey());
    }

    public function testMultipleRequestsGetDifferentResponseTypeInstances()
    {
        $privateKey = 'file://' . __DIR__ . '/Stubs/private.key';
        $encryptionKey = 'file://' . __DIR__ . '/Stubs/public.key';

        $responseTypePrototype = new class extends BearerTokenResponse {
            /* @return null|CryptKey */
            public function getPrivateKey()
            {
                return $this->privateKey;
            }

            public function getEncryptionKey()
            {
                return $this->encryptionKey;
            }
        };

        $clientRepository = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();

        $server = new AuthorizationServer(
            $clientRepository,
            $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock(),
            $privateKey,
            $encryptionKey,
            $responseTypePrototype
        );

        $abstractGrantReflection = new \ReflectionClass($server);
        $method = $abstractGrantReflection->getMethod('getResponseType');
        $method->setAccessible(true);

        $responseTypeA = $method->invoke($server);
        $responseTypeB = $method->invoke($server);

        // prototype should not get changed
        $this->assertNull($responseTypePrototype->getPrivateKey());
        $this->assertNull($responseTypePrototype->getEncryptionKey());

        // generated instances should have keys setup
        $this->assertSame($privateKey, $responseTypeA->getPrivateKey()->getKeyPath());
        $this->assertSame($encryptionKey, $responseTypeA->getEncryptionKey());

        // all instances should be different but based on the same prototype
        $this->assertSame(get_class($responseTypePrototype), get_class($responseTypeA));
        $this->assertSame(get_class($responseTypePrototype), get_class($responseTypeB));
        $this->assertNotSame($responseTypePrototype, $responseTypeA);
        $this->assertNotSame($responseTypePrototype, $responseTypeB);
        $this->assertNotSame($responseTypeA, $responseTypeB);
    }

    public function testCompleteAuthorizationRequest()
    {
        $clientRepository = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();

        $server = new AuthorizationServer(
            $clientRepository,
            $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock(),
            'file://' . __DIR__ . '/Stubs/private.key',
            'file://' . __DIR__ . '/Stubs/public.key'
        );

        $authCodeRepository = $this->getMockBuilder(AuthCodeRepositoryInterface::class)->getMock();
        $authCodeRepository->method('getNewAuthCode')->willReturn(new AuthCodeEntity());

        $grant = new AuthCodeGrant(
            $authCodeRepository,
            $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock(),
            new \DateInterval('PT10M')
        );

        $server->enableGrantType($grant);

        $authRequest = new AuthorizationRequest();
        $authRequest->setAuthorizationApproved(true);
        $authRequest->setClient(new ClientEntity());
        $authRequest->setGrantTypeId('authorization_code');
        $authRequest->setUser(new UserEntity());

        $this->assertInstanceOf(
            ResponseInterface::class,
            $server->completeAuthorizationRequest($authRequest, new Response)
        );
    }

    public function testValidateAuthorizationRequest()
    {
        $client = new ClientEntity();
        $client->setRedirectUri('http://foo/bar');
        $clientRepositoryMock = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $clientRepositoryMock->method('getClientEntity')->willReturn($client);

        $scope = new ScopeEntity();
        $scopeRepositoryMock = $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock();
        $scopeRepositoryMock->method('getScopeEntityByIdentifier')->willReturn($scope);

        $grant = new AuthCodeGrant(
            $this->getMockBuilder(AuthCodeRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock(),
            new \DateInterval('PT10M')
        );
        $grant->setClientRepository($clientRepositoryMock);

        $server = new AuthorizationServer(
            $clientRepositoryMock,
            $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock(),
            $scopeRepositoryMock,
            'file://' . __DIR__ . '/Stubs/private.key',
            'file://' . __DIR__ . '/Stubs/public.key'
        );

        $server->setDefaultScope(self::DEFAULT_SCOPE);
        $server->enableGrantType($grant);

        $request = new ServerRequest(
            [],
            [],
            null,
            null,
            'php://input',
            $headers = [],
            $cookies = [],
            $queryParams = [
                'response_type' => 'code',
                'client_id'     => 'foo',
            ]
        );

        $this->assertInstanceOf(AuthorizationRequest::class, $server->validateAuthorizationRequest($request));
    }

    public function testValidateAuthorizationRequestWithMissingRedirectUri()
    {
        $client = new ClientEntity();
        $clientRepositoryMock = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $clientRepositoryMock->method('getClientEntity')->willReturn($client);

        $grant = new AuthCodeGrant(
            $this->getMockBuilder(AuthCodeRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock(),
            new \DateInterval('PT10M')
        );
        $grant->setClientRepository($clientRepositoryMock);

        $server = new AuthorizationServer(
            $clientRepositoryMock,
            $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock(),
            'file://' . __DIR__ . '/Stubs/private.key',
            'file://' . __DIR__ . '/Stubs/public.key'
        );
        $server->enableGrantType($grant);

        $request = new ServerRequest(
            [],
            [],
            null,
            null,
            'php://input',
            $headers = [],
            $cookies = [],
            $queryParams = [
                'response_type' => 'code',
                'client_id'     => 'foo',
            ]
        );

        try {
            $server->validateAuthorizationRequest($request);
        } catch (OAuthServerException $e) {
            $this->assertEquals('invalid_client', $e->getErrorType());
            $this->assertEquals(401, $e->getHttpStatusCode());
        }
    }

    /**
     * @expectedException  \League\OAuth2\Server\Exception\OAuthServerException
     * @expectedExceptionCode 2
     */
    public function testValidateAuthorizationRequestUnregistered()
    {
        $server = new AuthorizationServer(
            $this->getMockBuilder(ClientRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock(),
            $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock(),
            'file://' . __DIR__ . '/Stubs/private.key',
            'file://' . __DIR__ . '/Stubs/public.key'
        );

        $request = new ServerRequest(
            [],
            [],
            null,
            null,
            'php://input',
            $headers = [],
            $cookies = [],
            $queryParams = [
                'response_type' => 'code',
                'client_id'     => 'foo',
            ]
        );

        $server->validateAuthorizationRequest($request);
    }
}
