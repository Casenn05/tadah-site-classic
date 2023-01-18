<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'register_ip',
        'last_ip',
        'verified_hoster',
        'scribbler',
        'old_cores',
        'booster',
        'invite_key',
        'discord_id',
        'last_online',
        'added_servers',
        'qa',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'email',
        'password',
        'remember_token',
        'register_ip',
        'last_ip',
        'invite_key',
        'discord_id'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        //'email_verified_at' => 'datetime',
        'added_servers' => AsCollection::class,
    ];

    protected $dates = [
        'joined',
        'last_online'
    ];

    public function servers()
    {
        return $this->hasMany('App\Models\Server', 'creator');
    }

    public function threads()
    {
        return $this->hasMany('App\Models\ForumThread', 'user_id');
    }

    public function posts()
    {
        return $this->hasMany('App\Models\ForumPost', 'user_id');
    }

    public function friends()
    {
        $friends = \App\Models\Friendship::where(function($query) {
            $query->where(['receiver_id' => $this->id]);
            $query->orWhere(['requester_id' => $this->id]);
        })->where('status', '1')->get();

        return $friends;
    }

    public function friendRequests()
    {
        return \App\Models\Friendship::where('status', '0')
            ->where('receiver_id', '=', $this->id)
            ->get();
    }

    public function discordLinked()
    {
        if ($this->discord_id) {
            return true;
        }

        return false;
    }

    public function isVerifiedHoster()
    {
        return false;
    }

    public function isAdmin()
    {
        return $this->admin == 1;
    }

    public function isModerator()
    {
        return $this->admin == 2;
    }

    public function isEventStaff()
    {
        return false;
    }

    public function isStaff()
    {
        // we are not giving event staff perms
        return ($this->admin == 1 || $this->admin == 2);
    }
}
