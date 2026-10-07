<?php
/* MCBA — recrutement + espace administrateur, dans un seul fichier.
   Prérequis : PHP 7.4+. Aucune base de données : les candidatures sont stockées dans de simples fichiers du dossier data/ (créé automatiquement). */
session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
mb_internal_encoding('UTF-8');
date_default_timezone_set('Africa/Bamako');
define('SELF', basename(__FILE__)); 

/* ---------- Stockage (fichiers, sans base de données) ---------- */
$dir = __DIR__.'/data';
if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
if (!is_dir($dir) || !is_writable($dir)) { http_response_code(500); exit("Erreur : le dossier data/ n'est pas accessible en écriture."); }
if (!is_file($dir.'/.htaccess')) { @file_put_contents($dir.'/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"); }
if (!is_file($dir.'/index.html')) { @file_put_contents($dir.'/index.html', ''); }
$sfx = substr(sha1(__DIR__), 0, 12);
// Fichiers .php dont la 1re ligne bloque l'exécution : illisibles depuis le web même sans .htaccess
$F = ['c' => "$dir/candidatures-$sfx.php", 'a' => "$dir/admin-$sfx.php", 't' => "$dir/tentatives-$sfx.php"];
const GARDE = "<?php http_response_code(404); exit; ?>\n";
function jread($f){ if (!is_file($f)) return []; $s = @file_get_contents($f); $p = $s === false ? false : strpos($s, "\n"); $j = $p === false ? [] : json_decode(substr($s, $p + 1), true); return is_array($j) ? $j : []; }
function jupdate($f, $fn){
  $h = @fopen($f, 'c+'); if (!$h) { http_response_code(500); exit("Erreur : écriture impossible dans data/."); }
  flock($h, LOCK_EX); $s = stream_get_contents($h); $p = strpos((string)$s, "\n");
  $j = $p === false ? [] : json_decode(substr($s, $p + 1), true); if (!is_array($j)) $j = [];
  $r = $fn($j);
  ftruncate($h, 0); rewind($h); fwrite($h, GARDE.json_encode($j, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)); fflush($h); flock($h, LOCK_UN); fclose($h);
  return $r;
}
function new_ref(){ $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $r = ''; for ($i = 0; $i < 8; $i++) $r .= $a[random_int(0, 31)]; return 'MCBA-'.$r; }
function nb_essais($key){ global $F; return jupdate($F['t'], function(&$j) use ($key){ $j = array_values(array_filter($j, function($x){ return $x['t'] > time() - 900; })); $c = 0; foreach ($j as $x) if ($x['ip'] === $key) $c++; return $c; }); }
function add_essai($key){ global $F; jupdate($F['t'], function(&$j) use ($key){ $j[] = ['ip' => $key, 't' => time()]; }); }
function wa_num($tel){ $d = preg_replace('/\D/', '', (string)$tel); if (strpos($d, '00') === 0) $d = substr($d, 2); elseif (strlen($d) === 8) $d = '223'.$d; return $d; }
function admin_hash(){ global $F; $a = jread($F['a']); return $a['hash'] ?? null; }
function set_admin_hash($p){ global $F; jupdate($F['a'], function(&$j) use ($p){ $j['hash'] = password_hash($p, PASSWORD_DEFAULT); }); }

/* ---------- Données ---------- */
$STATUTS = ['Nouveau','À contacter','Retenu','Refusé'];
$JOURS = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$SEXES = ['Féminin','Masculin'];
$SITUATIONS = ['Élève','Étudiant(e)','Jeune diplômé(e)','Autre'];
$BASKET = ['Oui, en club ou en équipe','Oui, en loisir','Non, mais je suis passionné(e)','Non'];
$HEURES = ['Moins de 3 h','3 à 6 h','6 à 10 h','Plus de 10 h'];
$SOURCES = ['Réseaux sociaux','Un ami ou un camarade','Mon établissement','Autre'];
$COMMUN = ['Jeune motivé(e), responsable et dynamique','Prêt(e) à travailler en équipe','Disponible pour les activités à Ségou'];
$POSTES = [
 ['id'=>'communication-medias','nom'=>'Communication & Médias','resume'=>'Faire connaître la MCBA et produire ses photos, vidéos et contenus.','domaine'=>true,'lien'=>true,
  'blocs'=>[
   ['t'=>'Communication','d'=>'Faire connaître la MCBA et assurer sa communication auprès du public, des lycées, des partenaires et des participants.','m'=>['Gérer les réseaux sociaux','Rédiger les annonces et publications','Préparer les informations sur les activités','Participer à la création des affiches et contenus','Valoriser l\'image de la MCBA']],
   ['t'=>'Médias','d'=>'Produire les contenus visuels et audiovisuels.','m'=>['Prendre des photos','Filmer les matchs et activités','Réaliser des interviews','Créer des vidéos et des Reels','Effectuer des montages','Conserver les archives photos et vidéos']]],
  'skills'=>['Réseaux sociaux','Rédaction','Design d\'affiches','Photo','Vidéo','Montage','Interviews','Reels et formats courts','Matériel : smartphone','Matériel : appareil photo','Matériel : ordinateur']],
 ['id'=>'organisation','nom'=>'Organisation','resume'=>'Veiller au bon déroulement pratique des activités et des matchs.','blocs'=>[
   ['t'=>'','d'=>'Le pôle Organisation veille au bon déroulement pratique des activités et des matchs.','m'=>['Préparer les lieux et espaces','Vérifier le matériel','Organiser l\'installation avant les activités','Coordonner les besoins logistiques','Préparer les matchs et événements','Vérifier que tout est prêt avant le début des activités']]],
  'skills'=>['Logistique','Gestion du matériel','Coordination d\'équipe','Arbitrage ou table de marque','Accueil du public']],
 ['id'=>'securite-premiers-secours','nom'=>'Sécurité & Premiers secours','resume'=>'Prévenir les risques et veiller à la sécurité des participants.','blocs'=>[
   ['t'=>'','d'=>'Ce pôle veille à la prévention des risques et à la sécurité des participants.','m'=>['Identifier les risques','Préparer les dispositifs de sécurité','Connaître les contacts d\'urgence','Signaler rapidement les incidents','Orienter les personnes vers les services compétents','Contribuer à la sécurité des joueurs, responsables et spectateurs']]],
  'skills'=>['Premiers secours (formation ou notions)','Prévention des risques','Gestion d\'incidents','Sang-froid en situation d\'urgence','Surveillance des espaces']],
 ['id'=>'partenariats-mobilisation','nom'=>'Partenariats & Mobilisation','resume'=>'Rechercher et développer les relations avec les partenaires et soutiens de la MCBA.','blocs'=>[
   ['t'=>'','d'=>'Ce pôle recherche et développe les relations avec les partenaires et soutiens de la MCBA.','m'=>['Rechercher des partenaires','Présenter le projet MCBA','Contacter les entreprises et organisations','Suivre les partenaires','Participer à la préparation des conventions','Mobiliser des ressources et soutiens pour les activités']]],
  'skills'=>['Prospection','Présentation de projet','Rédaction de courriers','Négociation','Suivi de partenaires']],
 ['id'=>'statistiques-resultats','nom'=>'Statistiques & Résultats','resume'=>'Collecter, vérifier et organiser les informations sportives.','blocs'=>[
   ['t'=>'','d'=>'Ce pôle est chargé de collecter, vérifier et organiser les informations sportives.','m'=>['Enregistrer les résultats des matchs','Collecter les statistiques','Suivre les scores et classements','Préparer les tableaux de résultats','Signaler les erreurs ou incohérences','Travailler avec la Direction technique pour la validation des résultats']]],
  'skills'=>['Saisie de données','Excel ou tableur','Statistiques sportives','Rigueur et vérification','Tenue de scores']],
 ['id'=>'protocole','nom'=>'Protocole','resume'=>'Assurer l\'accueil, l\'organisation officielle et le bon déroulement des cérémonies.','blocs'=>[
   ['t'=>'','d'=>'Le Protocole assure l\'accueil, l\'organisation officielle et le bon déroulement des cérémonies.','m'=>['Accueillir les invités et partenaires','Orienter les responsables et participants','Préparer l\'ordre des cérémonies','Organiser les remises de trophées et médailles','Accompagner les invités officiels','Veiller au respect du programme protocolaire','Travailler avec l\'Organisation et la Communication lors des événements']]],
  'skills'=>['Accueil','Organisation de cérémonies','Expression orale','Gestion d\'un programme','Remise de trophées et médailles']],
];
foreach ($POSTES as &$x) { $x += ['domaine'=>false,'lien'=>false]; } unset($x);

/* ---------- Outils ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok(){ return isset($_POST['csrf'], $_SESSION['csrf']) && is_string($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }
function is_admin(){ return !empty($_SESSION['admin']); }
function post_str($k,$max=200){ $v=$_POST[$k]??''; return is_string($v)?trim(mb_substr($v,0,$max)):''; }
function post_list($k,$allowed){ $v=$_POST[$k]??[]; if(!is_array($v))return []; return array_values(array_intersect(array_filter($v,'is_string'),$allowed)); }
function opts($list,$cur){ $o='<option value="">Choisir</option>'; foreach($list as $x) $o.='<option'.($x===$cur?' selected':'').'>'.h($x).'</option>'; return $o; }
function chips($name,$list,$cur){ $o=''; foreach($list as $x) $o.='<label class="chip"><input type="checkbox" name="'.$name.'[]" value="'.h($x).'"'.(in_array($x,(array)$cur,true)?' checked':'').'><span>'.h($x).'</span></label>'; return $o; }
function go($url){ header('Location: '.$url); exit; }

function head($title,$noindex=false){ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if($noindex): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<title><?= h($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--green:#0b5a35;--green-d:#073d24;--gold:#f2b61c;--ink:#14221b;--muted:#5a6a61;--paper:#f4f6f5;--line:#d3dbd6;--err:#b42318}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:Barlow,Arial,sans-serif;background:var(--paper);color:var(--ink);line-height:1.55;font-size:17px}
h1,h2,h3,legend{font-family:"Barlow Condensed","Arial Narrow",Arial,sans-serif;line-height:1.05}
.wrap{width:min(1040px,92%);margin:auto}
a{color:inherit}
:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
.bar{background:var(--green-d);color:#fff}
.bar .wrap{display:flex;justify-content:space-between;align-items:center;height:64px}
.logo{display:flex;align-items:center;gap:10px;font:700 24px "Barlow Condensed",Arial,sans-serif;text-decoration:none;color:#fff}
.logo img{width:44px;height:44px;border-radius:50%;object-fit:cover;display:block}
.btn{display:inline-block;background:var(--gold);color:var(--green-d);padding:12px 22px;border-radius:6px;text-decoration:none;border:0;font:600 17px Barlow,Arial,sans-serif;cursor:pointer}
.btn:hover{background:#ffc93a}
.btn.g{background:var(--green);color:#fff}.btn.g:hover{background:var(--green-d)}
.btn.o{background:#fff;color:var(--green);border:1.5px solid var(--green)}
.btn.s{padding:7px 14px;font-size:15px}
.hero{background:var(--green);color:#fff;position:relative;overflow:hidden;padding:80px 0 88px}
.cta{display:flex;gap:14px;flex-wrap:wrap}
.btn.big{font-size:21px;padding:17px 34px;font-weight:700}
.btn.ghost{background:transparent;color:#fff;border:2.5px solid #fff;padding:14.5px 31.5px}
.btn.ghost:hover{background:#fff;color:var(--green-d)}
.hero svg{position:absolute;right:-60px;top:-20px;height:130%;opacity:.2}
.hero .wrap{position:relative}
h1{font-size:clamp(46px,8vw,90px);max-width:12ch;margin-bottom:20px}
.hero p{max-width:54ch;font-size:19px;color:#d9eadf;margin-bottom:28px}
section{padding:60px 0}
h2{font-size:clamp(32px,4.5vw,46px);margin-bottom:12px}
.lead{color:var(--muted);margin-bottom:28px;max-width:60ch}
.posts{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.post{background:#fff;border:1.5px solid var(--line);border-top:6px solid var(--gold);border-radius:6px;padding:22px;display:flex;flex-direction:column;text-decoration:none;color:inherit}
.post:hover{border-color:var(--green);border-top-color:var(--gold)}
.post h3{font-size:30px;margin-bottom:8px}
.post p{color:var(--muted);font-size:16px;margin-bottom:18px;flex:1}
.post b{color:var(--green);font-weight:600}
.back{display:inline-block;margin-bottom:22px;color:var(--green);font-weight:600;text-decoration:none}
.info{display:grid;grid-template-columns:1.3fr 1fr;gap:34px;margin-bottom:46px}
.info h3{font-size:24px;margin-bottom:8px}
.info ul{padding-left:20px;margin-bottom:18px}
.info li{margin-bottom:5px}
.tags{background:#fff;border-left:5px solid var(--gold);padding:18px 20px;align-self:start}
.tags ul{margin:8px 0 12px 18px}.tags p{margin-bottom:8px;font-size:16px}
form.card{background:#fff;border:1.5px solid var(--line);border-radius:8px;padding:32px}
fieldset{border:0;margin-bottom:32px}
legend{font-size:28px;font-weight:700;padding-bottom:8px;margin-bottom:14px;border-bottom:3px solid var(--gold);width:100%}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.full{grid-column:1/-1}
label,.lbl{display:block;font-weight:600;font-size:15px;margin-bottom:6px}
.opt{font-weight:400;color:var(--muted)}
input[type=text],input[type=email],input[type=tel],input[type=date],input[type=url],input[type=password],input[type=search],select,textarea{width:100%;padding:12px;border:1.5px solid #b9c5be;border-radius:6px;font:inherit;background:#fff;color:var(--ink)}
textarea{min-height:120px;resize:vertical}
input:focus,select:focus,textarea:focus{border-color:var(--green);outline:3px solid #f2b61c66;outline-offset:0}
.hint{font-size:13px;color:var(--muted);margin-top:5px;display:flex;justify-content:space-between}
.chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.chip input{position:absolute;opacity:0}
.chip span{display:inline-block;padding:8px 14px;border:1.5px solid #b9c5be;border-radius:999px;font-size:15px;cursor:pointer;background:#fff;font-weight:500}
.chip input:checked+span{background:var(--green);border-color:var(--green);color:#fff}
.chip input:focus-visible+span{outline:3px solid var(--gold);outline-offset:2px}
.agree{display:flex;gap:12px;align-items:flex-start;font-weight:400;font-size:15px}
.agree input{width:20px;height:20px;margin-top:3px;accent-color:var(--green);flex:none}
.submit{width:100%;font-size:19px;padding:16px;margin-top:18px}
.errs{background:#fef3f2;border:1.5px solid var(--err);color:var(--err);border-radius:6px;padding:14px 18px;margin-bottom:22px;font-weight:600}
.errs ul{padding-left:18px}
.hp{position:absolute;left:-9999px}
.done{border:2px solid var(--green);border-radius:8px;padding:34px;background:#e7f3ec}
.done p{margin-bottom:10px}
.ref{display:inline-block;font:700 30px "Barlow Condensed",Arial,sans-serif;background:#fff;padding:6px 16px;border-radius:6px;border:1.5px dashed var(--green)}
footer{background:var(--green-d);color:#b9d3c4;padding:28px 0;text-align:center;font-size:14px}
footer a{color:#b9d3c4}
.login{max-width:400px;margin:60px auto;background:#fff;padding:28px;border-radius:8px;border:1.5px solid var(--line)}
.login h1{font-size:34px;max-width:none;margin-bottom:6px}.login p{color:var(--muted);margin-bottom:16px;font-size:15px}
.login input{margin-bottom:12px}
.stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.stat{background:#fff;border:1.5px solid var(--line);border-left:5px solid var(--gold);padding:10px 18px;border-radius:6px;min-width:110px}
.stat b{display:block;font-size:28px;line-height:1.1}
.tools{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.tools input{flex:1;min-width:200px}.tools select{width:auto}
details.row{background:#fff;border:1.5px solid var(--line);border-radius:6px;margin-bottom:10px}
details.row summary{display:grid;grid-template-columns:1.6fr 1.2fr 1fr .8fr;gap:10px;padding:12px 16px;cursor:pointer;align-items:center}
details.row summary small{display:block;color:var(--muted)}
.pole{font-weight:600;color:var(--green)}
.badge{display:inline-block;padding:3px 12px;border-radius:999px;background:#e7f3ec;color:var(--green-d);font-size:14px;font-weight:600}
.badge.n{background:#fff4d6;color:#7a5a00}.badge.r{background:#fef3f2;color:var(--err)}
.detail{border-top:1px solid var(--line);padding:16px;background:#fafcfb}
.detail dl{display:grid;grid-template-columns:200px 1fr;gap:6px 14px}
.detail dt{color:var(--muted);font-weight:600}.detail dd{white-space:pre-wrap;word-break:break-word}
.acts{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap}.acts form{display:flex;gap:8px}.acts select{width:auto}
.empty{padding:40px;text-align:center;color:var(--muted)}
.msgbox{margin-top:20px;border-top:1px solid var(--line);padding-top:16px}
.msgbox h3{font-size:24px;margin-bottom:10px}
.msgbox .msg,.suivi .msg{background:#fff;border:1.5px solid var(--line);border-left:5px solid var(--gold);padding:12px 16px;border-radius:6px;margin-bottom:12px}
.msg small{display:block;color:var(--muted);margin-bottom:6px}
.msgbox form label{margin-top:12px}.msgbox .agree{margin:12px 0}
@media(max-width:760px){.posts,.info,.grid{grid-template-columns:1fr}.hero svg{opacity:.1}form.card{padding:20px}section{padding:44px 0}details.row summary{grid-template-columns:1fr 1fr}.detail dl{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="bar"><div class="wrap"><a class="logo" href="<?= SELF ?>"><img src="logo.jpeg" alt="Logo MCBA">MCBA</a>
<?php if(is_admin()): ?><form method="post" action="<?= SELF ?>?page=admin"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="logout"><button class="btn s">Se déconnecter</button></form><?php endif; ?>
</div></div>
<?php }
function foot($show_admin_link = false){ ?>
<footer><div class="wrap">© 2026 Mali Campus Basketball Association — Ségou, Mali · <a href="<?= SELF ?>?page=suivi">Suivre ma candidature</a><?php if ($show_admin_link): ?> · <a href="<?= SELF ?>?page=admin">Espace administrateur</a><?php endif; ?></div></footer>
</body>
</html>
<?php }

/* ---------- Espace administrateur ---------- */
$page = $_GET['page'] ?? 'home';
if ($page === 'admin') {
  $hash = admin_hash(); $aerr = '';
  $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = post_str('action', 20);
    if (!csrf_ok()) { $aerr = 'Session expirée, réessaie.'; }
    elseif ($a === 'setup' && !$hash) {
      $p1 = (string)($_POST['pw'] ?? ''); $p2 = (string)($_POST['pw2'] ?? '');
      if (strlen($p1) < 8) $aerr = 'Le mot de passe doit contenir au moins 8 caractères.';
      elseif ($p1 !== $p2) $aerr = 'Les deux mots de passe sont différents.';
      else { set_admin_hash($p1); session_regenerate_id(true); $_SESSION['admin'] = 1; go(SELF.'?page=admin'); }
    } elseif ($a === 'login' && $hash) {
      $nb = jupdate($F['t'], function(&$j) use ($ip){ $j = array_values(array_filter($j, function($x){ return $x['t'] > time() - 900; })); $c = 0; foreach ($j as $x) if ($x['ip'] === $ip) $c++; return $c; });
      if ($nb >= 6) $aerr = 'Trop de tentatives. Réessaie dans 15 minutes.';
      elseif (password_verify((string)($_POST['pw'] ?? ''), $hash)) { session_regenerate_id(true); $_SESSION['admin'] = 1; go(SELF.'?page=admin'); }
      else { jupdate($F['t'], function(&$j) use ($ip){ $j[] = ['ip' => $ip, 't' => time()]; }); sleep(1); $aerr = 'Mot de passe incorrect.'; }
    } elseif (is_admin()) {
      if ($a === 'logout') { $_SESSION = []; session_destroy(); go(SELF.''); }
      if ($a === 'changepw') {
        $n1 = (string)($_POST['n1'] ?? ''); $n2 = (string)($_POST['n2'] ?? '');
        if (!password_verify((string)($_POST['cur'] ?? ''), $hash)) { sleep(1); $_SESSION['flash'] = ['err', 'Le mot de passe actuel est incorrect.']; }
        elseif (strlen($n1) < 8) $_SESSION['flash'] = ['err', 'Le nouveau mot de passe doit contenir au moins 8 caractères.'];
        elseif ($n1 !== $n2) $_SESSION['flash'] = ['err', 'Les deux nouveaux mots de passe sont différents.'];
        else { set_admin_hash($n1); session_regenerate_id(true); $_SESSION['flash'] = ['ok', 'Mot de passe modifié.']; }
        go(SELF.'?page=admin#mdp');
      }
      if ($a === 'message') {
        $mid = (int)($_POST['id'] ?? 0); $txt = post_str('texte', 1500); $mod = post_str('modele', 10);
        $maj = (!empty($_POST['majstatut']) && ($mod === 'retenu' || $mod === 'refuse')) ? ($mod === 'retenu' ? 'Retenu' : 'Refusé') : '';
        if ($txt === '') { $_SESSION['flash'] = ['err', "Écris un message avant de l'envoyer."]; }
        else {
          $info = jupdate($F['c'], function(&$j) use ($mid, $txt, $maj){
            foreach ($j as &$r) { if ($r['id'] === $mid) {
              if ($maj !== '') $r['statut'] = $maj;
              $r['messages'][] = ['date' => date('Y-m-d H:i:s'), 'texte' => $txt];
              return true;
            } }
            return null;
          });
          if (!$info) { $_SESSION['flash'] = ['err', 'Candidature introuvable.']; }
          else {
            $_SESSION['flash'] = ['ok', 'Message enregistré : le candidat le lit sur « Suivre ma candidature ». Utilise le bouton WhatsApp pour le prévenir.'.($maj !== '' ? " Statut : $maj." : '')];
          }
        }
        go(SELF.'?page=admin&'.preg_replace('/[^A-Za-z0-9=&%+_.\-]/', '', post_str('qs', 300)));
      }
      $id = (int)($_POST['id'] ?? 0);
      if ($a === 'statut' && in_array($_POST['statut'] ?? '', $STATUTS, true)) { $ns = $_POST['statut']; jupdate($F['c'], function(&$j) use ($id, $ns){ foreach ($j as &$r) { if ($r['id'] === $id) $r['statut'] = $ns; } }); }
      if ($a === 'supprimer') jupdate($F['c'], function(&$j) use ($id){ $j = array_values(array_filter($j, function($r) use ($id){ return $r['id'] !== $id; })); });
      go(SELF.'?page=admin&'.preg_replace('/[^A-Za-z0-9=&%+_.\-]/', '', post_str('qs', 300)));
    }
  }
  if (!is_admin()) {
    head('Administration MCBA', true); ?>
<form class="login" method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
<?php if (!$hash): ?>
  <h1>Créer le mot de passe</h1><p>Première utilisation : choisis le mot de passe administrateur.</p>
  <input type="hidden" name="action" value="setup">
  <label for="pw">Mot de passe (8 caractères minimum)</label><input type="password" id="pw" name="pw" required minlength="8" autocomplete="new-password">
  <label for="pw2">Confirmer le mot de passe</label><input type="password" id="pw2" name="pw2" required autocomplete="new-password">
  <button class="btn g" style="width:100%">Créer et entrer</button>
<?php else: ?>
  <h1>Administration</h1><p>Connecte-toi pour voir les candidatures.</p>
  <input type="hidden" name="action" value="login">
  <label for="pw">Mot de passe</label><input type="password" id="pw" name="pw" required autocomplete="current-password" autofocus>
  <button class="btn g" style="width:100%">Se connecter</button>
<?php endif; ?>
<?php if ($aerr): ?><p class="errs" style="margin:14px 0 0"><?= h($aerr) ?></p><?php endif; ?>
</form>
<?php foot(); exit; }

  /* Liste filtrée */
  $fp = (string)($_GET['pole'] ?? ''); $fs = (string)($_GET['statut'] ?? ''); $q = trim((string)($_GET['q'] ?? ''));
  $all = jread($F['c']); usort($all, function($x, $y){ return $y['id'] - $x['id']; });
  $rows = array_values(array_filter($all, function($r) use ($fp, $fs, $q){
    if ($fp !== '' && $r['pole'] !== $fp) return false;
    if ($fs !== '' && $r['statut'] !== $fs) return false;
    return $q === '' || mb_stripos($r['reference'].' '.json_encode($r['data'], JSON_UNESCAPED_UNICODE), $q) !== false;
  }));
  $qs = http_build_query(array_filter(['pole'=>$fp,'statut'=>$fs,'q'=>$q], 'strlen'));

  if (isset($_GET['export'])) {
    $keys = [];
    foreach ($rows as &$r) { $r['d'] = $r['data']; $keys = array_unique(array_merge($keys, array_keys($r['d']))); } unset($r);
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="candidatures-mcba.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    $clean = function($v){ $v = is_array($v) ? implode(' | ', $v) : (string)$v; return (preg_match('/^[=+\-@\t\r]/', $v) && !preg_match('/^\+[0-9 ]+$/', $v)) ? "'".$v : $v; };
    fputcsv($o, array_merge(['reference','date','pole','statut'], $keys), ';');
    foreach ($rows as $r) { $l = [$r['reference'], $r['created_at'], $r['pole'], $r['statut']]; foreach ($keys as $k) $l[] = $clean($r['d'][$k] ?? ''); fputcsv($o, array_map($clean, $l), ';'); }
    exit;
  }
  $cnt = array_count_values(array_column($all, 'statut'));
  head('Administration MCBA', true); ?>
<section style="padding-top:34px"><div class="wrap">
<h2 style="font-size:40px">Candidatures</h2>
<?php if (!empty($_SESSION['flash'])): $fl = $_SESSION['flash']; unset($_SESSION['flash']); ?><div class="<?= $fl[0] === 'ok' ? 'done' : 'errs' ?>" role="alert" style="padding:12px 18px;margin-bottom:18px"><?= h($fl[1]) ?></div><?php endif; ?>
<div class="stats"><div class="stat"><b><?= count($all) ?></b>Total</div>
<?php foreach ($STATUTS as $s): ?><div class="stat"><b><?= (int)($cnt[$s] ?? 0) ?></b><?= h($s) ?></div><?php endforeach; ?></div>
<form class="tools" method="get"><input type="hidden" name="page" value="admin">
<input type="search" name="q" value="<?= h($q) ?>" placeholder="Rechercher un nom, un téléphone, une référence…" aria-label="Rechercher">
<select name="pole" aria-label="Pôle"><option value="">Tous les pôles</option><?php foreach ($POSTES as $x): ?><option<?= $fp === $x['nom'] ? ' selected' : '' ?>><?= h($x['nom']) ?></option><?php endforeach; ?></select>
<select name="statut" aria-label="Statut"><option value="">Tous les statuts</option><?php foreach ($STATUTS as $s): ?><option<?= $fs === $s ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
<button class="btn g">Filtrer</button><a class="btn o" href="<?= SELF ?>?page=admin&export=1<?= $qs ? '&'.h($qs) : '' ?>">Exporter en CSV</a></form>
<?php if (!$rows): ?><div class="empty">Aucune candidature.</div><?php endif; ?>
<?php foreach ($rows as $r): $d = $r['data']; $bc = $r['statut'] === 'Nouveau' ? 'n' : ($r['statut'] === 'Refusé' ? 'r' : ''); ?>
<details class="row"><summary><div><b><?= h(($d['prenom'] ?? '').' '.($d['nom'] ?? '')) ?></b><small><?= h($r['reference']) ?></small></div>
<div class="pole"><?= h($r['pole']) ?></div><div><?= h($d['telephone'] ?? '') ?><small><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></small></div><div><span class="badge <?= $bc ?>"><?= h($r['statut']) ?></span></div></summary>
<div class="detail"><dl><?php foreach ($d as $k => $v): ?><dt><?= h(str_replace('_', ' ', $k)) ?></dt><dd><?= h(is_array($v) ? implode(', ', $v) : $v) ?></dd><?php endforeach; ?></dl>
<div class="msgbox"><h3>Messagerie</h3>
<?php $rf = $r['reference']; $pn = $d['prenom'] ?? '';
$t_ok = "Bonjour $pn,\n\nBonne nouvelle : ta candidature (réf. $rf) pour le pôle {$r['pole']} a été retenue. Félicitations !\nNous te contacterons très bientôt pour la suite.\n\nL'équipe MCBA";
$t_no = "Bonjour $pn,\n\nMerci pour l'intérêt que tu portes à la MCBA. Après étude de ta candidature (réf. $rf) pour le pôle {$r['pole']}, nous ne pouvons pas la retenir pour le moment.\nNous t'encourageons à rester connecté(e) pour les prochaines opportunités.\n\nL'équipe MCBA"; ?>
<?php foreach (($r['messages'] ?? []) as $m): ?><div class="msg"><small><?= h(date('d/m/Y H:i', strtotime($m['date']))) ?></small><?= nl2br(h($m['texte'])) ?>
<div class="acts"><a class="btn o s" target="_blank" rel="noopener" href="https://wa.me/<?= h(wa_num($d['telephone'] ?? '')) ?>?text=<?= h(rawurlencode($m['texte'])) ?>">Envoyer par WhatsApp</a></div></div><?php endforeach; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="message"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="qs" value="<?= h($qs) ?>">
<label for="mod<?= (int)$r['id'] ?>">Modèle de message</label>
<select id="mod<?= (int)$r['id'] ?>" name="modele" onchange="var t=this.selectedOptions[0].getAttribute('data-t');if(t)this.form.texte.value=t"><option value="libre">Message libre</option><option value="retenu" data-t="<?= h($t_ok) ?>">Candidature retenue</option><option value="refuse" data-t="<?= h($t_no) ?>">Candidature non retenue</option></select>
<label for="txt<?= (int)$r['id'] ?>">Message</label><textarea id="txt<?= (int)$r['id'] ?>" name="texte" required maxlength="1500" placeholder="Choisis un modèle ou écris ton message."></textarea>
<label class="agree"><input type="checkbox" name="majstatut" value="1" checked><span>Mettre à jour le statut selon le modèle (Retenu / Refusé)</span></label>
<button class="btn g s">Envoyer le message</button></form></div>
<div class="acts">
<form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="statut"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="qs" value="<?= h($qs) ?>">
<select name="statut" aria-label="Statut"><?php foreach ($STATUTS as $s): ?><option<?= $r['statut'] === $s ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select><button class="btn g s">Changer le statut</button></form>
<form method="post" onsubmit="return confirm('Supprimer définitivement cette candidature ?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="qs" value="<?= h($qs) ?>"><button class="btn o s">Supprimer</button></form>
</div></div></details>
<?php endforeach; ?>
<details class="row" id="mdp" style="margin-top:26px"><summary><b>Changer le mot de passe</b></summary><div class="detail">
<form method="post" style="max-width:380px"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="changepw">
<label for="cur">Mot de passe actuel</label><input type="password" id="cur" name="cur" required autocomplete="current-password" style="margin-bottom:12px">
<label for="n1">Nouveau mot de passe (8 caractères minimum)</label><input type="password" id="n1" name="n1" required minlength="8" autocomplete="new-password" style="margin-bottom:12px">
<label for="n2">Confirmer le nouveau mot de passe</label><input type="password" id="n2" name="n2" required autocomplete="new-password" style="margin-bottom:14px">
<button class="btn g">Enregistrer</button></form></div></details>
</div></section>
<?php foot(); exit;
}

/* ---------- Suivi de candidature (candidat) ---------- */
if ($page === 'suivi') {
  $res = null; $serr = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref = strtoupper(preg_replace('/\s+/', '', post_str('reference', 20))); if ($ref !== '' && strpos($ref, 'MCBA-') !== 0) $ref = 'MCBA-'.$ref; $key = ($_SERVER['REMOTE_ADDR'] ?? '?').'|suivi';
    if (!csrf_ok()) { $serr = 'Session expirée, réessaie.'; }
    elseif (nb_essais($key) >= 10) { $serr = 'Trop de tentatives. Réessaie dans 15 minutes.'; }
    else {
      foreach (jread($F['c']) as $r) { if ($r['reference'] === $ref) $res = $r; }
      if (!$res) { add_essai($key); sleep(1); $serr = 'Aucune candidature ne correspond à cette référence. Vérifie-la et réessaie.'; }
    }
  }
  head('Suivre ma candidature — MCBA'); ?>
<section class="suivi"><div class="wrap" style="max-width:700px">
<a class="back" href="<?= SELF ?>">← Accueil</a>
<h2>Suivre ma candidature</h2>
<?php if (!$res): ?><p class="lead">Saisis la référence reçue après l'envoi de ta candidature (du type MCBA-K7M4P2XQ) pour lire les messages de l'équipe.</p><?php endif; ?>
<?php if ($res): ?>
<div class="done" style="margin-bottom:24px"><p>Candidature <b><?= h($res['reference']) ?></b> · pôle <b><?= h($res['pole']) ?></b><br>Reçue le <?= h(date('d/m/Y', strtotime($res['created_at']))) ?></p></div>
<h3 style="font-size:28px;margin-bottom:12px">Messages de la MCBA</h3>
<?php foreach (array_reverse($res['messages'] ?? []) as $m): ?><div class="msg"><small><?= h(date('d/m/Y H:i', strtotime($m['date']))) ?></small><?= nl2br(h($m['texte'])) ?></div><?php endforeach; ?>
<?php if (empty($res['messages'])): ?><p class="lead">Pas encore de message. Ta candidature est bien reçue : nous te répondrons dès qu'elle aura été étudiée.</p><?php endif; ?>
<?php else: ?>
<form class="card" method="post"><?php if ($serr): ?><div class="errs" role="alert"><?= h($serr) ?></div><?php endif; ?>
<input type="hidden" name="csrf" value="<?= csrf() ?>">
<label for="sref">Référence</label><input type="text" id="sref" name="reference" required placeholder="MCBA-K7M4P2XQ" maxlength="20" value="<?= h($_POST['reference'] ?? '') ?>">
<button class="btn g submit" type="submit">Voir mes messages</button></form>
<?php endif; ?>
</div></section>
<?php foot(); exit;
}

/* ---------- Site public ---------- */
$poste = null;
if (isset($_GET['p']) && is_string($_GET['p'])) foreach ($POSTES as $x) if ($x['id'] === $_GET['p']) $poste = $x;

if (!$poste) {
  head('Rejoins la MCBA — Postes et candidature'); ?>
<header class="hero">
<svg viewBox="0 0 500 470" fill="none" stroke="#f2b61c" stroke-width="4" aria-hidden="true"><rect x="20" y="10" width="460" height="450"/><rect x="170" y="10" width="160" height="190"/><circle cx="250" cy="200" r="80"/><path d="M60 10v110a190 190 0 0 0 380 0V10"/><circle cx="250" cy="52" r="16"/></svg>
<div class="wrap"><h1>Un ballon, une ambition, un avenir.</h1>
<p>La Mali Campus Basketball Association utilise le basket pour former la jeunesse : discipline, leadership, cohésion et détection de talents. Première phase à Ségou. Nous cherchons des jeunes motivés pour la faire vivre.</p>
<div class="cta"><a class="btn big" href="#postes">Postuler</a><a class="btn big ghost" href="<?= SELF ?>?page=suivi">Suivre ma candidature</a></div></div>
</header>
<section id="postes"><div class="wrap">
<h2>Choisis ton pôle</h2>
<p class="lead">Rejoindre la MCBA, ce n'est pas seulement occuper un poste : c'est contribuer à construire un projet sportif pour la jeunesse. Clique sur un pôle pour voir le poste et postuler.</p>
<div class="posts"><?php foreach ($POSTES as $x): ?><a class="post" href="<?= SELF ?>?p=<?= h($x['id']) ?>"><h3><?= h($x['nom']) ?></h3><p><?= h($x['resume']) ?></p><b>Voir le poste et postuler</b></a><?php endforeach; ?></div>
</div></section>
<?php foot(true); exit; }

/* ---------- Candidature : traitement ---------- */
$errs = []; $old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old = $_POST;
  if (!csrf_ok()) { $errs[] = 'La session a expiré. Réessaie.'; }
  elseif (post_str('bot-field') !== '') { $_SESSION['ok'] = ['ref'=>new_ref(),'prenom'=>'','pole'=>$poste['nom']]; go(SELF.'?p='.$poste['id'].'&ok=1'); }
  else {
    $d = ['prenom'=>post_str('prenom',60),'nom'=>post_str('nom',60),'sexe'=>post_str('sexe'),'date_naissance'=>post_str('date_naissance',10),'telephone'=>post_str('telephone',20),'email'=>post_str('email',120),'ville_quartier'=>post_str('ville_quartier',100),'situation'=>post_str('situation'),'etablissement'=>post_str('etablissement',120),'niveau'=>post_str('niveau',60),'pratique_basket'=>post_str('pratique_basket'),'experiences'=>post_str('experiences',800)];
    if ($poste['domaine']) $d['domaine'] = post_str('domaine');
    $d['competences'] = post_list('competences', $poste['skills']);
    if ($poste['lien']) $d['lien_realisations'] = post_str('lien_realisations', 250);
    $d['motivation'] = post_str('motivation', 1200);
    $d['jours'] = post_list('jours', $JOURS);
    $d['heures_semaine'] = post_str('heures_semaine'); $d['source'] = post_str('source');
    $dn = DateTime::createFromFormat('!Y-m-d', $d['date_naissance']);
    if ($d['prenom'] === '' || $d['nom'] === '') $errs[] = 'Indique ton prénom et ton nom.';
    if (!in_array($d['sexe'], $SEXES, true)) $errs[] = 'Indique ton sexe.';
    if (!$dn || $dn->format('Y-m-d') !== $d['date_naissance'] || (int)$dn->format('Y') < 1950 || $dn > new DateTime('today')) $errs[] = 'La date de naissance est invalide.';
    if (!preg_match('/^[0-9+\s]{8,18}$/', $d['telephone'])) $errs[] = 'Le numéro de téléphone est invalide.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errs[] = "L'adresse e-mail est invalide.";
    if ($d['ville_quartier'] === '') $errs[] = 'Indique ta ville et ton quartier.';
    if (!in_array($d['situation'], $SITUATIONS, true)) $errs[] = 'Indique ta situation actuelle.';
    if (!in_array($d['pratique_basket'], $BASKET, true)) $errs[] = 'Indique si tu pratiques le basketball.';
    if ($poste['domaine'] && !in_array($d['domaine'], ['Communication','Médias','Communication et Médias'], true)) $errs[] = 'Choisis ton domaine.';
    if (!empty($d['lien_realisations']) && !(preg_match('#^https?://#i', $d['lien_realisations']) && filter_var($d['lien_realisations'], FILTER_VALIDATE_URL))) $errs[] = 'Le lien vers tes réalisations est invalide.';
    if (mb_strlen($d['motivation']) < 60) $errs[] = 'Ta motivation doit contenir au moins 60 caractères.';
    if (!$d['jours']) $errs[] = 'Choisis au moins un jour de disponibilité.';
    if (!in_array($d['heures_semaine'], $HEURES, true)) $errs[] = 'Indique ton temps disponible par semaine.';
    if ($d['source'] !== '' && !in_array($d['source'], $SOURCES, true)) $d['source'] = '';
    if (post_str('engagement', 5) !== 'Oui') $errs[] = "Coche la case d'engagement.";
    $tel = preg_replace('/\D/', '', $d['telephone']);
    if (!$errs) {
      $ref = new_ref(); $nom = $poste['nom'];
      $ajout = jupdate($F['c'], function(&$j) use ($nom, $tel, $ref, $d){
        $id = 0; foreach ($j as $r) { if ($r['pole'] === $nom && $r['telephone'] === $tel) return false; $id = max($id, $r['id']); }
        $j[] = ['id' => $id + 1, 'created_at' => date('Y-m-d H:i:s'), 'reference' => $ref, 'pole' => $nom, 'statut' => 'Nouveau', 'telephone' => $tel, 'data' => $d];
        return true;
      });
      if (!$ajout) $errs[] = 'Une candidature avec ce numéro existe déjà pour ce pôle.';
    }
    if (!$errs) {
      $_SESSION['ok'] = ['ref'=>$ref,'prenom'=>$d['prenom'],'pole'=>$poste['nom']];
      go(SELF.'?p='.$poste['id'].'&ok=1');
    }
  }
}
function v($k){ global $old; return h(is_string($old[$k] ?? null) ? $old[$k] : ''); }
function sv($k){ global $old; return is_string($old[$k] ?? null) ? $old[$k] : ''; }

/* ---------- Page d'un poste ---------- */
head('Pôle '.$poste['nom'].' — MCBA'); ?>
<section><div class="wrap">
<a class="back" href="<?= SELF ?>#postes">← Voir les autres postes</a>
<?php if (isset($_GET['ok']) && !empty($_SESSION['ok'])): $ok = $_SESSION['ok']; unset($_SESSION['ok']); ?>
<div class="done"><h2>Candidature envoyée</h2>
<p>Merci<?= $ok['prenom'] !== '' ? ' <b>'.h($ok['prenom']).'</b>' : '' ?>, l'équipe de la MCBA a bien reçu ta candidature pour le pôle <b><?= h($ok['pole']) ?></b>.</p>
<p>Garde ce numéro de référence :</p><p><span class="ref"><?= h($ok['ref']) ?></span></p>
<p>Nous te contacterons par téléphone ou WhatsApp pour la suite. Tu peux aussi lire nos messages à tout moment sur la page <a href="<?= SELF ?>?page=suivi" style="color:var(--green);font-weight:600">Suivre ma candidature</a> avec cette référence.</p>
<p><a class="back" href="<?= SELF ?>#postes">Retour aux postes</a></p></div>
<?php else: ?>
<div class="info"><div><h2>Pôle <?= h($poste['nom']) ?></h2>
<p class="lead" style="margin-bottom:16px"><?= h(count($poste['blocs']) > 1 ? $poste['resume'] : $poste['blocs'][0]['d']) ?></p>
<?php foreach ($poste['blocs'] as $b): if (count($poste['blocs']) > 1): ?><h3><?= h($b['t']) ?></h3><p style="margin-bottom:6px"><?= h($b['d']) ?></p><p><b>Missions :</b></p><?php else: ?><h3>Missions</h3><?php endif; ?>
<ul><?php foreach ($b['m'] as $m): ?><li><?= h($m) ?></li><?php endforeach; ?></ul><?php endforeach; ?></div>
<div class="tags"><h3>Profil recherché</h3><ul><?php foreach ($COMMUN as $c): ?><li><?= h($c) ?></li><?php endforeach; ?></ul>
<p><b>Lieu :</b> Ségou (première phase)</p><p><a href="#formulaire" style="color:var(--green);font-weight:600">Postuler maintenant ↓</a></p></div></div>

<form class="card" id="formulaire" method="post" action="<?= SELF ?>?p=<?= h($poste['id']) ?>">
<?php if ($errs): ?><div class="errs" role="alert"><ul><?php foreach ($errs as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<input type="hidden" name="csrf" value="<?= csrf() ?>">
<p class="hp"><label>Ne pas remplir <input name="bot-field" tabindex="-1" autocomplete="off"></label></p>
<fieldset><legend>Ton identité</legend><div class="grid">
<div><label for="prenom">Prénom *</label><input type="text" id="prenom" name="prenom" required maxlength="60" value="<?= v('prenom') ?>" autocomplete="given-name"></div>
<div><label for="nom">Nom *</label><input type="text" id="nom" name="nom" required maxlength="60" value="<?= v('nom') ?>" autocomplete="family-name"></div>
<div><label for="sexe">Sexe *</label><select id="sexe" name="sexe" required><?= opts($SEXES, sv('sexe')) ?></select></div>
<div><label for="naissance">Date de naissance *</label><input type="date" id="naissance" name="date_naissance" required value="<?= v('date_naissance') ?>"></div>
<div><label for="tel">Téléphone (WhatsApp de préférence) *</label><input type="tel" id="tel" name="telephone" required placeholder="+223 70 00 00 00" pattern="[0-9+\s]{8,18}" value="<?= v('telephone') ?>" autocomplete="tel"></div>
<div><label for="email">E-mail <span class="opt">(facultatif)</span></label><input type="email" id="email" name="email" maxlength="120" value="<?= v('email') ?>"></div>
<div class="full"><label for="ville">Ville et quartier *</label><input type="text" id="ville" name="ville_quartier" required maxlength="100" placeholder="Ex. : Ségou, Pélengana" value="<?= v('ville_quartier') ?>"></div>
</div></fieldset>
<fieldset><legend>Ton parcours</legend><div class="grid">
<div><label for="situation">Situation actuelle *</label><select id="situation" name="situation" required><?= opts($SITUATIONS, sv('situation')) ?></select></div>
<div><label for="etab">Établissement</label><input type="text" id="etab" name="etablissement" maxlength="120" placeholder="Lycée, université, école…" value="<?= v('etablissement') ?>"></div>
<div><label for="niveau">Classe ou niveau d'études</label><input type="text" id="niveau" name="niveau" maxlength="60" placeholder="Ex. : Terminale, L2…" value="<?= v('niveau') ?>"></div>
<div><label for="basket">Pratiques-tu le basketball ? *</label><select id="basket" name="pratique_basket" required><?= opts($BASKET, sv('pratique_basket')) ?></select></div>
<div class="full"><label for="exp">Expériences utiles <span class="opt">(associations, bénévolat, sport, médias, événements)</span></label><textarea id="exp" name="experiences" maxlength="800" style="min-height:90px"><?= v('experiences') ?></textarea></div>
</div></fieldset>
<fieldset><legend>Tes compétences pour ce pôle</legend>
<?php if ($poste['domaine']): ?><div style="margin-bottom:16px"><label for="dom">Ton domaine *</label><select id="dom" name="domaine" required><?= opts(['Communication','Médias','Communication et Médias'], sv('domaine')) ?></select></div><?php endif; ?>
<div class="chips"><?= chips('competences', $poste['skills'], $old['competences'] ?? []) ?></div>
<?php if ($poste['lien']): ?><label for="lien">Lien vers tes réalisations <span class="opt">(facultatif)</span></label><input type="url" id="lien" name="lien_realisations" maxlength="250" placeholder="https://" value="<?= v('lien_realisations') ?>"><?php endif; ?>
</fieldset>
<fieldset><legend>Ta motivation et tes disponibilités</legend>
<label for="motiv">Pourquoi veux-tu rejoindre la MCBA ? *</label>
<textarea id="motiv" name="motivation" required minlength="60" maxlength="1200" placeholder="Ce qui te motive et ce que tu veux apporter au projet."><?= v('motivation') ?></textarea>
<div class="hint"><span>60 caractères minimum</span><span id="count">0 / 1200</span></div>
<div style="margin-top:18px"><span class="lbl">Jours où tu es disponible *</span><div class="chips"><?= chips('jours', $JOURS, $old['jours'] ?? []) ?></div></div>
<div class="grid"><div><label for="heures">Temps disponible par semaine *</label><select id="heures" name="heures_semaine" required><?= opts($HEURES, sv('heures_semaine')) ?></select></div>
<div><label for="source">Comment as-tu connu la MCBA ?</label><select id="source" name="source"><?= opts($SOURCES, sv('source')) ?></select></div></div>
</fieldset>
<label class="agree"><input type="checkbox" name="engagement" required value="Oui"><span>Je certifie que les informations sont exactes et j'accepte que la MCBA me contacte par téléphone ou WhatsApp au sujet de ma candidature. </span></label>
<P> NB: <span>Aucune candidature ne garantit automatiquement l’obtention du poste choisi.</span></P>
<button class="btn g submit" type="submit">Envoyer ma candidature</button>
</form>
<script>
var m=document.getElementById('motiv'),c=document.getElementById('count');
function u(){c.textContent=m.value.length+' / 1200'}m.addEventListener('input',u);u();
var e=document.querySelector('.errs');if(e)e.scrollIntoView({block:'center'});
</script>
<?php endif; ?>
</div></section>
<?php foot();
