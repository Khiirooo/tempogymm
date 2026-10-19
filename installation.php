<?php
declare(strict_types=1);
require __DIR__.'/api/core.php';
tg_headers();header('Cache-Control: no-store');tg_session_start();
if(tg_config()){header('Location: /',true,302);exit;}
// Génère une clé non committée sur GitHub et stockée à côté de www, hors du web.
try {
    tg_init_dir();
    $keyPath=tg_private_dir().'/installation_key.txt';
    if (!is_file($keyPath)) {
        $newKey=bin2hex(random_bytes(32));
        $h=@fopen($keyPath,'x');
        if ($h) { fwrite($h,$newKey); fclose($h); @chmod($keyPath,0600); }
    }
    $secret=trim((string)@file_get_contents($keyPath));
    if(strlen($secret)!==64)throw new RuntimeException('Clé installation manquante');
} catch (Throwable $e) {
    error_log('Installation TempoGym : '.$e->getMessage());
    http_response_code(503);
    exit('Impossible de préparer l’installation. Le dossier parent de www doit être inscriptible.');
}
$error='';$ok=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $key=(string)($_POST['key']??'');$admin=(string)($_POST['admin']??'');$coach=(string)($_POST['coach']??'');$visitor=(string)($_POST['visitor']??'');$csrf=(string)($_POST['csrf']??'');
  if(!hash_equals($secret,$key) || !hash_equals($_SESSION['tg_csrf']??'',$csrf))$error='Clé d’installation incorrecte ou formulaire expiré.';
  elseif(strlen($admin)<14||strlen($coach)<14||strlen($visitor)<14||strlen($admin)>128||strlen($coach)>128||strlen($visitor)>128||hash_equals($admin,$coach)||hash_equals($admin,$visitor)||hash_equals($coach,$visitor))$error='Choisis trois mots de passe différents de 14 caractères minimum (128 maximum).';
  elseif($admin!==($_POST['admin2']??'')||$coach!==($_POST['coach2']??'')||$visitor!==($_POST['visitor2']??''))$error='Les confirmations ne correspondent pas.';
  else{
    try{
      tg_init_dir();$p=tg_private_dir().'/config.json';$f=@fopen($p,'x');
      if(!$f)throw new RuntimeException('Configuration déjà créée.');
      $cfg=['adminHash'=>password_hash($admin,PASSWORD_DEFAULT),'coachHash'=>password_hash($coach,PASSWORD_DEFAULT),'developmentHash'=>password_hash($visitor,PASSWORD_DEFAULT),'installedAt'=>gmdate('c')];
      fwrite($f,json_encode($cfg,JSON_PRETTY_PRINT));fclose($f);@chmod($p,0600);
      @unlink($keyPath); // Inutile après configuration ; ne pas laisser de clé active.
      header('Location: /',true,303);exit;
    }catch(Throwable $e){error_log('TempoGym installation : '.$e->getMessage());$error='Impossible d’installer. Vérifie que le dossier parent de www est inscriptible.';}
  }
}
if(!isset($_SESSION['tg_csrf']))$_SESSION['tg_csrf']=bin2hex(random_bytes(24));
?><!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Installation privée — Royal TempoGym Jette</title><style>
*{box-sizing:border-box}body{margin:0;background:#f2f4f8;color:#15233c;font:15px/1.5 system-ui,Arial;display:grid;min-height:100vh;place-items:center;padding:22px}.card{width:min(100%,560px);background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 18px 65px #0917312a}.hero{background:#07172e;color:#fff;padding:30px}.brand{color:#e8ad47;font-weight:800;text-transform:uppercase;letter-spacing:.12em;font-size:12px}h1{font-size:27px;line-height:1.15;margin:14px 0 8px}p{margin:0}.hero p{color:#d3dbea;font-size:14px}form{padding:27px;display:grid;gap:16px}label{font-weight:700;display:grid;gap:7px}input{font:inherit;width:100%;padding:12px;border-radius:9px;border:1px solid #b9c4d5}input:focus{outline:2px solid #deaa49}button{font:inherit;cursor:pointer;border:0;border-radius:9px;background:#ce2d44;color:#fff;padding:14px;font-weight:800}.sub{color:#586b82;font-size:12px}.err{color:#b92334;font-weight:700}.note{background:#fff7e8;padding:13px 27px;font-size:13px;color:#654c26}
</style></head><body><div class="card"><header class="hero"><div class="brand">Royal TempoGym Jette • Production</div><h1>Configurer le site sur OVH</h1><p>Une seule fois. Aucun serveur ni logiciel à lancer : OVH exécute automatiquement le PHP.</p></header><form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['tg_csrf'],ENT_QUOTES)?>"><label>Clé privée d’installation<input name="key" type="password" required autocomplete="off" placeholder="Lire tempogym_prive/installation_key.txt sur OVH"></label><label>Mot de passe administration<input name="admin" type="password" required minlength="14" maxlength="128" autocomplete="new-password"></label><label>Confirmer le mot de passe administration<input name="admin2" type="password" required minlength="14" maxlength="128" autocomplete="new-password"></label><label>Mot de passe entraîneurs<input name="coach" type="password" required minlength="14" maxlength="128" autocomplete="new-password"></label><label>Confirmer le mot de passe entraîneurs<input name="coach2" type="password" required minlength="14" maxlength="128" autocomplete="new-password"></label><label>Mot de passe visiteur du site en développement<input name="visitor" type="password" required minlength="14" maxlength="128" autocomplete="new-password"></label><label>Confirmer le mot de passe visiteur<input name="visitor2" type="password" required minlength="14" maxlength="128" autocomplete="new-password"></label><p class="sub">Au moins 14 caractères, trois mots de passe différents. Ne réutilise pas les anciens codes courts : la version en ligne protège des commandes et des données personnelles.</p><?php if($error):?><p class="err" role="alert"><?=htmlspecialchars($error,ENT_QUOTES)?></p><?php endif;?><button type="submit">Installer et ouvrir le site →</button></form><div class="note">La clé unique de cette installation est dans <strong>tempogym_prive/installation_key.txt</strong>, accessible uniquement par FTP dans le dossier privé au même niveau que <strong>www</strong>. Elle est supprimée après installation. Le stockage privé reste en dehors du dossier public. Tu pourras activer/désactiver l’écran de développement depuis le panneau administrateur. Le code visiteur est choisi pendant cette installation ; aucun code commun n’est utilisé.</div></div></body></html>
