<?php

namespace App;

use App\Repositories\MemberRepository;
use Carbon\Carbon;
use Google_Service_Calendar_Event;
use Illuminate\Support\Arr;

class MessageFormatter
{
    protected $event;
    protected $members;
    protected $data;

    const TAKES_PLACE = 'y';
    const CANCELLED = 'n';
    const NO_ANSWER = '?';

    const EMOJI_AVAILABLE = '👍';
    const EMOJI_UNAVAILABLE = '👎';
    const EMOJI_UNKNOWN = '❓';

    public function __construct(Google_Service_Calendar_Event $event)
    {
        $this->event = $event;
        $this->members = app(MemberRepository::class);

        $this->state = new EventStateManager($event);
    }

    protected function getData()
    {
        if ($this->data) {
            return $this->data;
        }

        return new \stdClass();
    }

    public function getStart()
    {
        return Carbon::parse($this->event->getStart()->getDateTime());
    }

    public function getEnd()
    {
        return Carbon::parse($this->event->getEnd()->getDateTime());
    }

    public function getTitle()
    {
        return $this->getStart()->format('l Y/m/d H:i') . '-' . $this->getEnd()->format('H:i') . ' : ' . $this->event->getSummary();
    }

    public function getDecision()
    {
        $decision = $this->state->getData()['decision'];

        if ($decision === true) {
            return self::TAKES_PLACE;
        } else if ($decision === false) {
            return self::CANCELLED;
        }

        return self::NO_ANSWER;
    }

    public function getColor()
    {
        switch ($this->getDecision()) {
            case self::TAKES_PLACE:
                return 0x478A23;
            case self::CANCELLED:
                return 0xCC2229;
            case self::NO_ANSWER:
                return 0xE7AA30;
        }

        return 0;
    }

    public function getAnswers()
    {
        $members = $this->members->index();
        $answers = Arr::get($this->state->getData(), 'members');

        $return = [
            'available' => [],
            'unavailable' => [],
            'unknown' => [],
        ];

        foreach ($members as $member) {
            $answer = Arr::get($answers, $member->id);

            if ($answer === true) {
                $return['available'][] = $member;
            } else if ($answer === false) {
                $return['unavailable'][] = $member;
            } else {
                $return['unknown'][] = $member;
            }
        }

        return $return;
    }

    public function embed()
    {
        $fields = [];

        foreach ($this->getAnswers() as $answer => $members) {
            $title = 'Sans réponse';

            switch ($answer) {
                case 'available':
                    $title = 'Présent';
                    break;
                case 'unavailable':
                    $title = 'Absent';
                    break;
            }

            $members_value = collect($members)->map(function ($member) {
                return $member->name;
            })->implode("\n");

            $fields[] = [
                'name' => $title,
                'value' => $members_value ? $members_value : '?',
                'inline' => true,
            ];
        }

        $decision_text = 'en discussion';

        switch ($this->getDecision()) {
            case self::TAKES_PLACE:
                $decision_text = 'a lieu';
                break;
            case self::CANCELLED:
                $decision_text = 'n\'a pas lieu';
                break;
        }

        return [
            'title' => $this->getTitle(),
            'color' => $this->getColor(),
            'fields' => $fields,
            'footer' => [
                'text' => 'Décision: ' . $decision_text . ', mis à jour le ' . Carbon::now()->format('Y-m-d H:i'),
            ],
        ];
    }

    public function happeningMessage()
    {
        $message = 'est en discussion';

        switch ($this->getDecision()) {
            case self::TAKES_PLACE:
                $message = 'a lieu';
                break;
            case self::CANCELLED:
                $message = 'n\'a pas lieu';
                break;
        }

        return 'L\'événement du ' . $this->getStart()->format('l Y-m-d H:i') . ' (' . $this->getStart()->diffForHumans() . ') ' . $message;
    }
}
