<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2018 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace App\Controller;

use App\Entity\PublicKeyCredentialSource;
use Assert\Assertion;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use App\Repository\PublicKeyCredentialUserEntityRepository;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialSourceRepository;

final class AttestationResponseController
{
    private const SESSION_PARAMETER = '__WEBAUTHN__ATTESTATION__REQUEST__';

    /**
     * @var PublicKeyCredentialUserEntityRepository
     */
    private $userEntityRepository;

    /**
     * @var PublicKeyCredentialSourceRepository
     */
    private $credentialSourceRepository;

    /**
     * @var PublicKeyCredentialLoader
     */
    private $publicKeyCredentialLoader;

    /**
     * @var AuthenticatorAttestationResponseValidator
     */
    private $attestationResponseValidator;

    /**
     * @var HttpMessageFactoryInterface
     */
    private $httpMessageFactory;

    public function __construct(HttpMessageFactoryInterface $httpMessageFactory, PublicKeyCredentialLoader $publicKeyCredentialLoader, AuthenticatorAttestationResponseValidator $attestationResponseValidator, PublicKeyCredentialUserEntityRepository $userEntityRepository, PublicKeyCredentialSourceRepository $credentialSourceRepository)
    {
        $this->attestationResponseValidator = $attestationResponseValidator;
        $this->userEntityRepository = $userEntityRepository;
        $this->credentialSourceRepository = $credentialSourceRepository;
        $this->publicKeyCredentialLoader = $publicKeyCredentialLoader;
        $this->httpMessageFactory = $httpMessageFactory;
    }

    public function __invoke(Request $request): Response
    {
        try {
            $psr7Request = $this->httpMessageFactory->createRequest($request);
            Assertion::eq('json', $request->getContentType(), 'Only JSON content type allowed');
            $content = $request->getContent();
            Assertion::string($content, 'Invalid data');
            $publicKeyCredential = $this->publicKeyCredentialLoader->load($content);
            $response = $publicKeyCredential->getResponse();
            Assertion::isInstanceOf($response, AuthenticatorAttestationResponse::class, 'Invalid response');
            /** @var PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions */
            $publicKeyCredentialCreationOptions = $request->getSession()->get(self::SESSION_PARAMETER);
            $request->getSession()->remove(self::SESSION_PARAMETER);
            Assertion::isInstanceOf($publicKeyCredentialCreationOptions, PublicKeyCredentialCreationOptions::class, 'Unable to find the public key credential creation options');
            $this->attestationResponseValidator->check($response, $publicKeyCredentialCreationOptions, $psr7Request);
            $this->userEntityRepository->saveUserEntity($publicKeyCredentialCreationOptions->getUser());
            $credential = new PublicKeyCredentialSource(
                $publicKeyCredential->getRawId(),
                $publicKeyCredential->getType(),
                [],
                $response->getAttestationObject()->getAttStmt()->getType(),
                $response->getAttestationObject()->getAttStmt()->getTrustPath(),
                $response->getAttestationObject()->getAuthData()->getAttestedCredentialData()->getAaguid(),
                $response->getAttestationObject()->getAuthData()->getAttestedCredentialData()->getCredentialPublicKey(),
                $publicKeyCredentialCreationOptions->getUser()->getId(),
                $response->getAttestationObject()->getAuthData()->getSignCount()
            );

            $this->credentialSourceRepository->saveCredentialSource($credential);

            return new JsonResponse(['status' => 'ok', 'errorMessage' => '']);
        } catch (\Throwable $throwable) {
            return new JsonResponse(['status' => 'failed', 'errorMessage' => $throwable->getMessage(), 'errorCode' => $throwable->getCode(), 'errorFile' => $throwable->getFile(), 'errorLine' => $throwable->getLine()], 400);
        }
    }
}
