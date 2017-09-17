<?php

namespace App;

use Carbon\Carbon;
use Google_Service_Calendar_Event;
use Illuminate\Support\Arr;

class EventStateManager
{
    protected $event;
    protected $data;
    protected $hasData;
    protected $dirty;
    protected $decisionChanged;

    const DATA_DELIMITER = '--------------------';

    public function __construct(Google_Service_Calendar_Event $event)
    {
        $this->event = $event;

        $this->parseData();
    }

    protected function parseData()
    {
        $description = $this->event->getDescription();

        $parts = explode(self::DATA_DELIMITER, $description);

        $this->hasData = true;
        $this->dirty = false;
        $this->decisionChanged = false;
        $this->data = json_decode($parts[0], true);
        if (!$this->data) {
            $this->data = [];
            $this->hasData = false;
        }
        if (!Arr::has($this->data, 'decision')) {
            $this->data['decision'] = null;
        }
        if (!Arr::has($this->data, 'members')) {
            $this->data['members'] = [];
        }
    }

    protected function updateDecision()
    {
        $available_count = collect($this->data['members'])->filter(function($value) {
            return $value === true;
        })->count();

        $new_value = null;

        $configuration = $this->configuration();
        // Valeur par défaut haute pour que les événements non reconnus ne soient jamais annoncés comme acceptés
        $minimum_required = Arr::get($configuration, 'min_members', 9999);
        $must_decide = false;

        if (Arr::has($configuration, 'decision_time')) {
            $time = Carbon::parse($this->event->getStart()->getDateTime());

            $decide_before = $time->modify(Arr::get($configuration, 'decision_time'));

            $must_decide = Carbon::now()->gt($decide_before);
        }

        if ($available_count >= $minimum_required) {
            $new_value = true;
        } else if ($must_decide) {
            $new_value = false;
        } else {
            $new_value = null;
        }

        if ($this->data['decision'] !== $new_value) {
            $this->decisionChanged = true;
            $this->dirty = true;
        }

        $this->data['decision'] = $new_value;
    }

    protected function configuration()
    {
        foreach (config('ateliers.events') as $key => $settings) {
            // La configuration est choisie si le titre de l'événement contient sa valeur "name".
            // La comparaison est effectuée sans tenir compte de la casse
            if (str_contains(mb_strtolower($this->event->getSummary()), mb_strtolower(Arr::get($settings, 'name')))) {
                return $settings;
            }
        }

        return [];
    }

    public function setMemberAvailability($memberId, $value)
    {
        if (!array_key_exists($memberId, $this->data['members']) || $this->data['members'][$memberId] !== $value) {
            $this->dirty = true;
        }

        $this->data['members'][$memberId] = $value;

        $this->updateDecision();
    }

    public function isDirty()
    {
        return $this->dirty;
    }

    public function decisionChanged()
    {
        return $this->decisionChanged;
    }

    public function getData()
    {
        return $this->data;
    }

    public function save()
    {
        $description = $this->event->getDescription();

        $data = json_encode($this->data, JSON_PRETTY_PRINT);

        $currentDescriptionAfterData = $description;

        if ($this->hasData) {
            $parts = explode(self::DATA_DELIMITER, $description, 2);

            $currentDescriptionAfterData = count($parts) > 1 ? $parts[1] : '';
        }

        $this->event->setDescription($data . "\n" . self::DATA_DELIMITER . "\n" . $currentDescriptionAfterData);

        $summary_parts = explode(']', $this->event->getSummary(), 2);

        $decision_message = 'Proposition';

        if ($this->data['decision'] === true) {
            $decision_message = '👍';
        } else if ($this->data['decision'] === false) {
            $decision_message = 'Annulé';
        }

        $new_summary = '[' . $decision_message . '] ' . trim($summary_parts[count($summary_parts) - 1]);

        $this->event->setSummary($new_summary);
    }
}
