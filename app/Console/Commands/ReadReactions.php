<?php

namespace App\Console\Commands;

use App\DiscordApi;
use App\EventStateManager;
use App\MessageFormatter;
use App\Repositories\EventRepository;
use App\Repositories\MemberRepository;
use App\Repositories\MessageRepository;
use Illuminate\Console\Command;

class ReadReactions extends Command
{
    protected $signature = 'presences:readreactions';

    protected $description = 'Analyse les réactions et met à jour les messages et événements';

    protected $events;
    protected $messages;
    protected $discord;
    protected $members;

    public function __construct(EventRepository $events, MessageRepository $messages, DiscordApi $discord, MemberRepository $members)
    {
        $this->events = $events;
        $this->messages = $messages;
        $this->discord = $discord;
        $this->members = $members;

        parent::__construct();
    }

    public function handle()
    {
        $calendarId = config('ateliers.calendar_id');
        $events = $this->events->index($calendarId);

        if (!count($events)) {
            $this->info('Aucun événement trouvé');
            return; // Pas besoin d'envoyer quoi que ce soit
        } else {
            $this->info(count($events) . ' événements trouvés');
        }

        $channelId = config('ateliers.discord_room_id');

        $members = $this->members->index()->pluck('id');

        foreach ($events as $event) {
            /** @var \Google_Service_Calendar_Event $event */
            $this->info('Traitement de l\'événement ' . $event->getId());

            $messageId = $this->messages->messageForEvent($event->getId());

            if (!$messageId) {
                $this->info('Pas de message Discord');
                continue;
            }

            $manager = new EventStateManager($event);

            sleep(1);
            $available_reactions = $this->discord->getChannelMessageReactions($channelId, $messageId, MessageFormatter::EMOJI_AVAILABLE);
            foreach ($available_reactions as $user) {
                if ($members->contains($user->id)) {
                    $manager->setMemberAvailability($user->id, true);
                }
            }

            sleep(1);
            $unavailable_reactions = $this->discord->getChannelMessageReactions($channelId, $messageId, MessageFormatter::EMOJI_UNAVAILABLE);
            foreach ($unavailable_reactions as $user) {
                if ($members->contains($user->id)) {
                    $manager->setMemberAvailability($user->id, false);
                }
            }

            sleep(1);
            $unknown_reactions = $this->discord->getChannelMessageReactions($channelId, $messageId, MessageFormatter::EMOJI_UNKNOWN);
            foreach ($unknown_reactions as $user) {
                if ($members->contains($user->id)) {
                    $manager->setMemberAvailability($user->id, null);
                }
            }

            if ($manager->isDirty()) {
                $this->info('Changements détectés');
                $this->info(json_encode($manager->getData(), JSON_PRETTY_PRINT));
                $manager->save();

                $this->events->update($calendarId, $event);

                $this->info('Calendrier mis à jour');
            } else {
                $this->info('Pas de changement d\'état');
            }

            $formatter = new MessageFormatter($event);
            $this->discord->editChannelMessage($channelId, $messageId, [
                'embed' => $formatter->embed(),
            ]);

            if ($manager->decisionChanged()) {
                $this->info('La décision a changé, nouveau message');

                $this->discord->createChannelMessage($channelId, [
                    'content' => $formatter->happeningMessage(),
                ]);
            }

            $this->info('Message Discord ' . $messageId . ' traité');
        }

        $this->info('Tous les messages traités');
    }
}
