<?php
/** Royal TempoGym Jette V5 — PHP 8+ OVH mutualisé, sans Node.js ni dépendance Composer. */
declare(strict_types=1);
const TG_ALLOWED = ['PAGE_ACCUEIL','HERO_PHOTO_FOND','HERO_PHOTO_GYMNASTE','HERO_PHOTO_DROITE','HERO_PARTENAIRES','REJOIGNEZ_NOUS','SITE_INSTAGRAM','SITE_FACEBOOK','DISCIPLINES','DISCIPLINES_BADGES','ENTRAINEURS','INSCRIPTIONS_OUVERTES','INSCRIPTIONS_OPEN_LEAD','INSCRIPTIONS_OPEN_URL','INSCRIPTIONS_OPEN_SAISON','INSCRIPTIONS_OPEN_ETAPES','INSCRIPTIONS_MSG','INSCRIPTIONS_NOTE','INSCRIPTIONS_ALERTE','INSCRIPTIONS_GROUPES','INSCRIPTIONS_MODALITES','BOUTIQUE_PRODUITS','BOUTIQUE_CATALOGUE','BOUTIQUE_GROUPES','STAGES','TARIFS','HORAIRES_SEMAINE','EVENEMENTS','CONTACT_INFOS','FOOTER','DOCUMENTS_BASE'];
const TG_STATUSES = ['Nouvelle','En préparation','Prête','Remise','Annulée'];
function tg_private_dir():string { return dirname(__DIR__, 2).'/tempogym_prive'; }
function tg_init_dir():void {
    $dir=tg_private_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Dossier privé non inscriptible. Vérifie les droits FTP sur le dossier parent de www.');
    if (!is_writable($dir)) throw new RuntimeException('Dossier privé non inscriptible.');
    // Défense supplémentaire en profondeur ; le dossier est déjà HORS de www.
    if (!file_exists($dir.'/.htaccess')) @file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n");
}
function tg_config():?array { $p=tg_private_dir().'/config.json'; if (!is_file($p)) return null; $r=json_decode((string)file_get_contents($p),true); return is_array($r)?$r:null; }
function tg_default_legal():array { return ['identity'=>'Royal TempoGym Jette','legalAddress'=>'','enterpriseNumber'=>'','vat'=>'','responsible'=>'','email'=>'tempogymjette@hotmail.com','phone'=>'0473/17.34.14','host'=>'','privacyContact'=>'tempogymjette@hotmail.com','retentionMonths'=>12,'delivery'=>'Retrait au club lors des permanences, sous réserve de confirmation.','payment'=>'Modalités précisées par le secrétariat au moment de la confirmation de commande.','withdrawal'=>'À préciser avec le club selon les règles applicables au type de vente.','terms'=>'Les commandes enregistrées sont des demandes soumises à validation et à la disponibilité des articles.']; }
function tg_fresh_db():array { return ['site'=>null,'history'=>[],'orders'=>[],'stocks'=>[],'coaches'=>[],'legal'=>tg_default_legal(),'developmentEnabled'=>true]; }
/** Écrit sous verrou exclusif, évite la perte de commandes simultanées. */
function tg_db(callable $fn,bool $write=false):mixed {
    tg_init_dir(); $f=@fopen(tg_private_dir().'/data.json','c+');
    if (!$f) throw new RuntimeException('Stockage indisponible');
    try {
        if(!flock($f,LOCK_EX))throw new RuntimeException('Verrou du stockage indisponible');
        rewind($f);$s=stream_get_contents($f);$v=$s?json_decode($s,true):null;
        if($s && !is_array($v))throw new RuntimeException('Les données enregistrées sont illisibles. Restaurer une sauvegarde.');
        $db=is_array($v)?$v:tg_fresh_db();
        $db['legal']=array_merge(tg_default_legal(),is_array($db['legal']??null)?$db['legal']:[]);
        $result=$fn($db);
        if($write){$j=json_encode($db,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);rewind($f);if(!ftruncate($f,0) || fwrite($f,$j)===false)throw new RuntimeException('Impossible de sauvegarder');fflush($f);}
        flock($f,LOCK_UN);return $result;
    } finally { fclose($f); }
}
function tg_reply(int $code,mixed $data):never {http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function tg_fail(string $msg,int $code=400):never {tg_reply($code,['error'=>$msg]);}
function tg_headers():void { header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: strict-origin-when-cross-origin');header('Permissions-Policy: camera=(), microphone=(), geolocation=()');}
function tg_session_start():void {session_name('tg_session');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);session_start();if(isset($_SESSION['tg_expires'])&&time()>$_SESSION['tg_expires']){$_SESSION=[];session_regenerate_id(true);} }
function tg_role():?string{return ($_SESSION['tg_expires']??0)>time()?($_SESSION['tg_role']??null):null;}
function tg_admin():void {if(tg_role()!=='admin')tg_fail('Accès administrateur requis.',403);}
function tg_staff():void {if(!in_array(tg_role(),['admin','coach'],true))tg_fail('Accès réservé aux entraîneurs.',403);}
function tg_origin():void {
    if(($_SERVER['HTTP_X_TG_REQUEST']??'')!=='v4')tg_fail('Requête refusée.',403);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if($origin!==''){ $h=parse_url($origin,PHP_URL_HOST);$p=parse_url($origin,PHP_URL_PORT);$dest=explode(':',$_SERVER['HTTP_HOST']??'');if(!$h || strcasecmp((string)$h,$dest[0])!==0 || ($p!==null && (string)$p!==($dest[1]??($origin[4]==='s'?'443':'80'))))tg_fail('Origine non autorisée.',403); }
}
function tg_body(int $limit=32000):array {
    if((int)($_SERVER['CONTENT_LENGTH']??0)>$limit)tg_fail('Données trop volumineuses.',413);
    $raw=file_get_contents('php://input',false,null,0,$limit+1);if(strlen($raw)>$limit)tg_fail('Données trop volumineuses.',413);
    $o=json_decode($raw,true);if(!is_array($o)||array_is_list($o))tg_fail('Requête JSON invalide.');return $o;
}
function tg_ratelimit(string $type,int $max,int $period,bool $failureOnly=false,bool $success=false):void {
    $id=hash('sha256',$type.':'.($_SERVER['REMOTE_ADDR']??'unknown'));
    tg_init_dir();$file=tg_private_dir().'/rate_'.$id.'.json';$f=fopen($file,'c+');if(!$f)return;
    flock($f,LOCK_EX);$v=json_decode(stream_get_contents($f),true);$v=is_array($v)?$v:[];$v=array_values(array_filter($v,fn($x)=>is_int($x)&&$x>time()-$period));
    $blocked=count($v)>=$max;
    if($success)$v=[];elseif(!$failureOnly)$v[]=time();
    rewind($f);ftruncate($f,0);fwrite($f,json_encode($v));fflush($f);flock($f,LOCK_UN);fclose($f);
    if($blocked)tg_fail('Trop de tentatives. Réessaie plus tard.',429);
}
function tg_record_failed_login():void{tg_ratelimit('login',8,900);}
function tg_products(array $db):array {
    $base=json_decode((string)file_get_contents(__DIR__.'/shop-base.json'),true);
    $fixed=json_decode((string)file_get_contents(__DIR__.'/catalog.json'),true);
    $featured=$db['site']['BOUTIQUE_PRODUITS']??$base['featured'];
    $cats=$db['site']['BOUTIQUE_CATALOGUE']??$base['categories'];
    $prods=[];
    foreach(($featured?:[]) as $p){if(empty($p['id']))continue;$prods[]=['sku'=>'vedette-'.$p['id'],'nom'=>str_replace("\n",' ',(string)($p['nom']??$p['id'])),'price'=>$p['prix']??'','sizes'=>!empty($p['tailles'])?$p['tailles']:['Taille unique']];}
    foreach(($cats?:[]) as $i=>$c)foreach(($c['articles']??[]) as $j=>$a){if(($a['dispo']??true)===false)continue;$prods[]=['sku'=>'catalogue-'.$i.'-'.$j,'nom'=>$a['titre']??'Article','price'=>strip_tags((string)($a['prix']??'')),'sizes'=>!empty($a['tailles'])?$a['tailles']:['Taille unique']];}
    foreach(($fixed['products']??[]) as $p)if(str_starts_with($p['sku'],'mini-'))$prods[]=$p;
    return ['products'=>$prods,'aliases'=>$fixed['aliases']??[]];
}
function tg_dev_enabled():bool{return tg_db(fn($db)=>(bool)($db['developmentEnabled']??true));}
function tg_dev_authorized():bool{return tg_role()==='admin'||($_SESSION['tg_dev_expires']??0)>time();}
function tg_dev_guard():void{if(tg_dev_enabled()&&!tg_dev_authorized())tg_fail('Site en cours de développement : accès requis.',403);}
function tg_active_groups(array $db):array{
    $p=json_decode((string)file_get_contents(__DIR__.'/shop-base.json'),true);
    $groups=$db['site']['BOUTIQUE_GROUPES']??($p['groups']??null);
    if(!is_array($groups))return ['Baby-Gym','Pré-Gym','GAF Loisirs','GAF Compétition','GAM Loisirs','GAM Compétition','Parkour','Fun Gym','Psychomotricité'];
    $ret=[];foreach($groups as $g){$name=is_string($g)?$g:(string)($g['nom']??'');$on=is_string($g)||($g['actif']??true)!==false;if($on&&$name!=='')$ret[]=$name;}
    return array_unique($ret);
}
function tg_ref():string{return 'TG-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(5)));}
function tg_csv_cell(mixed $val):string{$s=(string)($val??'');if(preg_match('/^\s*[=+@-]/u',$s))$s="'".$s;return '"'.str_replace('"','""',$s).'"';}

function tg_len(string $s):int{return function_exists('mb_strlen')?mb_strlen($s):strlen($s);}
