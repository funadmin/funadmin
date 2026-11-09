<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\FormFieldCatalog;
use app\cms\service\FormValueValidator;
use PHPUnit\Framework\TestCase;
final class FormValueValidatorTest extends TestCase
{
    private function check(array $field,mixed $value,array $fileCounts=[]): array
    {
        $field+=['name'=>'f','label'=>'F'];
        $input=$value===null?[]:[$field['name']=>$value];
        return FormValueValidator::validate(FormFieldCatalog::normalize([$field]),$input,$fileCounts);
    }
    private function error(array $field,mixed $value): ?string
    {
        return $this->check($field,$value)['errors']['f']??null;
    }
    private function value(array $field,mixed $value): mixed
    {
        $result=$this->check($field,$value);
        self::assertSame([],$result['errors']);
        return $result['values']['f'];
    }
    public function test_email_phone_number_and_options_are_validated_server_side(): void
    {
        self::assertSame('required',$this->error(['type'=>'email','required'=>true],'  '));
        self::assertSame('invalid_email',$this->error(['type'=>'email'],'not-an-email'));
        self::assertSame('a@example.com',$this->value(['type'=>'email'],' a@example.com '));
        self::assertNull($this->value(['type'=>'email'],''));
        self::assertSame('invalid_phone',$this->error(['type'=>'phone'],'1234'));
        self::assertSame('invalid_phone',$this->error(['type'=>'phone'],'+86 138-0000-0000 ext'));
        self::assertSame('invalid_phone',$this->error(['type'=>'phone'],str_repeat('1',33)));
        self::assertSame('+86 (10) 1234-5678',$this->value(['type'=>'phone'],'+86 (10) 1234-5678'));
        self::assertSame('invalid_number',$this->error(['type'=>'number'],'abc'));
        self::assertSame('invalid_number',$this->error(['type'=>'number','integer'=>true],'1.5'));
        self::assertSame('out_of_range',$this->error(['type'=>'number','min'=>1,'max'=>10],'11'));
        self::assertSame('out_of_range',$this->error(['type'=>'number','min'=>1,'max'=>10],0));
        self::assertSame(7,$this->value(['type'=>'number','integer'=>true,'min'=>1,'max'=>10],'7'));
        self::assertSame(2.5,$this->value(['type'=>'number'],'2.5'));
        $options=[['value'=>'a','label'=>'A'],['value'=>'b','label'=>'B']];
        self::assertSame('invalid_option',$this->error(['type'=>'radio','options'=>$options],'c'));
        self::assertSame('invalid_option',$this->error(['type'=>'radio','options'=>$options],['a']));
        self::assertSame('required',$this->error(['type'=>'radio','required'=>true,'options'=>$options],''));
        self::assertSame('b',$this->value(['type'=>'radio','options'=>$options],'b'));
        self::assertSame('invalid_option',$this->error(['type'=>'select','options'=>$options],'z'));
        self::assertSame('a',$this->value(['type'=>'select','options'=>$options],'a'));
        self::assertSame(['a','b'],$this->value(['type'=>'select','multiple'=>true,'options'=>$options],['a','b']));
        self::assertSame('invalid_option',$this->error(['type'=>'select','multiple'=>true,'options'=>$options],['a','x']));
    }
    public function test_checkbox_min_max_selections(): void
    {
        $field=['type'=>'checkbox','options'=>[['value'=>'a','label'=>'A'],['value'=>'b','label'=>'B'],['value'=>'c','label'=>'C']],'min'=>2,'max'=>2];
        self::assertSame('out_of_range',$this->error($field,['a']));
        self::assertSame('out_of_range',$this->error($field,['a','b','c']));
        self::assertSame(['a','c'],$this->value($field,['a','c']));
        self::assertSame(['a','c'],$this->value($field,['a','c','a']),'duplicates are collapsed');
        self::assertSame('invalid_option',$this->error($field,['a','zzz']));
        self::assertSame('invalid_option',$this->error($field,'a'));
        self::assertSame([],$this->value(['type'=>'checkbox','options'=>$field['options']],null),'optional and empty is allowed even with no input');
        self::assertSame('required',$this->error(['type'=>'checkbox','required'=>true,'options'=>$field['options']],[]));
    }
    public function test_date_datetime_time_formats_and_range(): void
    {
        self::assertSame('invalid_date',$this->error(['type'=>'date'],'2026-02-30'));
        self::assertSame('invalid_date',$this->error(['type'=>'date'],'09/11/2026'));
        self::assertSame('out_of_range',$this->error(['type'=>'date','min'=>'2026-01-01','max'=>'2026-12-31'],'2027-01-01'));
        self::assertSame('out_of_range',$this->error(['type'=>'date','min'=>'2026-01-01'],'2025-12-31'));
        self::assertSame('2026-11-09',$this->value(['type'=>'date','min'=>'2026-01-01','max'=>'2026-12-31'],'2026-11-09'));
        self::assertSame('invalid_date',$this->error(['type'=>'datetime'],'2026-11-09'));
        self::assertSame('invalid_date',$this->error(['type'=>'datetime'],'2026-11-09T25:00'));
        self::assertSame('2026-11-09 14:30',$this->value(['type'=>'datetime'],'2026-11-09T14:30'));
        self::assertSame('2026-11-09 14:30:15',$this->value(['type'=>'datetime'],'2026-11-09T14:30:15'));
        self::assertSame('invalid_date',$this->error(['type'=>'time'],'24:00'));
        self::assertSame('invalid_date',$this->error(['type'=>'time'],'noon'));
        self::assertSame('08:05',$this->value(['type'=>'time'],'08:05'));
        self::assertSame('required',$this->error(['type'=>'time','required'=>true],''));
    }
    public function test_required_consent_must_be_true(): void
    {
        $field=['type'=>'consent','required'=>true,'html'=>'I agree'];
        self::assertSame('must_accept',$this->error($field,null));
        self::assertSame('must_accept',$this->error($field,'0'));
        self::assertTrue($this->value($field,'1'));
        self::assertTrue($this->value($field,'on'));
        self::assertTrue($this->value($field,true));
        self::assertFalse($this->value(['type'=>'consent','html'=>'Newsletter'],null));
    }
    public function test_hidden_from_query_is_truncated_to_500(): void
    {
        self::assertSame(str_repeat('x',500),$this->value(['type'=>'hidden','from_query'=>true],str_repeat('x',600)));
        self::assertSame('ad campaign',$this->value(['type'=>'hidden','from_query'=>true],"ad\x00 campaign"));
        self::assertSame('fallback',$this->value(['type'=>'hidden','from_query'=>true,'value'=>'fallback'],null));
        self::assertSame('fixed',$this->value(['type'=>'hidden','value'=>'fixed'],'tampered'),'fixed hidden values ignore visitor input');
    }
    public function test_text_lengths_cap_at_2000_and_20000(): void
    {
        self::assertSame('too_long',$this->error(['type'=>'text'],str_repeat('a',2001)));
        self::assertSame(str_repeat('啊',2000),$this->value(['type'=>'text'],str_repeat('啊',2000)));
        self::assertSame('too_long',$this->error(['type'=>'textarea'],str_repeat('a',20001)));
        self::assertSame(str_repeat('a',20000),$this->value(['type'=>'textarea'],str_repeat('a',20000)));
        self::assertSame('too_long',$this->error(['type'=>'text','max'=>5],'abcdef'));
        self::assertSame('too_short',$this->error(['type'=>'text','min'=>3],'ab'));
        self::assertSame('a b',$this->value(['type'=>'text'],"  a\x01\nb  "));
        self::assertSame("line1\nline2",$this->value(['type'=>'textarea'],"line1\r\nline2\x07"));
        self::assertSame('invalid',$this->error(['type'=>'text'],['array']));
        self::assertSame('invalid',$this->error(['type'=>'text'],"\xff\xfe"));
        self::assertSame('required',$this->error(['type'=>'text','required'=>true],null));
    }
    public function test_file_required_and_max_files_use_counts(): void
    {
        $fields=FormFieldCatalog::normalize([['name'=>'doc','type'=>'file','label'=>'Doc','required'=>true,'max_files'=>2],['name'=>'pic','type'=>'image','label'=>'Pic']]);
        self::assertSame(['doc'=>'required'],FormValueValidator::validate($fields,[],[])['errors']);
        self::assertSame(['doc'=>'too_many_files'],FormValueValidator::validate($fields,[],['doc'=>3])['errors']);
        self::assertSame(['pic'=>'too_many_files'],FormValueValidator::validate($fields,[],['doc'=>1,'pic'=>2])['errors']);
        $ok=FormValueValidator::validate($fields,['doc'=>'ignored'],['doc'=>2,'pic'=>1]);
        self::assertSame([],$ok['errors']);
        self::assertSame([],$ok['values'],'file fields produce no values');
    }
    public function test_display_fields_and_unknown_input_are_ignored(): void
    {
        $fields=FormFieldCatalog::normalize([['type'=>'paragraph','html'=>'Hi'],['type'=>'divider'],['name'=>'name','type'=>'text','label'=>'Name']]);
        $result=FormValueValidator::validate($fields,['name'=>'Ann','_p1'=>'x','extra'=>'y'],[]);
        self::assertSame(['values'=>['name'=>'Ann'],'errors'=>[]],$result);
        self::assertSame(FormValueValidator::ERRORS,array_values(array_unique(FormValueValidator::ERRORS)));
    }
}
