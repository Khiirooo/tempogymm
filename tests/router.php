<?php
/* Routeur réservé aux tests locaux ; Apache/OVH utilise .htaccess. */
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$root=dirname(__DIR__);
if($path==='/')require $root.'/index.php';
elseif($path==='/installation.php')require $root.'/installation.php';
elseif(str_starts_with($path,'/api/')||str_starts_with($path,'/media/'))require $root.'/api/index.php';
elseif($path==='/index.html'){header('Location: /',true,302);}
elseif(preg_match('#^/(?:assets|image|floatingimage|logo|document)/[a-zA-Z0-9_/.% -]+\.(?:js|css|png|jpe?g|webp|pdf)$#',$path)&&!str_contains($path,'..')&&is_file($root.$path))return false;
else{http_response_code(404);echo 'Introuvable';}
