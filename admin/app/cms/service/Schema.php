<?php
declare(strict_types=1);
namespace app\cms\service;
use Illuminate\Database\Schema\{Blueprint,Builder};
use Illuminate\Database\MySqlConnection;
use support\Db;
/** Single schema definition produces both portable isolated tests and shipped MySQL migration. */
final class Schema
{
    public static function tables(): array { return ['cms_site','cms_site_locale','cms_domain','cms_site_admin','cms_content_model','cms_model_version','cms_site_model','cms_category','cms_category_locale','cms_content','cms_content_locale','cms_content_revision','cms_route','cms_menu','cms_menu_item','cms_template_tag','cms_template_tag_version']; }
    public static function install(): void { self::create(Db::schema()); }
    public static function mysql(): string
    {
        $connection=new MySqlConnection(static fn()=>throw new \RuntimeException('Schema export must not connect'),'', 'fun_', ['driver'=>'mysql','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','engine'=>'InnoDB']);
        $queries=$connection->pretend(static fn()=>self::create($connection->getSchemaBuilder()));
        return "-- CMS schema; generated from app/cms/service/Schema.php. Prefix rewritten by host migration runner.\n".implode(";\n",array_map(static fn($q)=>$q['query'],$queries)).";\n";
    }
    private static function create(Builder $s): void
    {
        $s->create('cms_site',static function(Blueprint $t): void {
            $t->id();$t->string('code',64)->unique();$t->string('name',100);$t->string('default_locale',20);$t->unsignedBigInteger('primary_domain_id')->nullable();$t->string('theme',64)->default('default');$t->json('settings')->nullable();$t->boolean('status')->default(true);$t->timestamps();$t->softDeletes();
        });
        $s->create('cms_site_locale',static function(Blueprint $t): void {
            $t->id();$t->unsignedBigInteger('site_id');$t->string('locale',20);$t->string('url_prefix',64)->default('');$t->string('name',100)->default('');$t->string('seo_title')->default('');$t->string('seo_description',500)->default('');$t->json('settings')->nullable();$t->unsignedBigInteger('canonical_route_id')->nullable();$t->boolean('status')->default(true);$t->timestamps();$t->unique(['site_id','locale']);$t->unique(['site_id','url_prefix']);
        });
        $s->create('cms_domain',static function(Blueprint $t): void {
            $t->id();$t->unsignedBigInteger('site_id');$t->string('host',253)->unique();$t->string('verification_status',20)->default('pending');$t->boolean('status')->default(true);$t->timestamps();$t->index(['site_id','status']);
        });
        $s->create('cms_site_admin',static function(Blueprint $t): void { $t->unsignedBigInteger('site_id');$t->unsignedBigInteger('admin_id');$t->unique(['site_id','admin_id']); });
        $s->create('cms_content_model',static function(Blueprint $t): void { $t->id();$t->string('code',64)->unique();$t->string('name',100);$t->unsignedInteger('current_version')->default(1);$t->boolean('status')->default(true);$t->timestamps(); });
        $s->create('cms_model_version',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('model_id');$t->unsignedInteger('version');$t->json('field_schema');$t->dateTime('created_at');$t->unique(['model_id','version']); });
        $s->create('cms_site_model',static function(Blueprint $t): void { $t->unsignedBigInteger('site_id');$t->unsignedBigInteger('model_id');$t->string('default_template',128)->default('detail');$t->boolean('status')->default(true);$t->unique(['site_id','model_id']); });
        $s->create('cms_category',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('parent_id')->nullable();$t->unsignedBigInteger('model_id')->nullable();$t->unsignedInteger('sort_order')->default(50);$t->boolean('status')->default(true);$t->timestamps();$t->softDeletes();$t->index(['site_id','parent_id','status']); });
        $s->create('cms_category_locale',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('category_id');$t->string('locale',20);$t->string('name',100);$t->text('description')->nullable();$t->unsignedBigInteger('cover_attach_id')->nullable();$t->string('list_template',128)->default('category');$t->string('detail_template',128)->default('detail');$t->string('seo_title')->default('');$t->string('seo_description',500)->default('');$t->unsignedBigInteger('canonical_route_id')->nullable();$t->boolean('status')->default(true);$t->timestamps();$t->unique(['category_id','locale']); });
        $s->create('cms_content',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('model_id');$t->unsignedBigInteger('category_id')->nullable();$t->unsignedBigInteger('created_by');$t->unsignedBigInteger('updated_by');$t->timestamps();$t->softDeletes();$t->index(['site_id','category_id','deleted_at','id'],'cms_content_category_idx'); });
        $s->create('cms_content_locale',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('content_id');$t->string('locale',20);$t->string('status',20)->default('draft');$t->unsignedBigInteger('canonical_route_id')->nullable();$t->unsignedBigInteger('published_revision_id')->nullable();$t->unsignedBigInteger('draft_revision_id')->nullable();$t->dateTime('published_at')->nullable();$t->dateTime('published_updated_at')->nullable();$t->unsignedInteger('lock_version')->default(0);$t->timestamps();$t->softDeletes();$t->unique(['content_id','locale']);$t->index(['site_id','status','deleted_at','published_at','id'],'cms_content_public_idx'); });
        $s->create('cms_content_revision',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('content_locale_id');$t->unsignedInteger('revision_no');$t->unsignedBigInteger('model_version_id');$t->unsignedBigInteger('category_id')->nullable();$t->string('title');$t->text('summary')->nullable();$t->longText('body_html')->nullable();$t->string('desired_path',512);$t->unsignedBigInteger('cover_attach_id')->nullable();$t->string('author_name',100)->default('');$t->string('template',128)->default('detail');$t->string('seo_title')->default('');$t->string('seo_description',500)->default('');$t->string('robots',40)->default('index,follow');$t->json('data');$t->unsignedBigInteger('created_by');$t->dateTime('created_at');$t->unique(['content_locale_id','revision_no'],'cms_revision_number_unique'); });
        $s->create('cms_route',static function(Blueprint $t)use($s): void { $t->id();$t->unsignedBigInteger('site_id');$t->string('path',512)->collation($s->getConnection()->getDriverName()==='mysql'?'utf8mb4_bin':'BINARY');$t->string('locale',20);$t->string('kind',20)->default('page');$t->string('target_type',20);$t->unsignedBigInteger('target_id');$t->timestamps();$t->unique(['site_id','path']);$t->index(['site_id','target_type','target_id'],'cms_route_target_idx'); });
        $s->create('cms_menu',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('site_id');$t->string('locale',20);$t->string('code',64);$t->string('name',100);$t->boolean('status')->default(true);$t->timestamps();$t->unique(['site_id','locale','code']); });
        $s->create('cms_menu_item',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('menu_id');$t->unsignedBigInteger('parent_id')->nullable();$t->string('title',100);$t->string('target_type',20)->default('external');$t->unsignedBigInteger('target_id')->nullable();$t->string('external_url',1024)->default('');$t->boolean('open_in_new_tab')->default(false);$t->unsignedInteger('sort_order')->default(50);$t->boolean('status')->default(true);$t->timestamps();$t->index(['site_id','menu_id','status']); });
        $s->create('cms_template_tag',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('site_id');$t->string('locale',20)->default('');$t->string('name',64);$t->unsignedInteger('current_version')->default(0);$t->boolean('status')->default(true);$t->timestamps();$t->softDeletes();$t->unique(['site_id','locale','name']); });
        $s->create('cms_template_tag_version',static function(Blueprint $t): void { $t->id();$t->unsignedBigInteger('tag_id');$t->unsignedInteger('version');$t->string('provider',64);$t->json('parameters');$t->text('template_fragment')->nullable();$t->unsignedBigInteger('created_by');$t->dateTime('created_at');$t->unique(['tag_id','version']); });
    }
}
