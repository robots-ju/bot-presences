<?php

namespace App\Repositories;

use Illuminate\Cache\Repository;

class MessageRepository
{
    protected $cache;

    public function __construct(Repository $cache)
    {
        $this->cache = $cache;
    }

    protected function cacheKeyForEvent($eventId)
    {
        return 'mapping_event_to_message_' . $eventId;
    }

    protected function cacheKeyForMessage($messageId)
    {
        return 'mapping_message_to_event_' . $messageId;
    }

    public function messageForEvent($eventId)
    {
        return $this->cache->get($this->cacheKeyForEvent($eventId));
    }

    public function eventForMessage($messageId)
    {
        return $this->cache->get($this->cacheKeyForMessage($messageId));
    }

    public function updateMessageForEvent($eventId, $newMessageId)
    {
        $currentMessageId = $this->messageForEvent($eventId);

        // TODO: garder la référence ?
        if ($currentMessageId) {
            $this->cache->forget($this->cacheKeyForMessage($currentMessageId));
        }

        $this->cache->put($this->cacheKeyForEvent($eventId), $newMessageId, 60);
        $this->cache->put($this->cacheKeyForMessage($newMessageId), $eventId, 60);
    }
}
