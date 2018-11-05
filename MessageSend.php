<?php

namespace App\Models;

use Laravel\Scout\Searchable;

use Illuminate\Database\Eloquent\Model;

class MessageSend extends Model
{
    use Searchable;

    protected $table = 'message_send';

    //定义索引里面的type
    public function searchableAs()
    {
        return "message_send";
    }

    //定义有哪些字段需要搜索
    public function toSearchableArray()
    {
        return [
            'message_id' => $this->message_id,
            'receiver_id' => $this->receiver_id,
            'status' => $this->status,
        ];
    }

    public function message()
    {
        return $this->belongsTo('App\Models\Message', 'message_id', 'message_id');
    }
}
