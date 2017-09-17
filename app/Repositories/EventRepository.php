<?php

namespace App\Repositories;

use Carbon\Carbon;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Illuminate\Cache\Repository;

class EventRepository
{
    protected $calendar;
    protected $cache;

    public function __construct(Google_Service_Calendar $calendar, Repository $cache)
    {
        $this->calendar = $calendar;
        $this->cache = $cache;
    }

    protected function cacheKey($eventId)
    {
        return 'google_event_' . $eventId;
    }

    protected function updateEventCache(Google_Service_Calendar_Event $event)
    {
        $this->cache->add($this->cacheKey($event->getId()), $event, 60);
    }

    public function index($calendarId)
    {
        $response = $this->calendar->events->listEvents($calendarId, [
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'timeMin' => Carbon::now()->toRfc3339String(),
            'timeMax' => Carbon::now()->addWeeks(4)->next(Carbon::SUNDAY)->toRfc3339String(),
        ]);

        $events = $response->getItems();

        $kept = [];

        foreach ($events as $event) {
            /** @var Google_Service_Calendar_Event $event */
            $this->updateEventCache($event);

            // On retourne uniquement les événements qui commencent avec un texte entre "[]"
            if (starts_with($event->getSummary(), '[')) {
                $kept[] = $event;
            }
        }

        return $kept;
    }

    public function get($calendarId, $eventId)
    {
        $key = $this->cacheKey($eventId);

        if ($this->cache->has($key)) {
            return $key;
        }

        $event = $this->calendar->events->get($calendarId, $eventId);

        $this->updateEventCache($event);

        return $event;
    }

    public function update($calendarId, Google_Service_Calendar_Event $event)
    {
        $this->calendar->events->update($calendarId, $event->getId(), $event);
    }
}
