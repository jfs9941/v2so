<?php
if(!extension_loaded('v2')){throw new \RuntimeException('ext');}
define('_J',__DIR__.'/src');
$GLOBALS['_l']=[];$GLOBALS['_m']=null;
function _jm():array{if($GLOBALS['_m']!==null)return $GLOBALS['_m'];$f=_J.'/manifest.bin';if(!file_exists($f)){$GLOBALS['_m']=[];return[];}$GLOBALS['_m']=\Jfs\Core\Encoder::loadManifest($f);return $GLOBALS['_m'];}
spl_autoload_register(function(string $c):void{if(strncmp($c,'Module\',7)!==0)return;if(class_exists($c,false)||interface_exists($c,false)||trait_exists($c,false)||enum_exists($c,false))return;if(isset($GLOBALS['_l'][$c]))return;$r=str_replace('\\','/',substr($c,7)).'.php';$p=_J.'/'.$r;if(file_exists($p)){$GLOBALS['_l'][$c]=true;require $p;return;}$m=_jm();if(!isset($m[$r]))return;$e=_J.'/'.$m[$r]['file'];if(!file_exists($e))return;$GLOBALS['_l'][$c]=true;\Jfs\Core\Encoder::loadClass($e);},true,true);
function jfs_require_encrypted(string $r):mixed{$p=_J.'/'.$r;if(file_exists($p))return require $p;$m=_jm();if(!isset($m[$r]))throw new \RuntimeException($r);$e=_J.'/'.$m[$r]['file'];if(!file_exists($e))throw new \RuntimeException($e);return \Jfs\Core\Encoder::loadFile($e);}
