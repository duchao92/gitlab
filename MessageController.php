<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

use App\Models\Message;
use App\Models\MessageSend;
use App\Models\MessageLog;

class MessageController extends Controller
{
    /**
     * 获取具体消息
     * 
     * @param integer $id
     * @param Request $request
     * @return void
     */
    public function list(Request $request)
    {
        $pageNum = $request->input('page', 1);
        $pageSize = $request->input('size', 20);
        $type = $request->input('type', null);
        $status = $request->input('status', 0);
        $userId = $request->user()->id;

        // 根据状态 选择
        switch ($status) {
            case '0':
                $redisZsetName = 'message:all';
                break;
            case '1':
                $redisZsetName = 'message:unread';
                break;
            case '2':
                $redisZsetName = 'message:read';
                break;
            default:
                return 'error';
                break;
        }

        // 根据消息类型 选择
        if ($type && $type == 1) {
            $redisZsetName = $redisZsetName.'System';
        } elseif ($type && $type == 2) {
            $redisZsetName = $redisZsetName.'Operation';
        }

        $userZsetInfo = $redisZsetName.':'.$userId;

        // 判断Redis 中是否存在数据
        if (!Redis::exists($userZsetInfo)) {
            
            // 从Es搜索库 获取数据 插入Redis
            $conditions = [ [ 'receiver_id', '=', $userId] ];
            if ($status != 0) {
                $conditions[] = [ 'status', '=', $status ];
            }

            $messages = MessageSend::where($conditions)
                ->with('message')
                ->get();

            $messageList = [];
            foreach ($messages as $message) {
                if ($message->message->type == $type) {
                    $messageList[] = [
                        'message_id' => $message->message_id,
                        'receiver_id' => $message->receiver_id,
                        'status' => $message->status,
                        'created_at' => strtotime($message->created_at->toDateTimeString()),
                        'title' => $message->message->title,
                        'content' => $message->message->content,
                        'type' => $message->message->type,
                        'sender_id' => $message->message->sender_id,
                    ];
                }
                
            }

            foreach ($messageList as $message) {

                // 有序集合 按时间排序 
                Redis::zadd($userZsetInfo, $message['created_at'], 'message:list:'.$userId.':'.$message['message_id']);
                Redis::hmset('message:list:'.$userId.':'.$message['message_id'], $message);
            }
            unset($messageList);
        }

        $messages = Redis::zrange($userZsetInfo, ($pageNum - 1) * $pageSize, ($pageNum * $pageSize) - 1);

        foreach ($messages as $message) {
            $messageList[] = Redis::hgetall($message);
        }

        return $this->success('获取成功', [
            'total' => 1,
            'list' => $messageList
        ]);
    }

    /**
     * 获取具体消息
     * 
     * @param integer $id
     * @param Request $request
     * @return void
     */
    public function details($id, Request $request)
    {
        $userId = $request->user()->id;

        if(!Redis::exists('message:list:'.$userId.':'.$id)){
            return $this->error(405, '没有该消息');
        }

        $messageInfo = Redis::hgetall('message:list:'.$userId.':'.$id);

        if (!$messageInfo) {
            return $this->error(405, '没有该消息');
        }

        if ($messageInfo['type'] == 1) {

            DB::beginTransaction();

            // 修改mysql数据库状态
            $result = MessageSend::where('message_id', $messageInfo['message_id'])->update([ 'status' => 2 ]);

            if (!$result) {
                DB::rollback();
                return $this->error(500, '修改状态失败，请重试');
            }

            // 删除redis 有序集合 hash数据
            $this->delRedisAZset();
            Redis::del('message:list:'.$userId.':'.$id);

            DB::commit();
        }

        // 转化为对象
        $message = new Message;
        $message->message_id = $messageInfo['message_id'];
        $message->title = $messageInfo['title'];
        $message->content = $messageInfo['content'];
        $message->created_at = $messageInfo['created_at'];

        return $this->success('导出成功', $message);
    }

    /**
     * 删除Redis 有序集合
     * 
     * @param integer $id
     * @return void
     */
    public function delRedisAZset()
    {
        // $userId = $request->user()->id;
        $userId = 22;
        Redis::del('message:all:'.$userId);
        Redis::del('message:allSystem:'.$userId);
        Redis::del('message:allOperation:'.$userId);

        Redis::del('message:unread:'.$userId);
        Redis::del('message:unreadSystem:'.$userId);
        Redis::del('message:unreadOperation:'.$userId);

        Redis::del('message:read:'.$userId);
        Redis::del('message:readSystem:'.$userId);
        Redis::del('message:readOperation:'.$userId);
    }

    /**
     * 标记已读（全部和部分）
     * 
     * @param integer $id
     * @return void
     */
     public function edit(Request $request)
    {
        $ids = $request->input('ids', null);
        $type = $request->input('type');

        // $userId = $request->user()->id;
        $userId = 22;

        if ($type != 1) {
            $conditions = [ [ 'receiver_id', '=', $userId], [ 'status', '=', 1] ];

            $ids = MessageSend::where($conditions)->pluck('message_id');
        } 

        DB::beginTransaction();

        // 修改数据库 状态
        $result = MessageSend::whereIn('message_id', $ids)->update([ 'status' => 2 ]);
        if (!$result) {
            DB::rollback();
            return $this->error(500, '标记失败');
        }

        // 删除Redis 消息数据
        $this->delRedisAZset();
        foreach ($ids as $id) {
            Redis::del('message:list:'.$userId.':'.$id);
        }

        DB::commit();

        return $this->success('标记成功');
    }
}