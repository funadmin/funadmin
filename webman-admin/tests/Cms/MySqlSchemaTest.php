<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
final class MySqlSchemaTest extends \PHPUnit\Framework\TestCase
{
    public function test_shipped_migration_installs_on_real_mysql_and_enforces_route_uniqueness(): void
    {
        $database=getenv('CMS_MYSQL_DATABASE')?:'';
        if(!preg_match('/^funadmin_cms_test_[a-f0-9]+$/D',$database))self::markTestSkipped('Set a task-owned CMS_MYSQL_DATABASE to verify MySQL DDL');
        $pdo=new \PDO('mysql:unix_socket='.(getenv('CMS_MYSQL_SOCKET')?:'/tmp/mysql.sock').';dbname='.$database.';charset=utf8mb4','root','',[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $sql=file_get_contents(dirname(__DIR__,2).'/plugins/cms/database/migrations/001_cms.sql');$pdo->exec($sql);
        self::assertCount(count(\app\cms\service\Schema::tables()),$pdo->query('SHOW TABLES')->fetchAll());
        $statement=$pdo->prepare('INSERT INTO fun_cms_route (site_id,path,locale,kind,target_type,target_id) VALUES (?,?,?,?,?,?)');
        $statement->execute([1,'/same','zh-cn','page','content',1]);$statement->execute([2,'/same','zh-cn','page','content',2]);
        try{$statement->execute([1,'/same','en-us','page','category',3]);self::fail('Same site path must be unique across types and languages');}catch(\PDOException $e){self::assertSame('23000',$e->getCode());}
        $columns=$pdo->query("SHOW FULL COLUMNS FROM fun_cms_route LIKE 'path'")->fetchAll(\PDO::FETCH_ASSOC);self::assertSame('utf8mb4_bin',$columns[0]['Collation']);
    }
}
