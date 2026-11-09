<?php

declare(strict_types=1);

namespace app\cms\service;

/**
 * 访客可见的表单文案：按钮、结果页与 FormValueValidator / FormSubmissionService 产出的错误键。
 * 内置 zh-cn 与 en-us，其他语言回退 en-us；两种语言的键必须一致。
 */
final class FormMessages
{
    private const MESSAGES = [
        'zh-cn' => [
            'submit' => '提交',
            'success' => '提交成功，感谢您的留言。',
            'required' => '必填',
            'captcha' => '验证码',
            'captcha_refresh' => '换一张',
            'honeypot' => '请勿填写此项',
            'select' => '请选择',
            'error_title' => '提交未成功',
            'error_summary' => '请检查以下内容后重新提交：',
            'success_title' => '提交成功',
            'back' => '返回上一页',
            'network' => '网络异常，请稍后重试。',
            'unavailable' => '表单不存在或已停用。',
            'errors' => [
                'required' => '此项为必填',
                'invalid' => '内容无效',
                'too_short' => '内容过短',
                'too_long' => '内容过长',
                'invalid_email' => '请输入有效的邮箱地址',
                'invalid_phone' => '请输入有效的电话号码',
                'invalid_number' => '请输入有效的数字',
                'invalid_option' => '选项无效',
                'out_of_range' => '超出允许的范围',
                'invalid_date' => '请输入有效的日期或时间',
                'must_accept' => '请勾选同意后再提交',
                'too_many_files' => '文件数量超出限制',
                'invalid_file_type' => '不支持的文件类型',
                'file_too_large' => '文件过大',
                'files_too_large' => '上传文件总大小超出限制',
                'token_invalid' => '页面已失效，请刷新后重试',
                'too_fast' => '提交过快，请稍候再试',
                'token_expired' => '页面已过期，请刷新后重试',
                'too_many_requests' => '提交过于频繁，请稍后再试',
                'captcha_invalid' => '验证码错误，请重新输入',
            ],
        ],
        'en-us' => [
            'submit' => 'Submit',
            'success' => 'Thank you. Your submission has been received.',
            'required' => 'Required',
            'captcha' => 'Verification code',
            'captcha_refresh' => 'New code',
            'honeypot' => 'Leave this field empty',
            'select' => 'Please choose',
            'error_title' => 'Submission failed',
            'error_summary' => 'Please check the following and try again:',
            'success_title' => 'Submitted',
            'back' => 'Go back',
            'network' => 'Network error. Please try again later.',
            'unavailable' => 'This form is unavailable.',
            'errors' => [
                'required' => 'This field is required.',
                'invalid' => 'This value is invalid.',
                'too_short' => 'This value is too short.',
                'too_long' => 'This value is too long.',
                'invalid_email' => 'Please enter a valid email address.',
                'invalid_phone' => 'Please enter a valid phone number.',
                'invalid_number' => 'Please enter a valid number.',
                'invalid_option' => 'Please choose a valid option.',
                'out_of_range' => 'This value is out of range.',
                'invalid_date' => 'Please enter a valid date or time.',
                'must_accept' => 'Please accept to continue.',
                'too_many_files' => 'Too many files.',
                'invalid_file_type' => 'This file type is not allowed.',
                'file_too_large' => 'This file is too large.',
                'files_too_large' => 'The total upload size is too large.',
                'token_invalid' => 'This page is no longer valid. Please refresh and try again.',
                'too_fast' => 'You submitted too quickly. Please wait a moment and try again.',
                'token_expired' => 'This page has expired. Please refresh and try again.',
                'too_many_requests' => 'Too many submissions. Please try again later.',
                'captcha_invalid' => 'The verification code is incorrect.',
            ],
        ],
    ];

    /** @return array<string,mixed> */
    public static function for(string $locale): array
    {
        return self::MESSAGES[strtolower(trim($locale))] ?? self::MESSAGES['en-us'];
    }

    /** 翻译一个错误键；未知键按“内容无效”处理，不回显内部键名。 */
    public static function error(array $messages, string $key): string
    {
        return $messages['errors'][$key] ?? $messages['errors']['invalid'];
    }
}
