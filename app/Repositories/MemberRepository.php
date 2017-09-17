<?php

namespace App\Repositories;

use App\DiscordApi;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;

class MemberRepository
{
    protected $discord;
    protected $cache;

    public function __construct(DiscordApi $discord, Repository $cache)
    {
        $this->discord = $discord;
        $this->cache = $cache;
    }

    /**
     * @return Collection
     */
    public function index()
    {
        return $this->cache->remember('members', 60, function () {
            $allUsers = $this->discord->getGuildMembers(config('ateliers.discord_guild_id'));

            $members = new Collection();

            foreach ($allUsers as $user) {
                if (
                    property_exists($user, 'roles') &&
                    is_array($user->roles) &&
                    in_array(config('ateliers.discord_members_role_id'), $user->roles)
                ) {
                    $member = new \stdClass();
                    $member->id = $user->user->id;
                    $member->name = property_exists($user, 'nick') ? $user->nick : $user->user->username;

                    $members->push($member);
                }
            }

            return $members->sortBy('name');
        });
    }
}
