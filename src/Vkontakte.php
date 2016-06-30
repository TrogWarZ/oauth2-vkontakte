<?php

namespace J4k\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class Vkontakte extends AbstractProvider
{
    protected $baseOAuthUri = 'https://oauth.vk.com';
    protected $baseUri      = 'https://api.vk.com/method';
    protected $version      = '5.52';

    /**
     * @type array
     * @see https://vk.com/dev/permissions
     */
    public $scopes = [
        'email',
        'friends',
        'offline',
        'photos',
        'wall',
        //'ads',
        //'audio',
        //'docs',
        //'groups',
        //'market',
        //'messages',
        //'nohttps',
        //'notes',
        //'notifications',
        //'notify',
        //'pages',
        //'stats',
        //'status',
        //'video',
    ];
    /**
     * @type array
     * @see https://new.vk.com/dev/fields
     */
    public $userFields = [
        'about',
        'bdate',
        'can_post',
        'city',
        'contacts',
        'counters',
        'country',
        'domain',
        'first_name',
        'friend_status',
        'has_mobile',
        'has_photo',
        'home_town',
        'id',
        'is_friend',
        'last_name',
        'maiden_name',
        'nickname',
        'photo_max',
        'photo_max_orig',
        'screen_name',
        'sex',
        'timezone',
        //'activities',
        //'blacklisted',
        //'blacklisted_by_me',
        //'books',
        //'can_see_all_posts',
        //'can_see_audio',
        //'can_send_friend_request',
        //'can_write_private_message',
        //'career',
        //'common_count',
        //'connections',
        //'crop_photo',
        //'deactivated',
        //'education',
        //'exports',
        //'followers_count',
        //'games',
        //'hidden',
        //'interests',
        //'is_favorite',
        //'is_hidden_from_feed',
        //'last_seen',
        //'military',
        //'movies',
        //'occupation',
        //'online',
        //'personal',
        //'photo_100',
        //'photo_200',
        //'photo_200_orig',
        //'photo_400_orig',
        //'photo_50',
        //'photo_id',
        //'quotes',
        //'relation',
        //'relatives',
        //'schools',
        //'site',
        //'status',
        //'tv',
        //'universities',
        //'verified',
        //'wall_comments',
    ];

    // ========== Abstract ==========

    public function getBaseAuthorizationUrl()
    {
        return "$this->baseOAuthUri/authorize";
    }
    public function getBaseAccessTokenUrl(array $params)
    {
        return "$this->baseOAuthUri/access_token";
    }
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        $params = [
            'fields'       => $this->userFields,
            'access_token' => $token->getToken(),
            'v'            => $this->version,
        ];
        $query  = $this->buildQueryString($params);
        $url    = "$this->baseUri/users.get?$query";

        return $url;
    }
    protected function getDefaultScopes()
    {
        return $this->scopes;
    }
    protected function checkResponse(ResponseInterface $response, $data)
    {
        // Metadata info
        $contentType = $response->getHeader('Content-Type');
        /** @noinspection PhpPassByRefInspection */
        $contentType = reset((explode(';', reset($contentType))));
        // Response info
        $responseCode    = $response->getStatusCode();
        $responseMessage = $response->getReasonPhrase();
        // Data info
        $error            = !empty($data['error']) ? $data['error'] : null;
        $errorCode        = !empty($error['error_code']) ? $error['error_code'] : $responseCode;
        $errorDescription = !empty($data['error_description']) ? $data['error_description'] : null;
        $errorMessage     = !empty($error['error_msg']) ? $error['error_msg'] : $errorDescription;
        $message          = $errorMessage ?: $responseMessage;

        // Request/meta validation
        if (399 < $responseCode)
            throw new IdentityProviderException($message, $responseCode, $data);

        // Content validation
        if ('application/json' != $contentType)
            throw new IdentityProviderException($message, $responseCode, $data);
        if ($error)
            throw new IdentityProviderException($errorMessage, $errorCode, $data);
    }
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        $response          = reset($response['response']);
        $additional        = $token->getValues();
        $response['email'] = !empty($additional['email']) ? $additional['email'] : null;
        $response['id']    = !empty($additional['user_id']) ? $additional['user_id'] : null;

        return new User($response, $response['id']);
    }

    // ========== API methods ==========

    /**
     * @see https://vk.com/dev/users.get
     *
     * @param integer[]        $ids
     * @param AccessToken|null $token Current user if empty
     * @param array            $params
     *
     * @return User[]
     */
    public function usersGet(array $ids = [], AccessToken $token = null, array $params = [])
    {
        if (empty($ids) && !$token)
            throw new \InvalidArgumentException('Some of parameters usersIds OR access_token are required');

        $default = [
            'user_ids'     => implode(',', $ids),
            'fields'       => $this->userFields,
            'access_token' => $token ? $token->getToken() : null,
            'v'            => $this->version,
        ];
        $params  = array_merge($default, $params);
        $query   = $this->buildQueryString($params);
        $url     = "$this->baseUri/users.get?$query";

        $response   = $this->getResponse($this->createRequest(static::METHOD_GET, $url, $token, []))['response'];
        $users      = !empty($response['items']) ? $response['items'] : $response;
        $array2user = function ($userData) { return new User($userData); };

        return array_map($array2user, $users);
    }
    /**
     * @see https://vk.com/dev/friends.get
     *
     * @param integer          $userId
     * @param AccessToken|null $token
     * @param array            $params
     *
     * @return User[]
     */
    public function friendsGet($userId, AccessToken $token = null, array $params = [])
    {
        $default = [
            'user_id'      => $userId,
            'fields'       => $this->userFields,
            'access_token' => $token ? $token->getToken() : null,
            'v'            => $this->version,
        ];
        $params  = array_merge($default, $params);
        $query   = $this->buildQueryString($params);
        $url     = "$this->baseUri/friends.get?$query";

        $response     = $this->getResponse($this->createRequest(static::METHOD_GET, $url, $token, []))['response'];
        $friends      = !empty($response['items']) ? $response['items'] : $response;
        $array2friend = function ($friendData) { return new User($friendData); };

        return array_map($array2friend, $friends);
    }
}
