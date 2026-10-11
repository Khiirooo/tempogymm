<?php
/** Page publique OVH : charge le HTML actuel + les réglages enregistrés, sans Node.js. */
declare(strict_types=1);
require __DIR__.'/api/core.php';
try {
 tg_headers();tg_session_start();
 if(!tg_config()){header('Location: /installation.php',true,302);exit;}
 if(tg_dev_enabled()&&!tg_dev_authorized()){
   header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');
   $error='';
   if($_SERVER['REQUEST_METHOD']==='POST'){
     tg_ratelimit('development',10,900);
     $csrf=(string)($_POST['csrf']??'');$pass=(string)($_POST['password']??'');
     if(hash_equals((string)($_SESSION['tg_dev_csrf']??''),$csrf)&&strlen($pass)<=256&&password_verify($pass,(string)(tg_config()['developmentHash']??''))){
       session_regenerate_id(true);$_SESSION['tg_dev_expires']=time()+8*3600;header('Location: /',true,303);exit;
     }$error='Mot de passe incorrect.';
   }
   if(!isset($_SESSION['tg_dev_csrf']))$_SESSION['tg_dev_csrf']=bin2hex(random_bytes(24));
   $csrf=htmlspecialchars($_SESSION['tg_dev_csrf'],ENT_QUOTES,'UTF-8');
   $errorHtml=$error?'<div class="error" role="alert">Mot de passe incorrect.</div>':'';
   echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Site en développement — Royal TempoGym Jette</title><style>*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 10% 12%,#17304b 0,transparent 28%),radial-gradient(circle at 92% 90%,#241a37 0,transparent 32%),#061329;color:white;min-height:100vh;display:grid;place-items:center;font:14px/1.6 Arial,sans-serif;padding:24px}.card{width:min(100%,430px);text-align:center}.tag{color:#f5a623;letter-spacing:.3em;font:bold 12px Arial,sans-serif;text-transform:uppercase}h1{text-transform:uppercase;font:900 clamp(32px,6vw,55px)/1.03 Impact,"Arial Narrow",sans-serif;margin:28px 0 10px}h1 span{color:#e8332a;display:block}p{color:#aebdd1;margin:0 auto 30px;max-width:340px}form{display:grid;text-align:left;gap:12px}label{font-weight:bold;color:#cddaea}input{width:100%;background:#172a48;color:#fff;border:1px solid #455977;padding:14px;border-radius:6px;font-size:16px}button{margin-top:11px;background:#e8332a;color:white;border:0;padding:16px;border-radius:6px;font-weight:bold;cursor:pointer}.error{color:#ffaaa5}footer{margin:40px 0;color:#73839c;font-size:11px;letter-spacing:.2em}</style></head><body><div class="card"><div class="tag">Royal TempoGym Jette</div><h1>Site en cours <span>de développement</span></h1><p>Le nouveau site du club est bientôt disponible. Accès réservé à l’équipe et aux personnes autorisées.</p><form method="POST" autocomplete="off"><input type="hidden" name="csrf" value="'.$csrf.'"><label for="pw">Mot de passe d’accès</label><input id="pw" name="password" type="password" required autocomplete="current-password" autofocus placeholder="Mot de passe d’accès"><button type="submit">ACCÉDER AU SITE →</button>'.$errorHtml.'</form><footer>BRUXELLES · BELGIQUE</footer></div></body></html>';exit;
 }
 $site=tg_db(fn($db)=>$db['site']?:new stdClass());
 $boot='window.TG_PASSWORD_MODE=true;window.TG_SERVER_DATA='.json_encode($site,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_UNICODE).';';
 $html=file_get_contents(__DIR__.'/index.html');
 if($html===false||!str_contains($html,'/* TG_V4_SERVER_BOOT */'))throw new RuntimeException('Page HTML introuvable');
 header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');echo str_replace('/* TG_V4_SERVER_BOOT */',$boot,$html);
}catch(Throwable $e){error_log($e->getMessage());http_response_code(503);echo 'Site temporairement indisponible : configuration à vérifier.';}
