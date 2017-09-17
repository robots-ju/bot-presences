<?php

namespace App;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class DiscordApi
{
    protected $client;

    public function __construct($token)
    {
        $this->client = new Client([
            'base_uri' => 'https://discordapp.com/api/v6/',
            'headers' => [
                'Authorization' => 'Bot ' . $token,
            ],
        ]);
    }

    protected function response(ResponseInterface $response)
    {
        return json_decode($response->getBody()->getContents());
    }

    public function getChannel($channelId)
    {
        return $this->response($this->client->get('channels/' . $channelId));
    }

    public function getChannelMessages($channelId)
    {
        return $this->response($this->client->get('channels/' . $channelId . '/messages'));
    }

    public function getChannelMessageReactions($channelId, $messageId, $emoji)
    {
        return $this->response($this->client->get('channels/' . $channelId . '/messages' . $messageId . '/reactions/' . $emoji));
    }

    public function createChannelMessage($channelId, $data)
    {
        return $this->response($this->client->post('channels/' . $channelId . '/messages', [
            'json' => $data,
        ]));
    }

    public function createChannelMessageReaction($channelId, $messageId, $emoji)
    {
        return $this->response($this->client->put('channels/' . $channelId . '/messages/' . $messageId . '/reactions/' . $emoji . '/@me'));
    }

    public function deleteChannelMessageReaction($channelId, $messageId, $emoji)
    {
        return $this->response($this->client->delete('channels/' . $channelId . '/messages/' . $messageId . '/reactions/' . $emoji . '/@me'));
    }
}
