<?php
/** Runs only against the isolated CI database. */
$dsn=getenv('SL_TEST_DSN')?:'';
if(!str_contains($dsn,'dbname=swiftlane_test'))throw new RuntimeException('A scratch test database is required');
define('CDP_ROOT',dirname(__DIR__));
define('CDP_APP_MODE_DEMO',false);
class Conexion {
 public PDO $dbh;
 public function __construct(){
  $this->dbh=new PDO(getenv('SL_TEST_DSN'),'root',getenv('SL_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 }
}
class StorageTestAdmin {
 public bool $logged_in=true;
 public function cdp_is_Admin(){return true;}
}
$user=new StorageTestAdmin();
require_once CDP_ROOT.'/helpers/delivery_storage.php';
if(!dl_ensureStorage())throw new RuntimeException('Delivery storage setup failed');
$db=(new Conexion())->dbh;
if(in_array('--existing',$argv,true)){
 $value=$db->query('SELECT config_json FROM cdb_delivery_config WHERE id=1')->fetchColumn();
 if($value!=='{"enabled":true}')throw new RuntimeException('Existing delivery configuration changed');
 echo "Existing storage and configuration preserved\n";
 exit;
}
$db->exec("INSERT INTO cdb_delivery_config(id,config_json,revision,updated_at) VALUES(1,'{\"enabled\":true}',7,NOW())");
$count=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cdb_freight_schedules'")->fetchColumn();
if($count)throw new RuntimeException('An unrelated freight table was installed');
$process=proc_open([PHP_BINARY,__FILE__,'--existing'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes);
fclose($pipes[0]);
if(proc_close($process)!==0)throw new RuntimeException('Repeat installation failed');
echo "MySQL delivery storage setup passed\n";
