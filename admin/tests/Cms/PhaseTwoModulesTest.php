<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{PublicSite,SiteConfiguration,SiteService};
use support\Request;
final class PhaseTwoModulesTest extends CmsTestCase
{
    private function home(string $host='cms.test',string $path='/'): string
    {
        $response=(new PublicSite())->handle(new Request("GET /cms$path HTTP/1.1\r\nHost: $host\r\n\r\n"));
        self::assertSame(200,$response->getStatusCode());return $response->rawBody();
    }
    private function field(string $key,array $types): array
    {
        $fields=[];foreach($types as $name=>$type)$fields[]=['key'=>$name,'label'=>$name,'type'=>$type];
        return ['key'=>$key,'label'=>$key,'type'=>'list','localized'=>true,'fields'=>$fields];
    }
    public function test_home_uses_scoped_enabled_modules_and_replaces_hero_only_for_valid_slides(): void
    {
        $site=$this->site();$this->site('other','other.test');$sites=new SiteService();$sites->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $config=new SiteConfiguration();$config->save($site['id'],'1',['fields'=>[
            $this->field('slides',['title'=>'text','image'=>'image','mobile_image'=>'image','url'=>'url']),
            $this->field('partners',['name'=>'text','logo'=>'image','url'=>'url']),
            $this->field('contacts',['name'=>'text','phone'=>'text','email'=>'email','qrcode'=>'image']),
            $this->field('ads',['name'=>'text','image'=>'image','url'=>'url','position'=>'text']),
        ],'values'=>[
            'slides'=>[['title'=>'Visible slide','image'=>'/storage/hero.webp','mobile_image'=>'/storage/mobile.webp','url'=>'/news/'],['title'=>'Hidden slide','image'=>'/storage/hidden.webp','enabled'=>false]],
            'partners'=>[['name'=>'<Partner>','logo'=>'/storage/logo.webp','url'=>'https://partner.example']],
            'contacts'=>[['name'=>'Sales team','phone'=>'12345','email'=>'sales@example.test']],
            'ads'=>[['name'=>'Home offer','image'=>'/storage/offer.webp','url'=>'/offer','position'=>'home'],['name'=>'Sidebar offer','image'=>'/storage/sidebar.webp','position'=>'sidebar']],
        ]]);
        $html=$this->home();
        foreach(['data-cms-slider','Visible slide','/storage/mobile.webp','&lt;Partner&gt;','Sales team','Home offer'] as $value)self::assertStringContainsString($value,$html);
        foreach(['Hidden slide','Sidebar offer','class="hero-art"','<Partner>'] as $value)self::assertStringNotContainsString($value,$html);
        self::assertStringNotContainsString('Visible slide',$this->home('other.test'));
        $config->save($site['id'],'1',['locale'=>'en-us','values'=>['slides'=>[],'partners'=>[],'contacts'=>[],'ads'=>[]]]);
        $english=$this->home('cms.test','/en/');self::assertStringNotContainsString('data-cms-slider',$english);self::assertStringContainsString('class="hero-art"',$english);
    }
    public function test_modules_revalidate_links_even_when_a_custom_configuration_uses_text_fields(): void
    {
        $site=$this->site();(new SiteConfiguration())->save($site['id'],'1',['fields'=>[$this->field('partners',['name'=>'text','logo'=>'text','url'=>'text'])],'values'=>['partners'=>[['name'=>'Unsafe logo partner','logo'=>'javascript:alert(1)','url'=>'javascript:alert(2)']]]]);
        $html=$this->home();self::assertStringContainsString('Unsafe logo partner',$html);self::assertStringNotContainsString('javascript:',$html);self::assertStringNotContainsString('data-cms-slider',$html);
    }
    public function test_full_module_configuration_keeps_the_complete_home_within_template_budgets(): void
    {
        $site=$this->site();$fields=[];$values=[];
        foreach(['slides'=>['title'=>'text','image'=>'image','mobile_image'=>'image','url'=>'url'],'partners'=>['name'=>'text','logo'=>'image','url'=>'url'],'contacts'=>['name'=>'text','phone'=>'text','email'=>'email','qrcode'=>'image'],'ads'=>['name'=>'text','image'=>'image','url'=>'url','position'=>'text']] as $key=>$types){
            $fields[]=$this->field($key,$types);$row=[];
            foreach($types as $name=>$type)$row[$name]=$type==='image'?'/storage/test.webp':($type==='url'?'/test':($type==='email'?'test@example.test':($name==='position'?'home':'Test')));
            $values[$key]=array_fill(0,100,$row);
        }
        (new SiteConfiguration())->save($site['id'],'1',['fields'=>$fields,'values'=>$values]);
        $html=$this->home();self::assertSame(8,substr_count($html,'class="home-slide"'));self::assertSame(12,substr_count($html,'class="partner-card"'));self::assertSame(6,substr_count($html,'class="contact-card"'));self::assertSame(4,substr_count($html,'class="offer-card"'));
    }
}
