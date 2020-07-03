<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/26
 */
namespace speed\helper;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

use think\facade\Db;
class MailHelper {

    /**
     * 邮件发送
     * @param $to    接收人
     * @param string $subject   邮件标题
     * @param string $content   邮件内容(html模板渲染后的内容)
     * @throws Exception
     * @throws phpmailerException
     */
    public static function sendEmail($to,$subject='',$content=''){
        //判断openssl是否开启
        $openssl_funcs = get_extension_funcs('openssl');
        if(!$openssl_funcs){
            return array('code'=>0 , 'msg'=>'请先开启openssl扩展');
        }
        $mail = new PHPMailer;
        $config = Db::name('config')->where('type','email')->cache(3600)->column('value','code');
        $mail->CharSet  = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
        $mail->isSMTP();
        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = 0;
        //调试输出格式
        $mail->Debugoutput = 'html';
        //smtp服务器
        $mail->Host = $config['email_host'];
        //端口 - likely to be 25, 465 or 587
        $mail->Port = $config['email_port'];

        $mail->SMTPSecure =$config['email_secure'];// 使用安全协议
        //Whether to use SMTP authentication
        $mail->SMTPAuth = true;
        //用户名
        $mail->Username = $config['email_addr'];
        //密码
        $mail->Password = $config['email_pass'];
        //Set who the message is to be sent from
        $mail->setFrom($config['email_addr']);
        //回复地址
        //$mail->addReplyTo('replyto@example.com', 'First Last');
        //接收邮件方
        if(is_array($to)){
            foreach ($to as $v){
                $mail->addAddress($v);
            }
        }else{
            $mail->addAddress($to);
        }

        $mail->isHTML(true);// send as HTML
        //标题
        $mail->Subject = $subject;
        //邮箱正文
        $mail->Body = $content;
        //HTML内容转换
//        $mail->msgHTML($content);
        //Replace the plain text body with one created manually
        //$mail->AltBody = 'This is a plain-text message body';
        //添加附件
        //$mail->addAttachment('images/phpmailer_mini.png');
        //send the message, check for errors
        try {
            $mail->send();
            return  array('code'=>1 , 'msg'=>'成功');
        }catch (Exception $e){
            return array('code'=>0 , 'msg'=>$e->getMessage());
        }

    }


}