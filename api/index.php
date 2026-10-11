<?php
declare(strict_types=1);
require __DIR__.'/core.php';
tg_headers();
try{
 tg_session_start();
 if(!tg_config())tg_fail('Site non configuré. Ouvre /installation.php.',503);
 $route=parse_url($_SERVER['REQUEST_URI']??'/api',PHP_URL_PATH);$method=$_SERVER['REQUEST_METHOD']??'GET';
 if($route==='/api/development-settings'&&$method==='GET'){tg_admin();tg_reply(200,['enabled'=>tg_dev_enabled()]);}
 if($route==='/api/development-settings'&&$method==='POST'){
  tg_origin();tg_admin();$x=tg_body(300);if(!isset($x['enabled'])||!is_bool($x['enabled']))tg_fail('Réglage invalide.');
  tg_db(function(&$db)use($x){$db['developmentEnabled']=$x['enabled'];return true;},true);tg_reply(200,['enabled'=>$x['enabled']]);
 }
 if($route==='/api/development-password'&&$method==='POST'){
  tg_origin();tg_admin();$x=tg_body(1024);$pass=$x['password']??null;
  if(!is_string($pass)||strlen($pass)<12||strlen($pass)>128)tg_fail('Le code doit contenir entre 12 et 128 caractères.');
  $p=tg_private_dir().'/config.json';$h=@fopen($p,'c+');if(!$h)tg_fail('Configuration inaccessible.',503);
  try{if(!flock($h,LOCK_EX))tg_fail('Fichier occupé.',503);
   rewind($h);$cfg=json_decode((string)stream_get_contents($h),true);
   if(!is_array($cfg)||empty($cfg['adminHash']))tg_fail('Configuration invalide.',503);
   $cfg['developmentHash']=password_hash($pass,PASSWORD_DEFAULT);
   $json=json_encode($cfg,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
   rewind($h);ftruncate($h,0);fwrite($h,$json);fflush($h);flock($h,LOCK_UN);
  }finally{fclose($h);}tg_reply(200,['ok'=>true]);
 }
 if($route==='/api/status'&&$method==='GET')tg_reply(200,['mode'=>'server','orders'=>true,'auth'=>'password','platform'=>'php-ovh']);
 if($route==='/api/storefront'&&$method==='GET'){
  tg_dev_guard();tg_reply(200,tg_db(function($db){$r=tg_products($db);foreach($r['products'] as &$p)$p['stock']=$db['stocks'][$p['sku']]??new stdClass();return $r;}));
 }
 if($route==='/api/legal'&&$method==='GET'){tg_dev_guard();tg_reply(200,tg_db(fn($db)=>$db['legal']));}
 if($route==='/api/session'&&$method==='GET')tg_reply(200,['role'=>tg_role()]);
 if($route==='/api/login'&&$method==='POST'){
  tg_origin();tg_ratelimit('login',8,900,true);
  $x=tg_body(2048);$role=$x['role']??'';$pass=$x['password']??'';
  if(!in_array($role,['admin','coach'],true)||!is_string($pass)||strlen($pass)>256)tg_fail('Identifiants incorrects.',401);
  $cfg=tg_config();if(!password_verify($pass,(string)($cfg[$role.'Hash']??''))){tg_record_failed_login();tg_fail('Mot de passe incorrect.',401);}
  tg_ratelimit('login',8,900,true,true);session_regenerate_id(true);$_SESSION['tg_role']=$role;$_SESSION['tg_expires']=time()+8*3600;
  tg_reply(200,['role'=>$role]);
 }
 if($route==='/api/logout'&&$method==='POST'){
  tg_origin();$_SESSION=[];session_regenerate_id(true);tg_reply(200,['ok'=>true]);
 }
 if($route==='/api/orders'&&$method==='POST'){
  tg_dev_guard();tg_origin();tg_ratelimit('order',8,1800);$x=tg_body(5000);
  if(!empty($x['website']))tg_fail('Formulaire non accepté.');
  $r=tg_db(function(&$db)use($x){
   $pr=tg_products($db);$p=null;foreach($pr['products'] as $p0)if($p0['sku']===($x['sku']??null)){$p=$p0;break;}
   if(!$p)tg_fail('Article non disponible.',404);
   $size=trim((string)($x['size']??''));if(!in_array($size,$p['sizes'],true))tg_fail('Taille incorrecte.');
   $first=trim((string)($x['first']??''));$last=trim((string)($x['last']??''));$group=trim((string)($x['group']??''));$email=trim((string)($x['email']??''));
   if(!$first||!$last||!$group||!filter_var($email,FILTER_VALIDATE_EMAIL)||tg_len($first)>80||tg_len($last)>80||tg_len($group)>80||tg_len($email)>160)tg_fail('Merci de compléter tous les champs correctement.');
   if(($x['privacyAccepted']??null)!==true)tg_fail('Consulte les informations de confidentialité avant de commander.');
   if(!in_array($group,tg_active_groups($db),true))tg_fail('Sélectionne un groupe valide et actif.',400);
   $q=$db['stocks'][$p['sku']][$size]??null;
   if(is_int($q)&&$q<=0)tg_fail('Cette taille est actuellement épuisée.',409);
   if(is_int($q))$db['stocks'][$p['sku']][$size]=$q-1;
   $o=['id'=>bin2hex(random_bytes(18)),'reference'=>tg_ref(),'date'=>gmdate('c'),'product'=>$p['nom'],'sku'=>$p['sku'],'size'=>$size,'first'=>$first,'last'=>$last,'group'=>$group,'email'=>$email,'price'=>$p['price'],'status'=>'Nouvelle'];
   array_unshift($db['orders'],$o);return ['reference'=>$o['reference'],'status'=>'Nouvelle'];
  },true);tg_reply(201,$r);
 }
 if($route==='/api/orders'&&$method==='GET'){tg_admin();tg_reply(200,tg_db(fn($db)=>$db['orders']));}
 if($route==='/api/site'&&$method==='GET'){tg_admin();tg_reply(200,tg_db(fn($db)=>$db['site']??new stdClass()));}
 if($route==='/api/stock'&&$method==='GET'){tg_admin();tg_reply(200,tg_db(fn($db)=>['products'=>tg_products($db)['products'],'stocks'=>$db['stocks']]));}
 if($route==='/api/history'&&$method==='GET'){tg_admin();tg_reply(200,tg_db(fn($db)=>array_slice($db['history'],0,10)));}
 if($route==='/api/coaches'&&$method==='GET'){tg_staff();tg_reply(200,tg_db(fn($db)=>$db['coaches']));}
 if($route==='/api/orders.csv'&&$method==='GET'){
  tg_admin();$orders=tg_db(fn($db)=>$db['orders']);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="tempogym-commandes.csv"');header('Cache-Control: no-store');echo "\xEF\xBB\xBF"; $fields=['reference','date','product','sku','size','first','last','group','email','status','price'];echo implode(';',$fields)."\r\n";foreach($orders as $o){echo implode(';',array_map(fn($k)=>tg_csv_cell($o[$k]??''),$fields))."\r\n";}exit;
 }
 if($method==='GET'&&preg_match('#^/media/([a-f0-9]{36}\.jpg)$#',$route,$m)){
  tg_dev_guard();$p=tg_private_dir().'/media/'.$m[1];if(!is_file($p))tg_fail('Image introuvable.',404);
  header('Content-Type: image/jpeg');header('Cache-Control: public, max-age=604800');readfile($p);exit;
 }
 if(!in_array($method,['POST','DELETE'],true))tg_fail('Page introuvable.',404);
 tg_origin();tg_admin();
 if($route==='/api/site'&&$method==='POST'){
  $x=tg_body(2600000);if(array_diff(array_keys($x),TG_ALLOWED))tg_fail('Champ inconnu.');
  $s=json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($s===false||preg_match('/<\s*\/?\s*(script|iframe|object|embed|svg|math|form|style|link|meta|base)\b|javascript\s*:|\bon[a-z]+\s*=|__proto__|constructor/i',$s))tg_fail('Contenu non autorisé.');
  tg_db(function(&$db)use($x){if($db['site'])array_unshift($db['history'],['at'=>gmdate('c'),'data'=>$db['site']]);$db['history']=array_slice($db['history'],0,10);$db['site']=$x;return true;},true);tg_reply(200,['ok'=>true]);
 }
 if($route==='/api/stock'&&$method==='POST'){
  $x=tg_body(2500);tg_db(function(&$db)use($x){$p=null;foreach(tg_products($db)['products'] as $candidate)if($candidate['sku']===($x['sku']??null)){$p=$candidate;break;}
   if(!$p||!in_array($x['size']??null,$p['sizes'],true))tg_fail('Produit ou taille introuvable.');$q=$x['quantity']??null;
   if($q!==null&&(!is_int($q)||$q<0||$q>100000))tg_fail('Quantité invalide.');
   $db['stocks'][$p['sku']][$x['size']]=$q;return true;
  },true);tg_reply(200,['ok'=>true]);
 }
 if($route==='/api/order-status'&&$method==='POST'){
  $x=tg_body(1000);tg_db(function(&$db)use($x){foreach($db['orders'] as &$o)if($o['id']===($x['id']??null)){
   if(!in_array($x['status']??null,TG_STATUSES,true))tg_fail('État invalide.');$q=$db['stocks'][$o['sku']][$o['size']]??null;
   if($o['status']==='Annulée'&&$x['status']!=='Annulée'){if(is_int($q)&&$q<=0)tg_fail('Plus de stock pour réactiver la commande.',409);if(is_int($q))$db['stocks'][$o['sku']][$o['size']]=$q-1;}
   if($o['status']!=='Annulée'&&$x['status']==='Annulée'&&is_int($q))$db['stocks'][$o['sku']][$o['size']]=$q+1;
   $o['status']=$x['status'];return true;
  }tg_fail('Commande introuvable.',404);},true);tg_reply(200,['ok'=>true]);
 }
 if($route==='/api/order-anonymize'&&$method==='POST'){
  $x=tg_body(400);tg_db(function(&$db)use($x){foreach($db['orders'] as &$o)if($o['id']===($x['id']??null)){
   if(!in_array($o['status'],['Remise','Annulée'],true))tg_fail('Termine ou annule avant d’anonymiser.',409);
   foreach(['first','last','group','email'] as $k)$o[$k]='';$o['anonymizedAt']=gmdate('c');return true;
  }tg_fail('Commande introuvable.',404);},true);tg_reply(200,['ok'=>true]);
 }
 if($route==='/api/legal'&&$method==='POST'){
  $x=tg_body(15000);tg_db(function(&$db)use($x){foreach(tg_default_legal() as $k=>$val){if(!array_key_exists($k,$x))continue;$v=$x[$k];if($k==='retentionMonths'){if(!is_int($v)||$v<1||$v>120)tg_fail('Durée de conservation invalide.');}else{if(!is_string($v)||tg_len($v)>3000)tg_fail('Texte invalide.');$v=trim($v);} $db['legal'][$k]=$v;}return true;},true);tg_reply(200,['ok'=>true]);
 }
 if($route==='/api/media'&&$method==='POST'){
  $limit=600000;if((int)($_SERVER['CONTENT_LENGTH']??0)>$limit)tg_fail('Image trop volumineuse.',413);
  $bytes=file_get_contents('php://input',false,null,0,$limit+1);if(strlen($bytes)>$limit||strlen($bytes)<10||substr($bytes,0,2)!=="\xFF\xD8"||substr($bytes,-2)!=="\xFF\xD9")tg_fail('Une image JPEG est attendue.');
  $info=@getimagesizefromstring($bytes);if(!$info||($info[2]??0)!==IMAGETYPE_JPEG)tg_fail('Fichier image invalide.');
  $dir=tg_private_dir().'/media';if(!is_dir($dir))mkdir($dir,0700,true);$name=bin2hex(random_bytes(18)).'.jpg';if(!file_put_contents($dir.'/'.$name,$bytes,LOCK_EX))tg_fail('Échec de l’import.');tg_reply(201,['url'=>'/media/'.$name]);
 }
 if($route==='/api/coaches'&&$method==='POST'){
  $x=tg_body(8500);$section=(string)($x['section']??'');$title=trim((string)($x['title']??''));$desc=trim((string)($x['description']??''));$audience=trim((string)($x['audience']??''));$url=trim((string)($x['url']??''));
  if(!in_array($section,['annonce','document','planning','rotation'],true)||!$title||tg_len($title)>140||tg_len($desc)>2500||tg_len($audience)>100||strlen($url)>1000||($url!==''&&!str_starts_with($url,'https://')))tg_fail('Informations invalides.');
  tg_db(function(&$db)use($section,$title,$desc,$audience,$url){array_unshift($db['coaches'],['id'=>bin2hex(random_bytes(18)),'section'=>$section,'title'=>$title,'description'=>$desc,'audience'=>$audience,'url'=>$url,'created_at'=>gmdate('c')]);return true;},true);tg_reply(201,['ok'=>true]);
 }
 if($route==='/api/coaches'&&$method==='DELETE'){
  $x=tg_body(1000);tg_db(function(&$db)use($x){$prev=count($db['coaches']);$db['coaches']=array_values(array_filter($db['coaches'],fn($o)=>$o['id']!==($x['id']??null)));if($prev===count($db['coaches']))tg_fail('Élément introuvable.',404);return true;},true);tg_reply(200,['ok'=>true]);
 }
 tg_fail('Page introuvable.',404);
}catch(Throwable $e){error_log('TempoGym V5 : '.$e->getMessage());tg_fail('Erreur interne. Vérifie les droits de stockage ou contacte le responsable technique.',500);}
