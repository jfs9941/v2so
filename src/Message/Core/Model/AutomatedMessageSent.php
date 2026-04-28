<?php

namespace Module\Message\Core\Model;

use Illuminate\Database\Eloquent\Model;


class AutomatedMessageSent extends Model
{
    public $incrementing = false;

    protected $table = 'automated_message_sent';

    protected $fillable = [
        'receiver_id',
        'sender_id',
        'sent_at',
        'id'
    ];
}