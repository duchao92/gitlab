<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// ID 发号器
Route::get('getId', 'CommonController@getId');

// 图片验证码
Route::get('code', 'CommonController@getCode');
// 短信验证码
Route::post('sms', 'CommonController@sendSms');
// 绑定邮箱
Route::post('binding/email', 'CommonController@bindingEmail');

// Auth路由
Route::namespace('Auth')->group(function () {
    // 登录
    Route::post('login', 'LoginController@login')->middleware('log.login');
    // 移动端登录
    Route::post('login/mobile', 'LoginController@mobileLogin')->middleware('log.login');
    // 用户注册
    Route::post('signup', 'RegisterController@register');
    // 验证邮箱验证码
    Route::post('check/code', 'RegisterController@checkCode');
    // 验证邮箱验证码
    Route::post('activate', 'RegisterController@activate');
    // 验证账户是否已注册
    Route::post('check/account', 'RegisterController@checkAccount');
    // 忘记密码
    Route::post('forget', 'ForgotPasswordController@forget');
    // 重置密码
    Route::post('forget/reset', 'ForgotPasswordController@reset');
});

Route::middleware('auth:api')->group(function () {
    // 用户接口
    Route::prefix('user')->group(function () {
        // 获取个人基础信息
        Route::get('basic', 'ProfileController@getBasic');
        // 获取登录日志
        Route::get('logs/login', 'LogController@getLoginLogs');
        // 设置个人信息
        Route::post('basic', 'ProfileController@setBasic')->middleware('log.profile');
        // 绑定手机
        Route::post('binding/phone', 'ProfileController@bindingPhone')->middleware('log.profile');
        // 发送绑定邮箱
        Route::post('binding/sendemail', 'ProfileController@sendEmail');
        // 设置提醒方式
        Route::post('notify', 'ProfileController@setNotify')->middleware('log.profile');
        // 重置密码
        Route::post('resetpwd', 'ProfileController@resetPwd')->middleware('log.profile');
        // 账号操作日志
        Route::get('logs/profile', 'LogController@getProfileLogs');
    });

    // 消息中心
    Route::prefix('messages')->group(function () {
        // 获取消息列表
        Route::get('/', 'MessageController@list');
        // 获取具体消息
        Route::get('/{id}/dashboard', 'MessageController@details');
        // 标记已读（全部和部分）
        Route::put('/mark', 'MessageController@edit');
    });
    
    // 获取单位列表
    Route::get('organizations', 'OrganizationController@list');
    // 研究领域
    Route::get('fields', 'CommonController@getFields');

    Route::middleware('check.basicinfo')->group(function () {
        // 管理员通用模块
        Route::middleware('check.types:1')->group(function () {
            // 系统模块
            Route::get('modules', 'CommonController@getModules');
            // 获取单个单位信息
            Route::get('organizations/{id}', 'OrganizationController@show')->where(['id' => '[0-9]+']);
            // 获取用户列表
            Route::get('users', 'UserController@list');
            // 获取单个用户信息
            Route::get('users/{id}', 'UserController@show')->where(['id' => '[0-9]+']);
            // 获取单个用户信息，含登录信息
            Route::get('users/{id}/full', 'UserController@showFull')->where(['id' => '[0-9]+']);

            Route::middleware('log.operation')->group(function () {
                // 新增单位
                Route::post('organizations', 'OrganizationController@add');
                // 编辑单位
                Route::put('organizations/{id}', 'OrganizationController@edit')->where(['id' => '[0-9]+']);
                // 删除单位
                Route::delete('organizations/{id}', 'OrganizationController@delete')->where(['id' => '[0-9]+']);
                // 新增用户
                Route::post('users', 'UserController@add');
                // 关联用户
                Route::post('users/{id}', 'UserController@relate')->where(['id' => '[0-9]+']);
                // 编辑用户
                Route::put('users/{id}', 'UserController@edit')->where(['id' => '[0-9]+']);
                // 删除用户
                Route::delete('users/{id}', 'UserController@delete')->where(['id' => '[0-9]+']);
            });
        });
        // 检索用户列表
        Route::get('users/search', 'UserController@search');

        Route::middleware('check.permissions:project_mange|project_configure')->group(function () {
            // 系统权限列表
            Route::get('permissions/{id?}', 'CommonController@getPermissions');
        });

        Route::prefix('projects')->middleware('log.operation')->group(function () {
            // 导出项目配置
            Route::get('{id}/config', 'ConfigController@export');
            // 导入项目配置
            Route::post('upload', 'ConfigController@import');
            // 新增项目
            Route::post('', 'ProjectController@add')->middleware('check.types:1');
            // 获取项目列表
            Route::get('', 'ProjectController@list');
            // 获取项目基本信息
            Route::get('{id}/basic', 'ProjectController@getBasic');
            // 获取项目分层信息
            Route::get('{id}/layers', 'LayerController@list');
            // 关联项目
            Route::post('relate', 'ProjectController@relate');
            Route::middleware('check.permissions:project_mange|project_configure')->group(function () {
                // 新增项目基本信息
                Route::post('{id}/basic', 'ProjectController@addBasic');
                // 获取项目所选模块
                Route::get('{id}/modules', 'ProjectController@getModules');
                // 获取项目角色
                Route::get('{id}/roles', 'RoleController@list');
                // 新增项目角色
                Route::post('{id}/roles', 'RoleController@add');
                // 编辑项目角色
                Route::put('{id}/roles/{rid}', 'RoleController@edit');
                // 删除项目角色
                Route::delete('{id}/roles/{rid}', 'RoleController@delete');
                // 新增项目关联单位
                Route::post('{id}/organizations', 'ProjectController@addOrganization');
                // 编辑项目关联单位
                Route::put('{id}/organizations/{oid}', 'ProjectController@editOrganization');
                // 删除项目关联单位
                Route::delete('{id}/organizations/{oid}', 'ProjectController@deleteOrganization');
                // 获取项目关联的用户
                Route::get('{id}/users', 'ProjectController@listUsers');
                // 验证用户是否已关联
                Route::post('{id}/users/check', 'ProjectController@checkUsers');
                // 新增项目关联用户
                Route::post('{id}/users', 'ProjectController@addUser');
                // 编辑项目关联用户
                Route::put('{id}/users/{rid}', 'ProjectController@editUser');
                // 重发邮件
                Route::get('{id}/users/{rid}/reinvite', 'ProjectController@reInvite');
                // 删除项目关联用户
                Route::delete('{id}/users/{rid}', 'ProjectController@deleteUser');
                // 设置项目随机化分组类型及相关信息
                Route::put('{id}/recordinfo', 'ProjectController@setRecordInfo');
                // 新增项目分层信息
                Route::post('{id}/layers', 'LayerController@add');
                // 编辑项目分层信息
                Route::put('{id}/layers/{lid}', 'LayerController@edit')->where(['id' => '[0-9]+', 'lid' => '[0-9]+']);
                // 删除项目分层信息
                Route::delete('{id}/layers/{lid}', 'LayerController@delete')
                    ->where(['id' => '[0-9]+', 'lid' => '[0-9]+']);
                // 获取项目中心分层信息
                Route::get('{id}/layers/organizations', 'LayerController@listOrganizations');
                // 编辑项目中心分层信息
                Route::put('{id}/layers/organizations', 'LayerController@editOrganizations');
                // 设置物流类型
                Route::post('{id}/products/logistics', 'ProjectController@editLogistics');
            });

            Route::middleware('check.permissions:project_mange|project_configure|record_manager|single_record|emergency_unblinding|all_unblinding|patient_print|product_pick|patient_list_view')->group(function () {
                // 获取项目随机化分组类型及相关信息
                Route::get('{id}/recordinfo', 'ProjectController@getRecordInfo');
            });

            Route::middleware('check.permissions:project_mange|project_configure|logistic_setting|product_send|product_receive|storage_autosend|storage_receive')->group(function () {
                // 获取项目关联的单位
                Route::get('{id}/organizations', 'ProjectController@listOrganizations');
                // 获取项目关联的单位简要信息
                Route::get('{id}/organizations/basic', 'ProjectController@listOrganizationsBasic');
            });

            // 编辑项目状态
            Route::put('{id}/publish', 'ProjectController@publish')->middleware('check.permissions:project_status_manage');
            // 编辑项目状态
            Route::put('{id}/status', 'ProjectController@updateStatus')->middleware('check.permissions:project_status_manage');
            // 获取关联单位的随机化统计信息
            Route::get('{id}/organizations/statistics', 'StatisticController@listOrganizations');
            // 获取当前用户在项目关联的单位
            Route::get('{id}/organizations/related', 'ProjectController@listRelatedOrganizations');

            Route::middleware('check.permissions:patient_blind')->group(function () {
                // 获取随机化盲底
                Route::get('{id}/blinds/rand', 'BlindController@listRandBlind');
                // 上传随机化盲底
                Route::post('{id}/blinds/rand', 'BlindController@uploadRandBlind');
                // 确认随机化盲底
                Route::post('{id}/blinds/rand/{bid}', 'BlindController@confirmRandBlind');
                // 查看随机化盲底
                Route::get('{id}/blinds/rand/{bid}', 'BlindController@showRandBlind');
            });

            Route::middleware('check.permissions:product_blind')->group(function () {
                // 获取药物盲底上传记录
                Route::get('{id}/blinds/products', 'BlindController@listDrugBlinds');
                // 上传药物盲底
                Route::post('{id}/blinds/products', 'BlindController@uploadDrugBlind');
                // 确认药物盲底
                Route::post('{id}/blinds/products/confirm', 'BlindController@confirmDrugBlind');
            });

            Route::middleware('check.permissions:product_send|product_receive|storage_autosend|storage_receive')->group(function () {
                // 获取项目产品列表
                Route::get('{id}/products', 'ProductController@list');
                // 获取项目产品库存列表
                Route::get('{id}/products/storage', 'ProductController@listStorage');
                // 批量设置批号
                Route::put('{id}/products/batch', 'ProductController@setBatch');
                // 批量作废
                Route::put('{id}/products/unavailable', 'ProductController@setInValid');
                // 获取产品操作日志
                Route::get('{id}/products/{pid}/conductlogs', 'ProductController@listConductLogs');
                // 获取产品使用日志
                Route::get('{id}/products/uselogs', 'ProductController@listUseLogs');
                // 获取产品使用日志
                Route::get('{id}/products/{pid}/uselogs', 'ProductController@getUseLogs');
                // 订单列表
                Route::get('{id}/products/orders', 'OrderController@list');
                // 获取订单基本信息
                Route::get('{id}/products/orders/{oid}/basic', 'OrderController@showBasic');
                // 获取订单详细信息
                Route::get('{id}/products/orders/{oid}/products', 'OrderController@showProducts');
                // 获取订单操作日志
                Route::get('{id}/products/orders/{oid}/logs', 'OrderController@listLogs');
                // 订单打印
                Route::get('{id}/products/orders/{oid}/print', 'CommonController@printOrder');
            });

            Route::middleware('check.permissions:product_send|product_receive|storage_autosend|storage_receive')->group(function () {
                // 库房发货生成订单
                Route::post('{id}/products/orders', 'OrderController@add');
                // 库房发货
                Route::put('{id}/products/orders/{oid}/send', 'OrderController@send');
                // 库房取消订单
                Route::put('{id}/products/orders/{oid}/cancle', 'OrderController@cancle');
            });

            Route::middleware('check.permissions:logistic_setting')->group(function () {
                // 新增药房物流配置
                Route::post('{id}/logistics/organization', 'LogisitcsController@add');
                // 编辑药房物流配置
                Route::put('{id}/logistics/organization/{oid}', 'LogisitcsController@edit');
                // 删除药房物流配置
                Route::delete('{id}/logistics/organization/{oid}', 'LogisitcsController@delete');
                // 获取药房物流配置
                Route::get('{id}/logistics', 'LogisitcsController@list');
            });

            // 获取项目分组信息
            Route::get('{id}/groups', 'GroupController@list');

            // 获取项目操作日志
            Route::get('{id}/logs', 'LogController@getProjectOperationLogs')->middleware('check.permissions:project_conduct_logs');

            // 获取项目配置日志
            Route::get('{id}/setting/logs', 'LogController@getProjectSettingLogs')->middleware('check.permissions:project_configure_logs');
            
            // 药房收货
            Route::put('{id}/products/orders/{oid}/receive', 'OrderController@receive')->middleware('check.permissions:product_receive|storage_receive');

            Route::middleware('check.permissions:product_pick')->group(function () {
                // 取药
                Route::post('{id}/product/dosage', 'PatientController@useProduct');
            });

            Route::middleware('check.permissions:record_manager|single_record')->group(function () {
                // 新增受试者
                Route::post('{id}/patients', 'PatientController@add');
                // 编辑受试者
                Route::put('{id}/patients', 'PatientController@edit');
            });

            Route::middleware('check.permissions:record_manager|single_record|patient_list_view')->group(function () {
                // 添加受试者备注
                Route::post('{id}/patients/{pid}/remark', 'PatientController@addRemark');
                // 获取受试者信息
                Route::get('{id}/patients/{pid}', 'PatientController@show');
                // 获取受试者信息
                Route::get('{id}/patients/{pid}/pic', 'PatientController@showPic');
            });

            Route::middleware('check.permissions:patient_list_view')->group(function () {
                // 获取受试者列表
                Route::get('{id}/patients', 'PatientController@list');
                // 批量导出
                Route::post('{id}/patients/export', 'PatientController@exportBatch');
            });

            Route::middleware('check.permissions:all_unblinding')->group(function () {
                // 全部揭盲
                Route::post('{id}/patients/unblind', 'PatientController@unblindAll');
            });

            Route::middleware('check.permissions:emergency_unblinding')->group(function () {
                // 紧急揭盲
                Route::post('{id}/patients/{pid}/unblind', 'PatientController@unblind');
            });

            // 获取特定项目中的角色及权限列表
            Route::get('{id}/role', 'RoleController@show');
        });
    });
});
