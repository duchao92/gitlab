<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Ramsey\Uuid\Uuid;

use App\Rules\Mobilephone;
use App\Mail\BindingEmail;
use App\Models\User;
use App\Models\SmsCode;
use App\Models\EmailCode;
use App\Models\ExtraSet;
use App\Models\LoginLog;
use App\Models\ProfileLog;
use App\Models\OpenPlatform;
use App\Models\Message;
use App\Models\MessageSend;

use App\Notifications\ChangePassword;

use Carbon\Carbon;

class ProfileController extends Controller
{
    /**
     * 获取个人信息
     *
     * @param Request $request
     * @return void
     */
    public function getBasic(Request $request)
    {
        $currentUser = $request->user();

        $result = [
            'id' => $currentUser->id,
            'realname' => $currentUser->realname,
            'phone' => $currentUser->phone,
            'email' => $currentUser->email,
            'wechat_bind' => false,
            'organization' => $currentUser->organization ? $currentUser->organization->name : '',
            'phone' => $currentUser->phone,
            'actived_at' => $currentUser->actived_at,
            'lastlogin_at' => '',
            'notify' => [
                'email' => 1,
                'sms' => 1,
                'wechat' => 1
            ]
        ];

        $loginRecord = LoginLog::where('uid', $currentUser->id)
            ->select('created_at')
            ->orderBy('created_at', 'desc')
            ->first()
            ->toArray();

        if (!empty($loginRecord)) {
            $result['lastlogin_at'] = $loginRecord['created_at'];
        }

        $extraSettings = ExtraSet::where('uid', $currentUser->id)->first();
        if ($extraSettings) {
            $result['notify']['sms'] = $extraSettings->sms_notice;
            $result['notify']['email'] = $extraSettings->email_notice;
            $result['notify']['wechat'] = $extraSettings->wechat_notice;
            if ($extraSettings['wechat_bind'] === 2) {
                $wechat = OpenPlatform::where('platform', 'wechat')
                    ->where('uid', $currentUser->id)
                    ->select('nickname')
                    ->first();
                if (!!$wechat) {
                    $result['wechat_bind'] = $wechat->nickname;
                }
            }
        }

        return $this->success('获取成功', $result);
    }

    /**
     * 设置个人基本信息
     *
     * @param Request $request
     * @return void
     */
    public function setBasic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'realname' => [
                'bail',
                'regex:/^[\x7f-\xff]+$/'
            ],
            'organization' => 'bail|integer'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            if ($errors->has('realname')) {
                return $this->error(405, "姓名只能为汉字");
            }
            return $this->error(405, '单位信息错误');
        }

        $user = $request->user();
        if ($request->has('realname')) {
            $logs = ProfileLog::where('uid', $request->user()->id)
                ->where('action', 0)
                ->where('created_at', '>=', Carbon::now()->subYears(1)->toDateTimeString())
                ->first();
            if ($logs) {
                return $this->error(405, '姓名一年内仅支持修改一次，请勿重复操作');
            }
            $user->realname = $request->input('realname');
        }

        if ($request->has('organization')) {
            $user->organization_id = $request->input('organization');
        }

        $user->save();

        return $this->success('修改成功');
    }

    /**
     * 绑定用户手机号
     *
     * @param Request $request
     * @return void
     */
    public function bindingPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => [ 'bail', 'required', new Mobilephone ],
            'code' => [ 'bail', 'required', 'regex:/^\d{6}$/' ]
        ]);
        if ($validator->fails()) {
            return $this->error(405, '手机号或验证码错误');
        }
        
        $user = $request->user();

        if ($user->phone === $request->input('phone')) {
            return $this->error(405, '当前手机号已绑定');
        }

        $code = SmsCode::where('phone', $request->input('phone'))
            ->where('code', $request->input('code'))
            ->where('type', 3)
            ->where('status', 1)
            ->where('expire_in', '>', Carbon::now()->toDateTimeString())
            ->first();
        
        if (!$code) {
            return $this->error(405, '验证码错误');
        }

        $code->status = 2;
        $code->save();

        $isExist = User::where('phone', $request->input('phone'))->count();
        if ($isExist > 0) {
            return $this->error(405, '手机号已绑定其他账户，请先解绑');
        }

        $user->phone = $request->input('phone');
        $user->save();


        return $this->success('绑定成功');
    }

    /**
     * 发送绑定邮箱邮件
     *
     * @param Request $request
     * @return void
     */
    public function sendEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'bail|required|email'
        ]);
        if ($validator->fails()) {
            return $this->error(405, '邮箱格式错误');
        }

        if ($request->user()->email === $request->input('email')) {
            return $this->error(405, '该邮箱已绑定，无需修改');
        }

        $user = User::where('email', $request->input('email'))->select('id')->first();
        if ($user) {
            return $this->error(405, '邮箱已绑定其他账户');
        }

        $code = (Uuid::uuid4())->toString();

        $message = (new BindingEmail(['url' => url('/#/binding/email?code='.$code)]))
            ->onConnection('redis')
            ->onQueue('emails');
        Mail::to($request->input('email'))->queue($message);

        $emailCode = new EmailCode;
        $emailCode->email = $request->input('email');
        $emailCode->uid = $request->user()->id;
        $emailCode->code = $code;
        $emailCode->expire_in = Carbon::now()->addHours(24)->toDateTimeString();
        $emailCode->type = 3;
        $emailCode->status = 1;
        $emailCode->save();

        return $this->success('邮件发送成功');
    }

    /**
     * 设置提醒方式
     *
     * @param Request $request
     * @return void
     */
    public function setNotify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wechat' => [ 'bail', 'required', Rule::in([1, 2]) ],
            'sms' => [ 'bail', 'required', Rule::in([1, 2]) ],
            'email' => [ 'bail', 'required', Rule::in([1, 2]) ],
            'web' => [ 'bail', 'required', Rule::in([1, 2]) ]
        ]);
        if ($validator->fails()) {
            return $this->error(405, '参数错误');
        }

        $settings = ExtraSet::where('uid', $request->user()->id)->first();
        if (!$settings) {
            $settings = new ExtraSet;
            $settings->uid = $request->user()->id;
        }

        $settings->wechat_notice = $request->input('wechat');
        $settings->sms_notice = $request->input('sms');
        $settings->email_notice = $request->input('email');
        $settings->web_notice = $request->input('web');
        $settings->save();

        return $this->success('设置成功');
    }

    /**
     * 重置密码
     *
     * @param Request $request
     * @return void
     */
    public function resetPwd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => [
                'bail',
                'required',
                'regex:/^[a-zA-Z0-9_!\@\#\$\%\^\&\*\(\)\-]{6,64}$/'
            ],
            'password' => [
                'bail',
                'required',
                'regex:/^[a-zA-Z0-9_!\@\#\$\%\^\&\*\(\)\-]{6,64}$/',
                'confirmed'
            ]
        ]);
        if ($validator->fails()) {
            return $this->error(405, '密码填写格式错误');
        }

        $user = $request->user();
        if (!Hash::check($request->input('old_password'), $user->password)) {
            return $this->error(405, '原始密码错误');
        }

        $user->password = Hash::make($request->input('password'));
        $user->save();

        $extraSettings = ExtraSet::where('uid', $user->id)->first();
        $channels = [];
        if ($extraSettings) {
            if ($extraSettings->sms_notice === 2) {
                $channels[] = 'sms';
            }
            if ($extraSettings->email_notice === 2) {
                $channels[] = 'mail';
            }
            if ($extraSettings->web_notice === 2) {
                $channels[] = 'web';
            }
        }

        if (count($channels) > 0) {
            $user->notify(new ChangePassword(['time' => date('Y-m-d H:i:s')], $channels));
        }

        return $this->success('修改密码成功');
    }
}
