<?php

namespace App\Console\Commands;

use App\DiscordApi;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'test';

    protected $description = 'Command description';

    protected $api;

    public function __construct(DiscordApi $api)
    {
        $this->api = $api;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $channelId = 358608998838304769;

        $message = $this->api->createChannelMessage($channelId, [
            'content' => 'Test présences',
            'embed' => [
                'title' => 'Membres',
                'color' => 4688419,
                'fields' => [
                    [
                        'name' => 'Présent',
                        'value' => "Clark\nOrélian",
                        'inline' => true,
                    ],
                    [
                        'name' => 'Absent 👎',
                        'value' => 'Loïc',
                        'inline' => true,
                    ],
                    [
                        'name' => 'Sans réponse',
                        'value' => 'Toto',
                        'inline' => true,
                    ],
                ],
                'footer' => [
                    'text' => 'Bas du message',
                ],
            ],
        ]);

        sleep(1);
        $this->api->createChannelMessageReaction($channelId, $message->id, '👍');
        sleep(1);
        $this->api->createChannelMessageReaction($channelId, $message->id, '👎');
        sleep(1);
        $this->api->createChannelMessageReaction($channelId, $message->id, '❓');
    }
}
