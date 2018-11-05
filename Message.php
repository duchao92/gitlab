<?php

namespace App\Models;

use Laravel\Scout\Searchable;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use Searchable;

    //定义索引里面的type
    public function searchableAs()
    {
        return "message";
    }

    //定义有哪些字段需要搜索
    public function toSearchableArray()
    {
        return [
            'message_id' => $this->message_id,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'sender_id' => $this->sender_id,
        ];
    }
}
