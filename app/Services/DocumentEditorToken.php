<?php

namespace App\Services;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

class DocumentEditorToken
{
    private function configuration(): Configuration
    {
        $secret = (string) config('documents.secret');
        abort_if(strlen($secret) < 32, 503, 'The document server is not configured.');
        return Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($secret));
    }

    public function sign(array $payload): string
    {
        $config = $this->configuration();
        $builder = $config->builder()->expiresAt(new \DateTimeImmutable('+8 hours'));
        foreach ($payload as $key => $value) { $builder = $builder->withClaim($key, $value); }
        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    public function verify(string $jwt): array
    {
        $config = $this->configuration();
        try {
            $token = $config->parser()->parse($jwt);
            abort_unless($token instanceof Plain && $config->validator()->validate($token, new SignedWith($config->signer(), $config->signingKey())), 403);
            $expires = $token->claims()->get('exp');
            abort_if($expires && $expires->getTimestamp() <= time(), 403);
            return $token->claims()->all();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            throw $exception;
        } catch (\Throwable) { abort(403, 'Invalid document server token.'); }
    }

    public function internalDownloadUrl(string $url): string
    {
        $parts = parse_url($url);
        abort_unless(is_array($parts) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment']), 422);
        foreach (array_unique([config('documents.url'), config('documents.internal_url')]) as $base) {
            if ($base && str_starts_with($url, $base.'/')) {
                return config('documents.internal_url').substr($url, strlen($base));
            }
        }
        abort(422, 'The edited file must come from the configured document server.');
    }
}
