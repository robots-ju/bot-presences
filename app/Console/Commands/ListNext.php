<?php

namespace App\Console\Commands;

use App\DiscordApi;
use App\MessageFormatter;
use App\Repositories\EventRepository;
use App\Repositories\MessageRepository;
use Illuminate\Console\Command;

class ListNext extends Command
{
    protected $signature = 'presences:listnext';

    protected $description = 'Liste les prochains événements sur Discord';

    protected $events;
    protected $messages;
    protected $discord;

    public function __construct(EventRepository $events, MessageRepository $messages, DiscordApi $discord)
    {
        $this->events = $events;
        $this->messages = $messages;
        $this->discord = $discord;

        parent::__construct();
    }

    public function handle()
    {
        $events = $this->events->index(config('ateliers.calendar_id'));

        if (!count($events)) {
            $this->info('Aucun événement trouvé');
            return; // Pas besoin d'envoyer quoi que ce soit
        } else {
            $this->info(count($events) . ' événements trouvés');
        }

        $channelId = config('ateliers.discord_room_id');

        $this->discord->createChannelMessage($channelId, [
            'content' => 'Ateliers à venir',
        ]);

        foreach ($events as $event) {
            /** @var \Google_Service_Calendar_Event $event */
            $formatter = new MessageFormatter($event);

            sleep(1);
            $message = $this->discord->createChannelMessage($channelId, [
                'embed' => $formatter->embed(),
            ]);

            $this->messages->updateMessageForEvent($event->getId(), $message->id);

            sleep(1);
            $this->discord->createChannelMessageReaction($channelId, $message->id, '👍');
            sleep(1);
            $this->discord->createChannelMessageReaction($channelId, $message->id, '👎');
            sleep(1);
            $this->discord->createChannelMessageReaction($channelId, $message->id, '❓');

            $this->info('Message ' . $message->id . ' envoyé via Discord');
        }

        $this->info('Tous les messages envoyés');
    }
}
