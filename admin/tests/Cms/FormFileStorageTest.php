<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\FormFileStorage;
use PHPUnit\Framework\TestCase;
final class FormFileStorageTest extends TestCase
{
    private const PNG='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const PDF="%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
    private string $root;
    private string $scratch;
    private FormFileStorage $storage;
    protected function setUp(): void
    {
        parent::setUp();
        $base=sys_get_temp_dir().'/cms-form-files-'.bin2hex(random_bytes(6));
        $this->root=$base.'/forms';
        $this->scratch=$base.'/uploads';
        mkdir($this->scratch,0700,true);
        $this->storage=new FormFileStorage($this->root);
    }
    protected function tearDown(): void
    {
        self::remove(dirname($this->root));
        parent::tearDown();
    }
    private static function remove(string $path): void
    {
        if(is_link($path)||is_file($path)){unlink($path);return;}
        if(!is_dir($path))return;
        foreach(scandir($path) as $item) if($item!=='.'&&$item!=='..') self::remove($path.'/'.$item);
        rmdir($path);
    }
    /** @return array{tmp:string,name:string,size:int} */
    private function upload(string $name,string $content): array
    {
        $tmp=tempnam($this->scratch,'up');
        file_put_contents($tmp,$content);
        return ['tmp'=>$tmp,'name'=>$name,'size'=>strlen($content)];
    }
    private function rejects(array $field,array $upload,string $key,string $why): void
    {
        try{$this->storage->inspect($field,$upload);self::fail('Expected rejection: '.$why);}
        catch(\InvalidArgumentException $e){self::assertSame($key,$e->getMessage(),$why);self::assertSame(422,$e->getCode(),$why);}
    }
    private static function file(array $extra=[]): array { return ['name'=>'f','type'=>'file','label'=>'F']+$extra; }
    private static function image(array $extra=[]): array { return ['name'=>'i','type'=>'image','label'=>'I']+$extra; }
    public function test_rejects_executable_types_even_with_allowed_extension(): void
    {
        $this->rejects(self::file(),$this->upload('cv.pdf','<?php echo 1;'),'invalid_file_type','php payload with pdf extension');
        $this->rejects(self::file(),$this->upload('notes.txt','<script>alert(1)</script>'),'invalid_file_type','script payload with txt extension');
        $this->rejects(self::file(),$this->upload('notes.txt',"\xEF\xBB\xBF  <svg onload=alert(1)>"),'invalid_file_type','svg payload behind BOM and whitespace');
        $this->rejects(self::file(),$this->upload('a.php.pdf',self::PDF),'invalid_file_type','double extension with blacklisted segment');
        $this->rejects(self::file(),$this->upload('a.php .pdf',self::PDF),'invalid_file_type','blacklisted segment padded with a space');
        $this->rejects(self::file(),$this->upload('a.php..pdf',self::PDF),'invalid_file_type','blacklisted segment followed by an empty segment');
        $this->rejects(self::file(),$this->upload("a.php\u{3000}.pdf",self::PDF),'invalid_file_type','blacklisted segment padded with unicode whitespace');
        $this->rejects(self::file(),$this->upload("a.ph\u{200B}p.pdf",self::PDF),'invalid_file_type','blacklisted segment split by a zero-width character');
        $this->rejects(self::file(),$this->upload('logo.svg','<svg xmlns="http://www.w3.org/2000/svg"/>'),'invalid_file_type','svg is blacklisted');
        $this->rejects(self::file(['accept'=>['php']]),$this->upload('x.php','<?php echo 1;'),'invalid_file_type','blacklist beats accept');
        $this->rejects(self::file(),$this->upload('photo.pdf',base64_decode(self::PNG)),'invalid_file_type','sniffed mime must match extension');
        $this->rejects(self::file(),$this->upload('README',"plain text\n"),'invalid_file_type','no extension');
        $this->rejects(self::file(['accept'=>['pdf']]),$this->upload('notes.txt',"hello\n"),'invalid_file_type','extension outside field accept');
        $pdf=$this->upload('Report.PDF',self::PDF);
        self::assertSame(['ext'=>'pdf','mime'=>'application/pdf','size'=>strlen(self::PDF),'name'=>'Report.PDF','tmp'=>$pdf['tmp']],$this->storage->inspect(self::file(['accept'=>['document']]),$pdf));
    }
    public function test_image_field_rejects_documents(): void
    {
        $this->rejects(self::image(),$this->upload('cv.pdf',self::PDF),'invalid_file_type','image field with default accept');
        $this->rejects(self::image(['accept'=>['document']]),$this->upload('cv.pdf',self::PDF),'invalid_file_type','image field never accepts documents');
        $png=base64_decode(self::PNG);
        $dot=$this->upload('dot.png',$png);
        self::assertSame(['ext'=>'png','mime'=>'image/png','size'=>strlen($png),'name'=>'dot.png','tmp'=>$dot['tmp']],$this->storage->inspect(self::image(),$dot));
        $bidi=$this->storage->inspect(self::file(),$this->upload("x\u{202E}fdp\u{200B}.docx",$this->zip('word/document.xml')))['name'];
        self::assertSame('xfdp.docx',$bidi,'format characters (bidi overrides, zero-width) are stripped from the name');
        self::assertStringNotContainsString("\u{202E}",$bidi);
        self::assertStringNotContainsString("\u{200B}",$bidi);
        $zip=tempnam($this->scratch,'zip');$archive=new \ZipArchive();$archive->open($zip,\ZipArchive::OVERWRITE);$archive->addFromString('word/document.xml','<w/>');$archive->close();
        $info=$this->storage->inspect(self::file(),['tmp'=>$zip,'name'=>"..\\dir/\x07Letter.docx",'size'=>filesize($zip)]);
        self::assertSame('docx',$info['ext'],'office containers sniffed as zip are accepted');
        self::assertSame('Letter.docx',$info['name'],'path components and control chars are stripped');
        $plain=tempnam($this->scratch,'zip');$archive=new \ZipArchive();$archive->open($plain,\ZipArchive::OVERWRITE);$archive->addFromString('a.txt','hi');$archive->close();
        self::assertSame('application/zip',(new \finfo(FILEINFO_MIME_TYPE))->file($plain));
        self::assertSame('xlsx',$this->storage->inspect(self::file(),['tmp'=>$plain,'name'=>'sheet.xlsx','size'=>filesize($plain)])['ext'],'generic zip sniff is accepted for office containers');
        $long=$this->storage->inspect(self::file(),$this->upload(str_repeat('名',300).'.txt',"hi\n"))['name'];
        self::assertSame(255,mb_strlen($long));
        self::assertStringEndsWith('名.txt',$long,'truncation keeps the extension');
    }
    public function test_size_over_field_limit_is_rejected(): void
    {
        $this->rejects(self::file(['max_size'=>10]),$this->upload('a.txt',str_repeat('a',11)),'file_too_large','over field max_size');
        $this->rejects(self::file(['max_size'=>10]),['tmp'=>$this->upload('a.txt',str_repeat('a',11))['tmp'],'name'=>'a.txt','size'=>1],'file_too_large','actual size wins over the declared size');
        self::assertSame(10,$this->storage->inspect(self::file(['max_size'=>10]),$this->upload('a.txt',str_repeat('a',10)))['size']);
    }
    public function test_store_writes_private_file_outside_public_dir(): void
    {
        $png=base64_decode(self::PNG);
        $upload=$this->upload('dot.png',$png);
        $stored=$this->storage->store('7',$this->storage->inspect(self::image(),$upload));
        self::assertMatchesRegularExpression('#^7/'.date('Ym').'/[0-9a-f]{32}$#D',$stored['path']);
        self::assertSame(hash('sha256',$png),$stored['sha256']);
        $absolute=$this->storage->absolute('7',$stored['path']);
        self::assertSame($this->root.'/'.$stored['path'],$absolute);
        self::assertSame($png,file_get_contents($absolute));
        self::assertSame(0600,fileperms($absolute)&0777);
        self::assertSame(0700,fileperms(dirname($absolute))&0777);
        self::assertSame(0700,fileperms($this->root.'/7')&0777);
        self::assertFileExists($upload['tmp'],'store copies the upload');
        $public=rtrim(public_path(),'/').'/';
        self::assertStringStartsNotWith($public,$absolute);
        $default=(new FormFileStorage())->absolute('7','7/202611/'.str_repeat('a',32));
        self::assertSame(rtrim(runtime_path('plugins-data/cms/forms'),'/').'/7/202611/'.str_repeat('a',32),$default,'default root is the private runtime directory');
        self::assertStringStartsNotWith($public,$default);
        $second=$this->storage->store('7',$this->storage->inspect(self::image(),$upload));
        self::assertNotSame($stored['path'],$second['path'],'every stored file gets a fresh random name');
        $this->storage->delete('7',$stored['path']);
        self::assertFileDoesNotExist($absolute);
        $this->storage->delete('7',$stored['path']);
        self::assertDirectoryExists($this->root.'/7','delete keeps the site directory');
    }
    public function test_store_only_copies_the_inspected_file(): void
    {
        $inspected=$this->storage->inspect(self::file(),$this->upload('a.txt',"hello\n"));
        foreach(['tmp','size'] as $key){
            $partial=$inspected;unset($partial[$key]);
            try{$this->storage->store('1',$partial);self::fail('store must require '.$key);}catch(\InvalidArgumentException){}
        }
        $other=$this->upload('evil.txt','<?php echo 1;');
        try{$this->storage->store('1',['tmp'=>$other['tmp']]+$inspected);self::fail('a swapped tmp file with another size must be refused');}catch(\RuntimeException){}
        file_put_contents($inspected['tmp'],"HELLO\n");
        $stored=$this->storage->store('1',$inspected);
        self::assertSame("HELLO\n",file_get_contents($this->storage->absolute('1',$stored['path'])),'store copies exactly the path inspect returned');
        self::assertSame([],array_values(array_filter(glob($this->root.'/1/'.date('Ym').'/*'),fn($f)=>file_get_contents($f)==='<?php echo 1;')),'the swapped file never lands');
    }
    private function zip(string $entry): string
    {
        $zip=tempnam($this->scratch,'zip');$archive=new \ZipArchive();$archive->open($zip,\ZipArchive::OVERWRITE);$archive->addFromString($entry,'<w/>');$archive->close();
        return file_get_contents($zip);
    }
    public function test_absolute_refuses_traversal_and_foreign_site(): void
    {
        $upload=$this->upload('a.txt',"hi\n");
        $stored=$this->storage->store('1',$this->storage->inspect(self::file(),$upload));
        $name=basename($stored['path']);$month=date('Ym');
        foreach(['2/'.$month.'/'.$name,'1/../2/'.$month.'/'.$name,'../'.$name,'1/'.$month.'/../../../etc/passwd','/etc/passwd',$this->root.'/'.$stored['path'],'1/'.$month.'/'.$name.'/','1/'.$month.'/'.strtoupper($name),''] as $path){
            try{$this->storage->absolute('1',$path);self::fail('Expected refusal: '.$path);}
            catch(\InvalidArgumentException $e){self::assertSame(404,$e->getCode(),$path);}
        }
        try{$this->storage->delete('2',$stored['path']);self::fail('foreign site delete must be refused');}catch(\InvalidArgumentException){}
        self::assertFileExists($this->root.'/'.$stored['path']);
        $outside=$this->scratch.'/secret';file_put_contents($outside,'secret');
        $link='1/'.$month.'/'.str_repeat('b',32);symlink($outside,$this->root.'/'.$link);
        try{$this->storage->absolute('1',$link);self::fail('symlinked file must be refused');}catch(\InvalidArgumentException $e){self::assertSame(404,$e->getCode());}
        symlink($this->root.'/1/'.$month,$this->root.'/1/199901');
        try{$this->storage->absolute('1','1/199901/'.$name);self::fail('symlinked month directory must be refused');}catch(\InvalidArgumentException $e){self::assertSame(404,$e->getCode());}
        mkdir($this->root.'/3',0700);symlink($this->root.'/1/'.$month,$this->root.'/3/'.$month);
        try{$this->storage->absolute('3','3/'.$month.'/'.$name);self::fail('path resolving into another site must be refused');}catch(\InvalidArgumentException $e){self::assertSame(404,$e->getCode());}
    }
}
