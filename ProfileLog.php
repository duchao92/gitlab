<?php

namespace App\Http\Middleware;

use Closure;

use App\Models\ProfileLog as Log;
use APp\Models\Organization;

class ProfileLog
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->user()) {
            $user = $request->user();
            $currentUser = [
                'id' => $user->id,
                'realname' => $user->realname,
                'email' => $user->email,
                'organization' => $user->organization ? $user->organization->name : '',
                'phone' => $user->phone,
            ];
        }

        $response = $next($request);

        if ($response->original['code'] === 200) {
            $path = $request->path();
            $log = new Log;
            $log->conductor_id = $currentUser['id'];
            $log->uid = $request->input('uid', $currentUser['id']);
            $log->ip = $request->getClientIp();
            $detail = '';
            $action = -1;
            switch ($path) {
                case 'api/user/basic':
                    if ($request->input('realname')) {
                        $detail .= $currentUser['realname'] ? '姓名由'.$currentUser['realname'].'修改为'.$request->input('realname') : '姓名设置为'.$request->input('realname');
                        $action = 0;
                    }
                    if ($request->input('organization')) {
                        $organization = Organization::find($request->input('organization'));
                        $detail .= $currentUser['organization'] ? '所属单位由'.$currentUser['organization'].'修改为'.$organization->name : '所属单位设置为'.$organization->name;
                        $action = 1;
                    }
                    break;
                case 'api/user/binding/phone':
                    $detail .= $currentUser['phone'] ? '手机号由'.$currentUser['phone'].'修改为'.$request->input('phone') : '手机号设置为'.$request->input('phone');
                    $action = 2;
                    break;
                case 'api/user/notify':
                    if ($request->input('wechat') == 1) {
                        $detail .= '关闭微信提醒  ';
                    } else {
                        $detail .= '开启微信提醒  ';
                    }
                    if ($request->input('sms') == 1) {
                        $detail .= '关闭短信提醒  ';
                    } else {
                        $detail .= '开启短信提醒  ';
                    }
                    if ($request->input('email') == 1) {
                        $detail .= '关闭邮件提醒';
                    } else {
                        $detail .= '开启邮件提醒';
                    }
                    if ($request->input('web') == 1) {
                        $detail .= '关闭站内信提醒';
                    } else {
                        $detail .= '开启站内信提醒';
                    }
                    $action = 3;
                    break;
                case 'api/user/resetpwd':
                    $detail .= '重置密码';
                    $action = 4;
                    break;
                default:
                    break;
            }
            $log->detail = $detail;
            $log->action = $action;

            $log->save();

        }

        return $response;
    }
}
