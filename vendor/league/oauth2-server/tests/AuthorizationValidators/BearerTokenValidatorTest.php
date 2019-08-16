<?php

namespace LeagueTests\AuthorizationValidators;

use Lcobucci\JWT\Builder;
use League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\ServerRequest;

class BearerTokenValidatorTest extends TestCase
{
    /**
     * @expectedException League\OAuth2\Server\Exception\OAuthServerException
     * @expectedExceptionCode 9
     */
    public function testThrowExceptionWhenAccessTokenIsNotSigned()
    {
        $accessTokenRepositoryMock = $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock();

        $bearerTokenValidator = new BearerTokenValidator($accessTokenRepositoryMock);
        $bearerTokenValidator->setPublicKey(new CryptKey('file://' . __DIR__ . '/../Stubs/public.key'));

        $unsignedJwt = (new Builder())
            ->setAudience('client-id')
            ->setId('token-id', true)
            ->setIssuedAt(time())
            ->setNotBefore(time())
            ->setExpiration(time())
            ->setSubject('user-id')
            ->set('scopes', 'scope1 scope2 scope3 scope4')
            ->getToken();

        $request = new ServerRequest();
        $request = $request->withHeader('authorization', sprintf('Bearer %s', $unsignedJwt));

        $bearerTokenValidator->validateAuthorization($request);
    }
}
