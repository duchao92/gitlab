<?php

namespace App\Http\Controllers;

use Heimuya\Captcha\Facades\Captcha;
use Overtrue\EasySms\EasySms;
use App\Rules\Mobilephone;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

use App\Models\User;
use App\Models\SmsCode;
use App\Models\EmailCode;
use App\Models\Module;
use App\Models\Field;
use App\Models\Permission;
use App\Models\ProjectModule;
use App\Models\Project;
use App\Models\BlindsTemplate;
use App\Models\DrugOrder;
use App\Models\DrugOrderDetail;
use App\Models\ProfileLog as Log;

use Validator;
use PDF;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redis;

class CommonController extends Controller
{
    /**
     * 获取图片验证码
     *
     * @return Illuminate\Http\Response
     */
    public function getCode()
    {
        return $this->success('获取成功', [ 'code' => Captcha::create('flat')->encode('data-url')->encoded ]);
    }

    /**
     * 发送短信验证码
     *
     * @param Request $request
     * @return void
     */
    public function sendSms(Request $request)
    {
        $validator  = Validator::make($request->all(), [
            'phone' => [
                'bail',
                'required',
                new Mobilephone
            ],
            'type' => [
                'bail',
                'required',
                Rule::in([1, 2, 3])
            ]
        ]);

        if ($validator->fails()) {
            return $this->error(405, '手机号输入错误');
        }

        $now = Carbon::now();

        $alreadySend = SmsCode::where([
            ['phone', '=', $request->input('phone')],
            ['expire_in', '>', $now->toDateTimeString()],
            ['status', '=', 1],
            ['type', '=', $request->input('type', 1)]
        ])->select('code', 'created_at', 'times')->first();

        $smsTemplate = '';
        switch ($request->input('type')) {
            case 1:
                $smsTemplate = config('sms.templates.register');
                break;
            case 2:
                $smsTemplate = config('sms.templates.forget_password');
                break;
            case 3:
                $smsTemplate = config('sms.templates.bind_phone');
                break;
            default:
                break;
        }
        
        if (!!$alreadySend) {
            if ($now->subMinutes(1)->lt(Carbon::createFromFormat('Y-m-d H:i:s', $alreadySend->created_at)) || $alreadySend->times >= 3) {
                return $this->error(405, '请勿频繁发送验证码');
            }
            
            if ($this->send($request->input('phone'), [ 'code' => $alreadySend->code], $smsTemplate)) {
                $alreadySend->updated_at = $now->toDateTimeString();
                $alreadySend->times = $alreadySend->times + 1;
                $alreadySend->save();
            } else {
                return $this->error(405, '验证码发送失败');
            }
        } else {
            $code = generateCode();
            if ($this->send($request->input('phone'), [ 'code' => $code], $smsTemplate)) {
                $smsCode = new SmsCode;
                $smsCode->code = $code;
                $smsCode->type = $request->input('type');
                $smsCode->phone = $request->input('phone');
                $smsCode->expire_in = $now->addMinutes(5)->toDateTimeString();
                $smsCode->status = 1;
                $smsCode->save();
            } else {
                return $this->error(405, '验证码发送失败');
            }
        }

        return $this->success('验证码发送成功');
    }

    /**
     * 获取系统模块
     *
     * @param Request $request
     * @return void
     */
    public function getModules(Request $request)
    {
        $conditions = [];
        if ($request->input('keywords')) {
            $condition[] = [
                'name',
                'like',
                '%'.$request->input('keywords').'%'
            ];
        }

        $modules = Module::where($conditions)->select('id', 'name', 'description', 'required', 'display_name')->orderBy('sequence', 'asc')->get();
        return $this->success('获取成功', $modules->toArray());
    }

    /**
     * 获取研究领域
     *
     * @param Request $request
     * @return void
     */
    public function getFields(Request $request)
    {
        $conditions = [
            ['status', '=', 1]
        ];
        if ($request->input('keywords')) {
            $condition[] = [
                'name',
                'like',
                '%'.$request->input('keywords').'%'
            ];
        }

        $fields = Field::where($conditions)->select('id', 'name')->orderBy('created_at', 'desc')->get();
        return $this->success('获取成功', $fields->toArray());
    }

    /**
     * 获取权限列表
     *
     * @return void
     */
    public function getPermissions($id = null)
    {
        $permissionObj = Permission::select('id', 'name', 'display_name', 'description', 'parent_id');

        if ($id) {
            $project = Project::find($id);
            if (!$project) {
                return $this->error(405, '参数错误');
            }

            $modules = ProjectModule::where('project_id', $id)->pluck('module_id');
            $modules[] = 0;

            $permissionObj->whereIn('module_id', $modules);
        }
        $permissions = $permissionObj->orderBy('created_at', 'asc')
            ->get()
            ->mapWithKeys(function ($item) {
                return [ $item['id'] => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'display_name' => $item['display_name'],
                    'description' => $item['description'],
                    'parent_id' => $item['parent_id']
                ]];
            })->toArray();

        $result = [];
        foreach ($permissions as $permission) {
            if (isset($permissions[$permission['parent_id']]) && ! empty($permissions[$permission['parent_id']])) {
                // 子分类
                $permissions[$permission['parent_id']]['children'][] = &$permissions[$permission['id']];
            } else {
                // 一级分类
                $result[] = &$permissions[$permission['id']];
            }
        }
        
        return $this->success('获取成功', $result);
    }

    /**
     * 下载受试者盲底模板文件
     *
     * @param Request $request
     * @return void
     */
    public function getRecordTemplate(Request $request)
    {
        $template = BlindsTemplate::where('status', 2)->where('type', 1)->first();
        if (!$template) {
            return $this->error(500, '暂无有效的模板文件');
        }

        return response()->download(public_path().'/storage/'.$template->path);
    }

    /**
     * 下载药物盲底模板文件
     *
     * @param Request $request
     * @return void
     */
    public function getDrugTemplate(Request $request)
    {
        $template = BlindsTemplate::where('status', 2)->where('type', 2)->first();
        if (!$template) {
            return $this->error(500, '暂无有效的模板文件');
        }

        return response()->download(public_path().'/storage/'.$template->path);
    }

    /**
     * 绑定邮箱
     *
     * @param Request $request
     * @return void
     */
    public function bindingEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'bail|required|alpha_dash'
        ]);
        if ($validator->fails()) {
            return $this->error(405, '信息错误');
        }

        $code = EmailCode::where('code', $request->input('code'))
            ->where('type', 3)
            ->first();
        if (!$code) {
            return $this->error(405, '邮箱验证码错误');
        }

        if ($code->status === 2 || Carbon::now()->gt(Carbon::createFromFormat('Y-m-d H:i:s', $code->expire_in))) {
            return $this->error(405, '验证码已过期');
        }

        $code->status = 2;
        $code->save();

        $detail = '';
        $user = User::find($code->uid);
        $detail .= $user->email ? '邮箱由'.$user->email.'修改为'.$code->email : '邮箱设置为'.$code->email;
        $user->email = $code->email;
        $user->save();

        $log = new Log;
        $log->conductor_id = $code->uid;
        $log->uid = $request->input('uid', $code->uid);
        $log->ip = $request->getClientIp();
        $log->detail = $detail;
        $log->save();

        return $this->success('邮箱绑定成功');
    }

    /**
     * 发送短信
     *
     * @param string $phone      手机号
     * @param array  $data       发送数据
     * @param string $templateId 模板ID
     * @return boolean
     */
    protected function send($phone, $data, $templateId)
    {
        $easySms = new EasySms(config('sms.easysms'));
        
        $res = $easySms->send($phone, [
            'template' => $templateId,
            'data' => $data
        ]);

        if ($res['aliyun']['status'] === 'success') {
            return true;
        }
        return false;
    }

    /**
     * 打印订单信息
     * @param integer $id
     * @param integer $oid
     * @param Request $request
     * @return void
     */
    public function printOrder($id, $oid, Request $request)
    {
        $project= Project::with('sponsorinfo')->find($id);
        if (!$project) {
            return $this->error(405, '非法操作');
        }

        $order = DrugOrder::where('project_id', $id)
            ->where('id', $oid)
            ->first();

        if (!$order) {
            return $this->error(405, '非法操作');
        }

        $products = DrugOrderDetail::with('product')
            ->where('order_id', $oid)
            ->get();

        $result = [];
        foreach ($products as $product) {
            $result[] = [
                'id' => $product->product->id,
                'number' => $product->product->number,
                'batch' => $product->product->batch,
                'type' => $product->product->type,
                'expire_in' => $product->product->expire_in,
                'status' => $product->product->status,
                'remark' => $product->remark
            ];
        }

        // return view('pdf.order', [ 'project' => $project->toArray(), 'basic' => $order->toArray(), 'products' => $result ]);
        $path = 'storage/orders/order_'.(Uuid::uuid4())->toString().$order->id.'_'.$project->id.'.pdf';
        $pdf = PDF::loadView('pdf.order', [ 'project' => $project->toArray(), 'basic' => $order->toArray(), 'products' => $result ])->save(public_path($path));
        return $this->success('获取成功', '/'.$path);
    }

    /**
     * ID发号器
     *
     * @param Request $request
     * @return void
     */
    public function getId(Request $request)
    {
        
        // 判断用户是否登陆
        $userId = 1122;
        if (!$userId) {
            return $this->error(405, '非法请求');
        }
        // 用户id 格式长度不够 补0
        $userNumber = sprintf("%05d", $userId);

        // 获取业务编码
        switch ($request->input('X')) {
            case 1:
                $code = 'X';
                break;
            default:
                return $this->error(405, '业务需求有误');
                break;
        }

        // 获取时间
        $time = date('Ymd', time());

        // 获取 Redis 唯一编号（message:number）
        $number = Redis::incr('message:number');

        return $id = $code.$time.$userNumber.$number;
    }
}
