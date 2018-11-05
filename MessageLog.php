<?php

namespace App\Models;

use Laravel\Scout\Searchable;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use Searchable;

    //定义索引里面的type
    public function searchableAs()
    {
        return "message_log";
    }

    //定义有哪些字段需要搜索
    public function toSearchableArray()
    {
        return [
            'receiver_id' => $this->receiver_id,
            'status' => $this->status,
            'content' => $this->content,
            'type' => $this->type,
            'sender_id' => $this->sender_id,
        ];
    }
}