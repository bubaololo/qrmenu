<?php

namespace App\Auth\Socialite;

use SocialiteProviders\Manager\OAuth2\User;
use SocialiteProviders\Zalo\Provider as BaseZaloProvider;

/**
 * Zalo OAuth v4 mandates PKCE, which the community provider does not enable; and
 * its mapUserToObject() dereferences picture.data.url with no null-check (Zalo
 * accounts can lack an avatar / the API may return an error body). This subclass
 * fixes both. Registered in AppServiceProvider's SocialiteWasCalled listener.
 */
class ZaloProvider extends BaseZaloProvider
{
    protected $usesPKCE = true;

    /**
     * Zalo limits the profile API (graph.zalo.me/me) to Vietnamese IPs (error
     * -501). Route ONLY that request through a VN proxy when configured — the
     * token exchange (which carries the app `secret_key`) stays on the direct,
     * trusted connection, so the secret never touches the proxy.
     */
    protected function getUserByToken($token)
    {
        if ($proxy = config('services.zalo.proxy')) {
            $this->guzzle['proxy'] = $proxy;
            $this->httpClient = null; // rebuild the client with the proxy
        }

        return parent::getUserByToken($token);
    }

    protected function mapUserToObject(array $user)
    {
        $avatar = $user['picture']['data']['url'] ?? null;

        return (new User)->setRaw($user)->map([
            'id' => $user['id'] ?? null,
            'nickname' => null,
            'name' => $user['name'] ?? null,
            'avatar' => $avatar ? preg_replace('/^http:/i', 'https:', $avatar) : null,
        ]);
    }
}
